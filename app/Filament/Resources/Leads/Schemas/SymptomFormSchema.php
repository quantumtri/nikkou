<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\Gender;
use App\Models\Lead;
use App\Models\Unit;
use App\Support\HealthSurvey;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/** Form gejala awal as a slide-over: printed page 1 (consultation) and page 2 (health survey). */
class SymptomFormSchema
{
    /** @return array<int, mixed> */
    public static function components(): array
    {
        $groups = [];
        foreach (HealthSurvey::GROUPS as $key => [$label, $items]) {
            $fields = [CheckboxList::make("conditions.$key")->hiddenLabel()->options($items)->columns(2)->gridDirection('row')];
            if (HealthSurvey::hasOther($key)) {
                $fields[] = TextInput::make("other_conditions.$key")->label('Lainnya')->maxLength(255);
            }
            if ($key === 'tumor') {
                $has = fn ($get) => in_array('had_cancer', $get('conditions.tumor') ?? [], true);
                $fields[] = TextInput::make('cancer_type')->label('Jenis kanker')->maxLength(255)->visible($has);
                $fields[] = TextInput::make('cancer_treatment')->label('Status pengobatan')->maxLength(255)->visible($has);
            }
            $groups[] = Section::make($label)->schema($fields)->columnSpan($key === 'tumor' ? 2 : 1)->compact();
        }

        return [
            Section::make('Diisi oleh tim sales')->columns(2)->schema([
                DatePicker::make('activity_on')->label('Tanggal kegiatan'),
                TextInput::make('session_location')->label('Sesi / lokasi')->maxLength(255),
                Select::make('unit_id')->label('No. seri mesin (ID inventori)')->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Unit::where('code', 'like', '%'.strtoupper($search).'%')->orderBy('code')->limit(30)->pluck('code', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => Unit::find($value)?->code),
                TextInput::make('cup_serial')->label('No. seri gelas air')->maxLength(100),
                DatePicker::make('machine_taken_on')->label('Tgl. mesin dibawa pulang'),
                DatePicker::make('filled_on')->label('Tanggal form')->default(today())->maxDate(today())->required(),
            ]),
            Section::make('Data diri')->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                Radio::make('gender')->label('Jenis kelamin')->options(Gender::options())->inline()->required(),
                DatePicker::make('birth_date')->label('Tanggal lahir')->maxDate(today()->subDay()),
                TextInput::make('whatsapp_number')->label('No. HP / WhatsApp')->tel()->maxLength(20),
                TextInput::make('referred_by')->label('Direkomendasikan oleh')->maxLength(255),
                TextInput::make('email')->label('Email')->email()->maxLength(255),
                TextInput::make('address')->label('Alamat')->maxLength(1000),
                TextInput::make('city')->label('Kota')->required()->maxLength(100),
            ]),
            Section::make('Persetujuan data')->schema([
                Toggle::make('consent_data')->label('Setuju data diri dan data kesehatan dipakai Nikkou untuk peminjaman mesin, saran penggunaan, dan layanan purna jual')
                    ->helperText('Wajib. Persetujuan yang sudah diberikan tidak bisa dicabut lewat form ini.'),
                Toggle::make('consent_whatsapp')->label('Bersedia dihubungi lewat WhatsApp untuk tindak lanjut dan survei pengalaman (dapat berhenti kapan saja)'),
            ]),
            Section::make('Survei kondisi kesehatan')->description('Centang kondisi peserta saat ini.')->columns(2)->schema($groups),
            Section::make('Catatan')->schema([
                Textarea::make('sales_notes')->label('Catatan sales')->rows(3)->maxLength(2000),
            ]),
        ];
    }

    /** Values to prefill: the saved form, else what is already known about the customer. @return array<string, mixed> */
    public static function defaults(Lead $lead): array
    {
        $lead->loadMissing('customer', 'symptomForm');
        $c = $lead->customer;
        $f = $lead->symptomForm;

        return [
            'name' => $c->name,
            'gender' => $c->gender?->value,
            'birth_date' => $c->birth_date?->toDateString(),
            'whatsapp_number' => $c->whatsapp_number,
            'referred_by' => $c->referred_by,
            'email' => $c->email,
            'address' => $c->address,
            'city' => $c->city,
            'consent_data' => (bool) $c->data_consent_at,
            'consent_whatsapp' => (bool) $c->whatsapp_consent_at,
            'filled_on' => $f?->filled_on?->toDateString() ?? today()->toDateString(),
            'activity_on' => $f?->activity_on?->toDateString(),
            'session_location' => $f?->session_location,
            'unit_id' => $f?->unit_id,
            'cup_serial' => $f?->cup_serial,
            'machine_taken_on' => $f?->machine_taken_on?->toDateString() ?? $lead->trial_started_on?->toDateString(),
            'conditions' => $f?->conditions ?? [],
            'other_conditions' => $f?->other_conditions ?? [],
            'cancer_type' => $f?->cancer_type,
            'cancer_treatment' => $f?->cancer_treatment,
            'sales_notes' => $f?->sales_notes,
        ];
    }
}
