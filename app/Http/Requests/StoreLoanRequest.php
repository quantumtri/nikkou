<?php

namespace App\Http\Requests;

use App\Enums\LoanType;
use App\Models\Loan;
use App\Support\WhatsApp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Loan::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_code' => strtoupper(trim((string) $this->input('unit_code'))),
            'whatsapp_number' => $this->filled('whatsapp_number') ? WhatsApp::normalize($this->input('whatsapp_number')) : null,
        ]);
    }

    public function rules(): array
    {
        $homeTrial = $this->input('type') === LoanType::HomeTrial->value;
        // Event loans may have no named customer; any customer field makes name and number required.
        $hasCustomer = $homeTrial || $this->filled('customer_name') || $this->filled('whatsapp_number');

        return [
            'type' => ['required', Rule::enum(LoanType::class)],
            'unit_code' => ['required', 'string', 'max:40', Rule::exists('units', 'code')],
            'customer_name' => [Rule::requiredIf($hasCustomer), 'nullable', 'string', 'max:255'],
            'whatsapp_number' => [Rule::requiredIf($hasCustomer), 'nullable', 'string', 'regex:/^62\d{8,13}$/'],
            'address' => [Rule::requiredIf($homeTrial), 'nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            // A loan starts on handover; future bookings are reservations (Phase 5).
            'starts_on' => ['required', 'date', 'before_or_equal:today'],
            'due_on' => ['required', 'date', 'after_or_equal:starts_on', 'after_or_equal:today'],
            'event_name' => [Rule::requiredIf(! $homeTrial), 'nullable', 'string', 'max:255'],
            'consent_data' => [Rule::when($hasCustomer, ['accepted'], ['nullable'])],
            'consent_whatsapp' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'jenis peminjaman',
            'unit_code' => 'kode unit',
            'customer_name' => 'nama',
            'whatsapp_number' => 'nomor WhatsApp',
            'address' => 'alamat',
            'city' => 'kota',
            'district' => 'domisili',
            'starts_on' => 'tanggal mulai',
            'due_on' => 'tanggal kembali',
            'event_name' => 'nama event',
            'override_reason' => 'alasan override',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_code.exists' => 'Kode unit tidak ditemukan.',
            'whatsapp_number.regex' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.',
            'consent_data.accepted' => 'Persetujuan pemakaian data wajib dicentang.',
            'starts_on.before_or_equal' => 'Tanggal mulai tidak boleh setelah hari ini.',
            'due_on.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal mulai atau hari ini.',
        ];
    }
}
