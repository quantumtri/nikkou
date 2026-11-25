<?php

namespace App\Services;

use App\Enums\AgeRange;
use App\Enums\Gender;
use App\Enums\LeadCancelReason;
use App\Enums\LeadPath;
use App\Enums\LeadStatus;
use App\Enums\ProductCategory;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Manual lead entry and updates (lead profile: person, trial, status, response). */
class LeadService
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(bool $withSales = false): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{8,20}$/'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'age_range' => ['required', Rule::enum(AgeRange::class)],
            'path' => ['required', Rule::enum(LeadPath::class)],
            // The product tried or bought is always a main product; accessories are listed separately.
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)->where('category', ProductCategory::Main->value)],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'accessories' => ['nullable', 'array', 'max:20'],
            'accessories.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('is_active', true)->where('category', ProductCategory::Accessory->value)],
            'accessories.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'trial_started_on' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'cancel_reason' => ['nullable', Rule::enum(LeadCancelReason::class), 'required_if:status,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...($withSales ? ['sales_id' => ['required', Rule::exists('users', 'id')->where('role', 'sales')->where('is_active', true)]] : []),
        ];
    }

    /** @param  array<string, mixed>  $input
     * @return array<string, mixed> */
    public function validate(array $input, bool $withSales = false, bool $creating = true): array
    {
        return Validator::make($input, self::rules($withSales), [
            'cancel_reason.required_if' => 'Alasan batal wajib diisi bila status Batal.',
            'product_id.exists' => 'Pilih produk utama. Aksesoris diisi di bagian Aksesoris.',
        ])->after(function ($validator) use ($input, $creating) {
            // A purchase must name the inventory unit (ID) being sold.
            if (! $creating || ($input['path'] ?? null) !== LeadPath::Direct->value || ! $validator->errors()->isEmpty()) {
                return;
            }
            $problems = InvoiceService::itemProblems([['product_id' => $input['product_id'], 'unit_id' => $input['unit_id'] ?? null, 'quantity' => 1]]);
            foreach ($problems as $message) {
                $validator->errors()->add('unit_id', $message);
            }
        })->validate();
    }

    /** @param  array<string, mixed>  $data  Validated by validate(). */
    public function create(array $data, User $by, ?User $sales = null): Lead
    {
        $owner = $sales ?? $by;
        if (! $owner->isSales() && ! $owner->isAdmin()) {
            throw ValidationException::withMessages(['sales_id' => 'Lead harus dimiliki sales.']);
        }

        return DB::transaction(function () use ($data, $by, $owner) {
            $customer = Customer::create($this->customerData($data));
            $lead = Lead::create($this->leadData($data) + [
                'customer_id' => $customer->id,
                'sales_id' => $owner->id,
                'source' => 'manual',
                'path' => $data['path'],
            ]);
            $this->syncAccessories($lead, $data['accessories'] ?? []);

            ActivityLog::record($lead, 'lead.created', $by, null, $lead->status->value, 'Lead ditambah manual ('.$lead->path->label().').');

            // A direct purchase skips the trial: the invoice is issued right away.
            if ($lead->path === LeadPath::Direct) {
                app(InvoiceService::class)->createForLead($lead, $this->invoiceLines($lead, $data['unit_id']), $by);
            }

            return $lead->refresh();
        });
    }

    /** @param  array<string, mixed>  $data  Validated by validate(). */
    public function update(Lead $lead, array $data, User $by): Lead
    {
        return DB::transaction(function () use ($lead, $data, $by) {
            $lead->refresh();
            $from = $lead->status;

            $lead->customer->update($this->customerData($data));
            $lead->update($this->leadData($data));
            $this->syncAccessories($lead, $data['accessories'] ?? []);

            if ($from !== $lead->status) {
                ActivityLog::record($lead, 'lead.status_changed', $by, $from->value, $lead->status->value, $lead->cancel_reason?->label());
            }

            return $lead->refresh();
        });
    }

    /** @param  array<string, mixed>  $d
     * @return array<string, mixed> */
    private function customerData(array $d): array
    {
        return [
            'name' => $d['name'],
            'whatsapp_number' => ($d['whatsapp_number'] ?? '') !== '' ? preg_replace('/[^0-9+]/', '', $d['whatsapp_number']) : null,
            'city' => $d['city'],
            'province' => $d['province'],
            'gender' => $d['gender'],
            'age_range' => $d['age_range'],
        ];
    }

    /** @param  array<string, mixed>  $d
     * @return array<string, mixed> */
    private function leadData(array $d): array
    {
        $cancelled = $d['status'] === LeadStatus::Cancelled->value;

        return [
            'product_id' => Product::findOrFail($d['product_id'])->id,
            // A direct purchase has no trial. The path itself is fixed once the lead exists.
            'trial_started_on' => ($d['path'] ?? 'trial') === LeadPath::Direct->value ? null : ($d['trial_started_on'] ?? null),
            'status' => $d['status'],
            'cancel_reason' => $cancelled ? $d['cancel_reason'] : null,
            'notes' => $d['notes'] ?? null,
        ];
    }

    /** @param  array<int, array{product_id: int|string, quantity: int|string}>  $rows */
    private function syncAccessories(Lead $lead, array $rows): void
    {
        $lead->accessories()->sync(collect($rows)->mapWithKeys(
            fn ($r) => [(int) $r['product_id'] => ['quantity' => (int) $r['quantity']]]
        )->all());
    }

    /** Main product plus accessories, priced from the catalog (the invoice keeps its own copy). @return array<int, array<string, int>> */
    private function invoiceLines(Lead $lead, int|string $unitId): array
    {
        $lead->load('accessories');

        return collect([['product_id' => $lead->product_id, 'quantity' => 1, 'unit_id' => (int) $unitId]])
            ->merge($lead->accessories->map(fn (Product $p) => ['product_id' => $p->id, 'quantity' => $p->pivot->quantity]))
            ->map(fn ($l) => $l + ['unit_price' => Product::findOrFail($l['product_id'])->price])
            ->all();
    }
}
