<?php

namespace App\Http\Requests;

use App\Enums\UnitStatus;
use App\Models\Loan;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReturnRequest extends FormRequest
{
    public ?Loan $loan = null;

    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value, ['admin', 'sales', 'warehouse'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['unit_code' => strtoupper(trim((string) $this->input('unit_code')))]);
    }

    public function rules(): array
    {
        return [
            'unit_code' => ['required', 'string', 'max:40', Rule::exists('units', 'code')],
            'checks' => ['required', 'array', 'min:1'],
            'checks.*.component' => ['required', 'string', 'max:255'],
            'checks.*.is_complete' => ['required', 'boolean'],
            'photos' => ['required', 'array', 'min:1', 'max:6'],
            'photos.*' => ['image', 'max:8192'],
            'has_damage' => ['boolean'],
            'notes' => [Rule::requiredIf($this->boolean('has_damage')), 'nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([UnitStatus::Available->value, UnitStatus::NeedsService->value])],
        ];
    }

    /** Check that the scanned unit has an active loan this user may return, and the checklist matches the unit. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $unit = Unit::query()->with(['components', 'activeLoan'])->where('code', $this->input('unit_code'))->first();
            $loan = $unit?->activeLoan;

            if (! $loan) {
                $validator->errors()->add('unit_code', "{$unit->code} tidak sedang dipinjam, jadi tidak ada yang perlu dikembalikan.");

                return;
            }

            if (! $this->user()->can('processReturn', $loan)) {
                $validator->errors()->add('unit_code', 'Unit ini dipinjam sales lain. Hanya peminjam, admin, atau staf gudang yang bisa mencatat pengembaliannya.');

                return;
            }

            $expected = $unit->components->where('is_present', true)->pluck('name')->sort()->values()->all();
            $given = collect($this->input('checks'))->pluck('component')->sort()->values()->all();

            if ($expected !== $given) {
                $validator->errors()->add('checks', 'Daftar kelengkapan tidak sesuai dengan unit ini. Muat ulang halaman dan coba lagi.');

                return;
            }

            $this->loan = $loan;
        }];
    }

    public function attributes(): array
    {
        return [
            'unit_code' => 'kode unit',
            'photos' => 'foto kondisi',
            'photos.*' => 'foto kondisi',
            'notes' => 'catatan kerusakan',
            'status' => 'status setelah kembali',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_code.exists' => 'Kode unit tidak ditemukan.',
            'photos.required' => 'Minimal satu foto kondisi unit.',
            'photos.min' => 'Minimal satu foto kondisi unit.',
            'photos.max' => 'Maksimal 6 foto.',
            'photos.*.image' => 'Berkas harus berupa foto.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 8 MB.',
            'notes.required' => 'Jelaskan kerusakannya di catatan.',
            'checks.required' => 'Cek kelengkapan unit lebih dulu.',
        ];
    }
}
