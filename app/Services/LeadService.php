<?php

namespace App\Services;

use App\Enums\AgeRange;
use App\Enums\Gender;
use App\Enums\LeadCancelReason;
use App\Enums\LeadStatus;
use App\Enums\OverallResponse;
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
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'trial_started_on' => ['nullable', 'date'],
            'symptom_form_filled' => ['boolean'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'cancel_reason' => ['nullable', Rule::enum(LeadCancelReason::class), 'required_if:status,cancelled'],
            'followup_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'overall_response' => ['nullable', Rule::enum(OverallResponse::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...($withSales ? ['sales_id' => ['required', Rule::exists('users', 'id')->where('role', 'sales')->where('is_active', true)]] : []),
        ];
    }

    /** @param  array<string, mixed>  $input
     * @return array<string, mixed> */
    public function validate(array $input, bool $withSales = false): array
    {
        return Validator::make($input, self::rules($withSales), [
            'cancel_reason.required_if' => 'Alasan batal wajib diisi bila status Batal.',
        ])->validate();
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
            ]);

            ActivityLog::record($lead, 'lead.created', $by, null, $lead->status->value, 'Lead ditambah manual.');

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
            'trial_started_on' => $d['trial_started_on'] ?? null,
            'symptom_form_filled' => (bool) ($d['symptom_form_filled'] ?? false),
            'status' => $d['status'],
            'cancel_reason' => $cancelled ? $d['cancel_reason'] : null,
            'followup_count' => (int) ($d['followup_count'] ?? 0),
            'overall_response' => $d['overall_response'] ?? null,
            'notes' => $d['notes'] ?? null,
        ];
    }
}
