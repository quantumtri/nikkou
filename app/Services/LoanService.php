<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\UnitStatus;
use App\Exceptions\UnitNotLoanable;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(private readonly UnitStatusService $unitStatus) {}

    /**
     * Record a loan (spec S2): the unit becomes "on loan", the customer and
     * lead are created or linked, and everything is written to the audit log.
     *
     * @param  array{
     *     unit_id: int, type: string, starts_on: string, due_on: string, event_name?: ?string,
     *     customer?: ?array{name: string, whatsapp_number: string, address?: ?string, city?: ?string, district?: ?string},
     *     consent_data?: bool, consent_whatsapp?: bool, override_reason?: ?string,
     * }  $data
     */
    public function create(array $data, User $sales, ?Carbon $at = null): Loan
    {
        $at ??= now();
        $type = LoanType::from($data['type']);
        $overrideReason = trim((string) ($data['override_reason'] ?? ''));

        return DB::transaction(function () use ($data, $sales, $at, $type, $overrideReason) {
            // Lock the unit first so two devices cannot lend it at the same time.
            $unit = Unit::query()->lockForUpdate()->findOrFail($data['unit_id']);
            $previousLoan = $unit->loans()->where('status', LoanStatus::Active)->with('customer')->latest('id')->first();

            if (! $this->unitStatus->isLoanable($unit)) {
                if ($overrideReason === '') {
                    throw new UnitNotLoanable($unit, $previousLoan);
                }

                if (! $sales->can('override', Loan::class)) {
                    throw new AuthorizationException('Hanya admin yang bisa meminjamkan unit yang belum tersedia.');
                }

                // The admin vouches that the unit is physically back: close the stale loan.
                if ($previousLoan) {
                    $previousLoan->update([
                        'status' => LoanStatus::Returned,
                        'returned_at' => $at,
                        'returned_by_id' => $sales->id,
                        'return_notes' => 'Ditutup lewat override admin: '.$overrideReason,
                    ]);
                    ActivityLog::record($previousLoan, 'loan.closed_by_override', $sales, LoanStatus::Active->value, LoanStatus::Returned->value, $overrideReason);
                }
            }

            $customer = isset($data['customer']) ? $this->upsertCustomer($data['customer'], $data, $at) : null;
            $lead = $customer ? $this->linkLead($customer, $sales, $type) : null;

            $loan = Loan::create([
                'unit_id' => $unit->id,
                'customer_id' => $customer?->id,
                'lead_id' => $lead?->id,
                'sales_id' => $sales->id,
                'type' => $type,
                'event_name' => $type === LoanType::Event ? ($data['event_name'] ?? null) : null,
                'starts_on' => $data['starts_on'],
                'due_on' => $data['due_on'],
                'status' => LoanStatus::Active,
                'override_by_id' => $overrideReason !== '' && ! $this->unitStatus->isLoanable($unit) ? $sales->id : null,
                'override_reason' => $overrideReason !== '' && ! $this->unitStatus->isLoanable($unit) ? $overrideReason : null,
            ]);

            $note = "Dipinjam {$loan->borrowerName()} ({$type->label()}).";

            if ($this->unitStatus->isLoanable($unit)) {
                $this->unitStatus->transition($unit, UnitStatus::OnLoan, $sales, $note, 'loan.created');
            } else {
                $this->unitStatus->forceTransition($unit, UnitStatus::OnLoan, $sales, $note.' Override: '.$overrideReason, 'loan.created_by_override');
            }

            ActivityLog::record($loan, 'loan.created', $sales, null, LoanStatus::Active->value, $unit->code);

            return $loan;
        });
    }

    /** One customer per WhatsApp number; the latest delivery address wins. */
    private function upsertCustomer(array $input, array $data, Carbon $at): Customer
    {
        $number = WhatsApp::normalize($input['whatsapp_number']);
        $customer = Customer::query()->where('whatsapp_number', $number)->lockForUpdate()->first()
            ?? new Customer(['whatsapp_number' => $number, 'name' => $input['name']]);

        $customer->fill([
            'address' => $input['address'] ?? $customer->address,
            'city' => $input['city'] ?? $customer->city,
            'district' => $input['district'] ?? $customer->district,
        ]);

        // Consent is only ever added here; withdrawing it is a separate, explicit action.
        if (($data['consent_data'] ?? false) && ! $customer->data_consent_at) {
            $customer->data_consent_at = $at;
        }

        if (($data['consent_whatsapp'] ?? false) && ! $customer->whatsapp_consent_at) {
            $customer->whatsapp_consent_at = $at;
        }

        $customer->save();

        return $customer;
    }

    /** Reuse the sales' open lead for this customer, otherwise start a new one. */
    private function linkLead(Customer $customer, User $sales, LoanType $type): Lead
    {
        $lead = Lead::query()
            ->where('customer_id', $customer->id)
            ->where('sales_id', $sales->id)
            ->whereIn('status', [LeadStatus::New, LeadStatus::TrialActive, LeadStatus::OfferSent])
            ->latest('id')
            ->first();

        if (! $lead) {
            $lead = Lead::create(['customer_id' => $customer->id, 'sales_id' => $sales->id, 'source' => $type->value]);
            ActivityLog::record($lead, 'lead.created', $sales, null, LeadStatus::New->value);
        }

        if ($type === LoanType::HomeTrial && $lead->status === LeadStatus::New) {
            $lead->update(['status' => LeadStatus::TrialActive]);
            ActivityLog::record($lead, 'lead.status_changed', $sales, LeadStatus::New->value, LeadStatus::TrialActive->value, 'Home trial dimulai.');
        }

        return $lead;
    }

    /** Plain-text summary for WhatsApp click-to-chat. No health claims (spec section 10). */
    public function summaryText(Loan $loan): string
    {
        $loan->loadMissing(['unit.type', 'unit.components', 'customer', 'sales']);
        $date = fn ($value) => $value->locale('id')->translatedFormat('j F Y');
        $components = $loan->unit->components->where('is_present', true)->pluck('name')->implode(', ');

        return implode("\n", array_filter([
            'Ringkasan peminjaman unit Nikkou',
            '',
            $loan->customer ? 'Nama: '.$loan->customer->name : null,
            $loan->event_name ? 'Event: '.$loan->event_name : null,
            'Jenis: '.$loan->type->label(),
            'Unit: '.$loan->unit->code.' ('.$loan->unit->type->name.')',
            $components !== '' ? 'Kelengkapan: '.$components : null,
            'Mulai: '.$date($loan->starts_on),
            'Kembali: '.$date($loan->due_on),
            'Sales: '.$loan->sales->name,
            '',
            'Mohon unit dan kelengkapannya dijaga dan dikembalikan sesuai tanggal di atas. Terima kasih.',
        ], fn ($line) => $line !== null));
    }
}
