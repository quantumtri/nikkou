<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\LeadPath;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\SymptomForm;
use App\Models\User;
use App\Support\HealthSurvey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The initial symptom form (printed pages 1-2): data diri, consent, sales block and health survey. One per lead. */
class SymptomFormService
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'whatsapp_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s\-]{8,20}$/'],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'consent_data' => ['boolean'],
            'consent_whatsapp' => ['boolean'],
            'filled_on' => ['required', 'date', 'before_or_equal:today'],
            'activity_on' => ['nullable', 'date'],
            'session_location' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', Rule::exists('units', 'id')],
            'cup_serial' => ['nullable', 'string', 'max:100'],
            'machine_taken_on' => ['nullable', 'date'],
            'conditions' => ['nullable', 'array'],
            'other_conditions' => ['nullable', 'array'],
            'cancer_type' => ['nullable', 'string', 'max:255'],
            'cancer_treatment' => ['nullable', 'string', 'max:255'],
            'sales_notes' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (HealthSurvey::GROUPS as $key => [, $items]) {
            $rules["conditions.$key"] = ['nullable', 'array'];
            $rules["conditions.$key.*"] = ['string', Rule::in(array_keys($items))];
            $rules["other_conditions.$key"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /** @param  array<string, mixed>  $input
     * @return array<string, mixed> */
    public function validate(array $input, Lead $lead): array
    {
        return Validator::make($input, self::rules(), [
            'consent_data.accepted' => 'Persetujuan pemakaian data wajib diberikan.',
        ])->after(function ($validator) use ($input, $lead) {
            if (! ($input['consent_data'] ?? false) && ! $lead->customer->data_consent_at) {
                $validator->errors()->add('consent_data', 'Persetujuan pemakaian data wajib dicentang sebelum form disimpan.');
            }
            foreach (array_diff(array_keys((array) ($input['conditions'] ?? [])), array_keys(HealthSurvey::GROUPS)) as $unknown) {
                $validator->errors()->add('conditions', "Kelompok kondisi \"$unknown\" tidak dikenal.");
            }
            $tumor = $input['conditions']['tumor'] ?? [];
            if (in_array('none', $tumor, true) && in_array('had_cancer', $tumor, true)) {
                $validator->errors()->add('conditions.tumor', '"Tidak ada" dan "Pernah menderita kanker" tidak bisa dipilih bersamaan.');
            }
        })->validate();
    }

    /** @param  array<string, mixed>  $data  Validated by validate(). */
    public function save(Lead $lead, array $data, User $by): SymptomForm
    {
        if (! $by->isAdmin() && ! ($by->isSales() && $lead->sales_id === $by->id)) {
            throw ValidationException::withMessages(['name' => 'Anda tidak boleh mengisi form gejala untuk lead ini.']);
        }

        return DB::transaction(function () use ($lead, $data, $by) {
            $customer = $lead->customer;
            $customer->fill([
                'name' => $data['name'],
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'whatsapp_number' => ($data['whatsapp_number'] ?? '') !== '' ? preg_replace('/[^0-9+]/', '', $data['whatsapp_number']) : null,
                'referred_by' => $data['referred_by'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'],
            ]);
            // Consent is only ever added here, never withdrawn by an edit.
            if (($data['consent_data'] ?? false) && ! $customer->data_consent_at) {
                $customer->data_consent_at = now();
            }
            if (($data['consent_whatsapp'] ?? false) && ! $customer->whatsapp_consent_at) {
                $customer->whatsapp_consent_at = now();
            }
            $customer->save();

            $conditions = collect($data['conditions'] ?? [])->map(fn ($items) => array_values($items))->filter()->all();
            $hasCancer = in_array('had_cancer', $conditions['tumor'] ?? [], true);

            $attributes = [
                'filled_by' => $by->id,
                'filled_on' => $data['filled_on'],
                'activity_on' => $data['activity_on'] ?? null,
                'session_location' => $data['session_location'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'cup_serial' => $data['cup_serial'] ?? null,
                'machine_taken_on' => $data['machine_taken_on'] ?? null,
                'conditions' => $conditions,
                'other_conditions' => array_filter($data['other_conditions'] ?? [], fn ($v) => filled($v)),
                'cancer_type' => $hasCancer ? ($data['cancer_type'] ?? null) : null,
                'cancer_treatment' => $hasCancer ? ($data['cancer_treatment'] ?? null) : null,
                'sales_notes' => $data['sales_notes'] ?? null,
            ];

            $form = $lead->symptomForm()->first();
            $created = $form === null;
            $form = $created ? $lead->symptomForm()->create($attributes) : tap($form)->update($attributes);

            // The form is the proof: the lead's "sudah isi form gejala" flag follows it. A trial with no start date
            // takes the machine pickup date from the form.
            $lead->symptom_form_filled = true;
            if ($lead->path === LeadPath::Trial && ! $lead->trial_started_on && ! empty($data['machine_taken_on'])) {
                $lead->trial_started_on = $data['machine_taken_on'];
            }
            $lead->save();

            ActivityLog::record($lead, 'lead.symptom_form_saved', $by, null, null, ($created ? 'Dibuat' : 'Diubah').' '.$form->code.'.');

            return $form;
        });
    }
}
