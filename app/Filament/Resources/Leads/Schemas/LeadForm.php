<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\AgeRange;
use App\Enums\Gender;
use App\Enums\LeadCancelReason;
use App\Enums\LeadPath;
use App\Enums\LeadStatus;
use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Provinces;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Calon pelanggan')->columns(2)->schema([
                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                TextInput::make('whatsapp_number')->label('WhatsApp (opsional)')->maxLength(20)->tel()->placeholder('08xx xxxx xxxx'),
                TextInput::make('city')->label('Kota')->required()->maxLength(100),
                Select::make('province')->label('Provinsi')->options(Provinces::options())->searchable()->required(),
                Radio::make('gender')->label('Gender')->options(Gender::options())->inline()->required(),
                Select::make('age_range')->label('Range usia')->options(AgeRange::options())->required(),
            ]),

            Section::make('Produk')->columns(2)->schema([
                Radio::make('path')->label('Jalur')->options(LeadPath::options())->default(LeadPath::Trial->value)->inline()->required()->live()
                    ->disabled(fn (string $operation) => $operation === 'edit')->dehydrated()
                    ->helperText('Trial: dicoba dulu. Beli langsung: invoice dibuat otomatis.')->columnSpanFull(),
                Select::make('product_id')->label(fn ($get) => $get('path') === LeadPath::Direct->value ? 'Produk yang dibeli' : 'Mencoba produk apa')
                    ->options(fn () => Product::active()->ofCategory(ProductCategory::Main)->orderBy('name')->pluck('name', 'id'))->searchable()->required()
                    ->helperText('Hanya produk utama. Aksesoris dipilih di bawah.'),
                DatePicker::make('trial_started_on')->label('Mulai coba tanggal')->visible(fn ($get) => $get('path') !== LeadPath::Direct->value),
                Repeater::make('accessories')->label(fn ($get) => $get('path') === LeadPath::Direct->value ? 'Aksesoris (masuk invoice)' : 'Aksesoris (dibawa saat trial)')
                    ->columnSpanFull()->addActionLabel('Tambah aksesoris')->defaultItems(0)->columns(3)
                    ->schema([
                        Select::make('product_id')->label('Aksesoris')->required()->columnSpan(2)
                            ->options(fn () => Product::active()->ofCategory(ProductCategory::Accessory)->orderBy('name')->pluck('name', 'id')),
                        TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    ]),
                Toggle::make('symptom_form_filled')->label('Sudah isi form gejala awal?')
                    ->helperText('Nanti tersambung ke form gejala. Untuk sementara diisi manual.'),
            ]),

            Section::make('Status')->columns(2)->schema([
                Select::make('status')->label('Status lead')->options(LeadStatus::options())->default(LeadStatus::New->value)->required()->selectablePlaceholder(false)->live(),
                Select::make('cancel_reason')->label('Alasan batal')->options(LeadCancelReason::options())
                    ->visible(fn ($get) => $get('status') === LeadStatus::Cancelled->value)
                    ->required(fn ($get) => $get('status') === LeadStatus::Cancelled->value),
                Select::make('sales_id')->label('Sales')->required()
                    ->options(fn () => User::where('role', 'sales')->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                Textarea::make('notes')->label('Catatan')->rows(2)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }
}
