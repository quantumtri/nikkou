<?php

namespace App\Filament\Resources\Units\Schemas;

use App\Models\Unit;
use App\Models\UnitType;
use App\Services\UnitService;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class UnitForm
{
    private const DOCUMENT_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Data unit')
                    ->columns(2)
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode produk')
                            ->required()
                            ->maxLength(40)
                            ->regex('/^[A-Za-z0-9\-]+$/')
                            // Codes are stored uppercase, so uniqueness must ignore case.
                            ->rule(fn (?Unit $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                                $taken = Unit::query()
                                    ->whereRaw('upper(code) = ?', [strtoupper(trim((string) $value))])
                                    ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($taken) {
                                    $fail('Kode produk ini sudah dipakai unit lain.');
                                }
                            })
                            ->default(fn () => app(UnitService::class)->suggestNextCode())
                            ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh berisi huruf, angka, dan tanda hubung.',
                            ]),
                        Select::make('unit_type_id')
                            ->label('Tipe')
                            ->options(fn () => UnitType::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('components', self::componentsFor($state))),
                        DatePicker::make('received_on')
                            ->label('Tanggal masuk')
                            ->required()
                            ->default(today())
                            ->maxDate(today())
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('creator_name')
                            ->label('Penginput')
                            ->default(fn () => auth()->user()->name)
                            ->formatStateUsing(fn ($record) => $record?->createdBy?->name ?? auth()->user()->name)
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('forwarder')
                            ->label('Forwarder')
                            ->placeholder('Nama forwarder')
                            ->maxLength(255),
                        TextInput::make('location')
                            ->label('Lokasi')
                            ->default('Gudang Surabaya')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Kondisi awal')
                    ->columnSpan(1)
                    ->visibleOn('create')
                    ->schema([
                        FileUpload::make('photos')
                            ->label('Foto kondisi awal')
                            ->helperText('Misal: depan, belakang, kelengkapan. Maksimal 8 MB per foto.')
                            ->image()
                            ->multiple()
                            ->maxFiles(6)
                            ->maxSize(8192)
                            ->disk('local')
                            ->directory('units/photos')
                            ->visibility('private'),
                        Textarea::make('initial_notes')
                            ->label('Catatan kondisi')
                            ->placeholder('Misal: kemasan penyok di sudut')
                            ->rows(2)
                            ->maxLength(2000),
                    ]),

                Section::make('Dokumen impor')
                    ->columns(2)
                    ->columnSpan(1)
                    ->visibleOn('create')
                    ->schema([
                        self::documentUpload('doc_packing_list', 'Packing list'),
                        self::documentUpload('doc_invoice', 'Invoice'),
                        self::documentUpload('doc_bl_awb', 'BL / AWB'),
                        self::documentUpload('doc_pib', 'PIB'),
                        self::documentUpload('doc_other', 'Dokumen lain')->multiple()->maxFiles(5)->columnSpanFull(),
                    ]),

                Section::make('Kelengkapan')
                    ->columnSpan(1)
                    ->visibleOn('create')
                    ->schema([
                        Repeater::make('components')
                            ->hiddenLabel()
                            ->default(fn () => [])
                            ->columns(3)
                            ->addActionLabel('Tambah komponen')
                            ->reorderable(false)
                            ->schema([
                                TextInput::make('name')->label('Komponen')->required()->maxLength(255)->columnSpan(2),
                                Checkbox::make('is_present')->label('Ada')->default(true)->inline(false),
                            ]),
                        Text::make('Setelah disimpan, unit berstatus Tersedia dan labelnya bisa dicetak.'),
                    ]),

                Textarea::make('initial_notes')
                    ->label('Catatan kondisi')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull()
                    ->visibleOn('edit'),
            ]);
    }

    private static function documentUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->acceptedFileTypes(self::DOCUMENT_TYPES)
            ->maxSize(10240)
            ->disk('local')
            ->directory('units/documents')
            ->visibility('private')
            ->storeFileNamesIn($name.'_names');
    }

    /** @return array<int, array{name: string, is_present: bool}> */
    private static function componentsFor(?string $unitTypeId): array
    {
        $names = UnitType::find($unitTypeId)?->default_components ?? [];

        return array_map(fn (string $name) => ['name' => $name, 'is_present' => true], $names);
    }
}
