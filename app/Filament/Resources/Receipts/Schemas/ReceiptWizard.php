<?php

namespace App\Filament\Resources\Receipts\Schemas;

use App\Filament\Pages\Labels;
use App\Filament\Resources\Receipts\Pages\EditReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\UnitType;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/** The seven-step receiving wizard (spec A18). Rules live in ReceiptService; this file only lays out the form. */
class ReceiptWizard
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                self::step1(),
                self::step2(),
                self::step3(),
                self::step4(),
                self::step5(),
                self::step6(),
                self::step7(),
            ])
                ->skippable(false)
                ->persistStepInQueryString()
                ->columnSpanFull()
                ->submitAction(new HtmlString('<button type="submit" class="fi-btn fi-color-primary fi-size-md" style="min-height:44px;padding:0 24px;border-radius:999px;background:#c0261b;color:#fff;font-weight:600">Kirim untuk persetujuan</button>')),
        ]);
    }

    private static function upload(string $name, string $label, string $dir, bool $multiple = true, bool $imagesOnly = true, bool $required = false): FileUpload
    {
        $field = FileUpload::make($name)
            ->label($label)
            ->disk('local')
            ->directory("receipts/{$dir}")
            ->visibility('private')
            ->maxSize(10240)
            ->multiple($multiple)
            ->required($required);

        $imagesOnly
            ? $field->image()
            : $field->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);

        return $multiple ? $field->maxFiles(8) : $field;
    }

    // 1. Persiapan
    private static function step1(): Step
    {
        return Step::make('Persiapan')
            ->description('Data kiriman')
            ->afterValidation(fn (Step $component, EditReceipt $livewire) => $livewire->persistStep(1, $component->getChildSchema()->getState()))
            ->schema([
                Section::make()->columns(3)->schema([
                    TextInput::make('number')->label('Nomor lembar')->disabled()->dehydrated(false)->helperText('Dibuat otomatis.'),
                    DatePicker::make('received_on')->label('Tanggal terima')->required()->native(false)->displayFormat('d/m/Y')->maxDate(today()),
                    TextInput::make('packages_expected')->label('Koli tercatat')->numeric()->minValue(0)->maxValue(999),
                    TextInput::make('po_number')->label('Nomor PO')->maxLength(100),
                    TextInput::make('supplier')->label('Pemasok')->maxLength(255),
                    TextInput::make('forwarder')->label('Kurir atau forwarder')->maxLength(255),
                    TextInput::make('tracking_number')->label('Nomor resi atau BL')->maxLength(100)->columnSpan(2),
                ]),
                Section::make('Data dari formulir kertas')
                    ->description('Pakai bila lembar diisi di kertas dulu dan diinput belakangan. Foto lembar kertas wajib diunggah.')
                    ->schema([
                        Toggle::make('from_paper')->label('Disalin dari kertas')->live(),
                        self::upload('paper_photos', 'Foto lembar kertas', 'paper')->visible(fn (Get $get) => (bool) $get('from_paper')),
                    ]),
            ]);
    }

    // 2. Kemasan luar
    private static function step2(): Step
    {
        return Step::make('Kemasan luar')
            ->description('Koli dan surat jalan')
            ->afterValidation(fn (Step $component, EditReceipt $livewire) => $livewire->persistStep(2, $component->getChildSchema()->getState()))
            ->schema([
                Section::make()->columns(2)->schema([
                    TextInput::make('packages_received')->label('Koli diterima')->numeric()->minValue(0)->maxValue(999)->required(),
                    Toggle::make('damage_noted')->label('Kerusakan dicatat di surat jalan')->helperText('Nyalakan bila ada kemasan rusak dan sudah ditulis di surat jalan.'),
                ]),
                self::upload('package_photos', 'Foto kemasan luar (sebelum dibuka)', 'packages', required: true)->helperText('Minimal 1 foto.'),
                self::upload('delivery_note_files', 'Foto surat jalan bertanda tangan', 'delivery-notes', imagesOnly: false),
            ]);
    }

    // 3. Cocokkan dokumen dan jumlah
    private static function step3(): Step
    {
        return Step::make('Cocokkan dokumen')
            ->description('Dokumen dan jumlah')
            ->afterValidation(fn (Step $component, EditReceipt $livewire) => $livewire->persistStep(3, $component->getChildSchema()->getState()))
            ->schema([
                Section::make('Pemeriksaan')->columns(2)->schema([
                    Radio::make('documents_match')->label('PO, invoice, packing list, dan fisik cocok')->options(['1' => 'Cocok', '0' => 'Tidak cocok'])->inline()->required(),
                    Radio::make('model_quantity_ok')->label('Model dan jumlah sesuai')->options(['1' => 'Sesuai', '0' => 'Tidak sesuai'])->inline()->required(),
                ]),
                Callout::make('Langkah berikutnya terkunci')
                    ->description('Ada yang tidak cocok. Catat temuan lewat tombol "Catat temuan" di tabel Temuan di bawah formulir ini. Pemeriksaan unit baru terbuka setelah semua temuan dokumen ditutup oleh pemilik atau disetujui tetap terbuka.')
                    ->warning()
                    ->visible(fn (Get $get) => $get('documents_match') === '0' || $get('model_quantity_ok') === '0'),
                Section::make('Dokumen impor')->columns(2)->schema([
                    self::upload('doc_packing_list', 'Packing list', 'import', imagesOnly: false),
                    self::upload('doc_invoice', 'Invoice', 'import', imagesOnly: false),
                    self::upload('doc_bl_awb', 'BL atau AWB', 'import', imagesOnly: false),
                    self::upload('doc_pib', 'PIB', 'import', imagesOnly: false),
                ])->description('Dokumen ini ikut dihubungkan ke tiap unit yang disimpan.'),
            ]);
    }

    // 4. Periksa tiap unit
    private static function step4(): Step
    {
        return Step::make('Periksa tiap unit')
            ->description('Satu baris per unit')
            ->afterValidation(fn (Step $component, EditReceipt $livewire) => $livewire->persistStep(4, $component->getChildSchema()->getState()))
            ->schema([
                Repeater::make('items')
                    ->label('Unit yang diterima')
                    ->addActionLabel('Tambah unit')
                    ->cloneable(false)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state) => ($state['unit_code'] ?? 'Unit baru').(filled($state['serial_number'] ?? null) ? ' | SN '.$state['serial_number'] : ''))
                    ->minItems(1)
                    ->columns(2)
                    ->schema([
                        TextInput::make('item_id')->hidden()->dehydratedWhenHidden(),
                        TextInput::make('unit_code')->label('Kode unit')->disabled()->dehydrated(false)->placeholder('Dibuat otomatis saat baris disimpan'),
                        Select::make('unit_type_id')->label('Tipe')->options(fn () => UnitType::query()->orderBy('name')->pluck('name', 'id'))->required()->live()
                            ->afterStateUpdated(fn ($set, $state) => $set('components', array_values(UnitType::find($state)?->default_components ?? []))),
                        TextInput::make('serial_number')->label('Nomor seri')->required()->maxLength(100)->placeholder('Scan atau ketik')->helperText('Alat scan USB atau Bluetooth mengisi kolom ini seperti keyboard.'),
                        Toggle::make('physical_ok')->label('Fisik baik')->default(true)->inline(false),
                        CheckboxList::make('components')
                            ->label('Kelengkapan (centang yang ada)')
                            ->options(fn (Get $get) => collect(UnitType::find($get('unit_type_id'))?->default_components ?? [])->mapWithKeys(fn ($name) => [$name => $name])->all())
                            ->columns(2)
                            ->columnSpanFull()
                            ->helperText('Daftar mengikuti tipe unit. Komponen yang tidak dicentang dianggap tidak ada.'),
                        self::upload('unit_photos', 'Foto kondisi awal (wajib)', 'units', required: true)->columnSpanFull(),
                        Textarea::make('notes')->label('Catatan kondisi')->rows(2)->maxLength(2000)->columnSpanFull(),
                    ]),
            ]);
    }

    // 5. Uji fungsi
    private static function step5(): Step
    {
        return Step::make('Uji fungsi')
            ->description('Satu baris per unit')
            ->afterValidation(fn (Step $component, EditReceipt $livewire) => $livewire->persistStep(5, $component->getChildSchema()->getState()))
            ->schema([
                Text::make(fn () => 'Penguji: '.auth()->user()->name.'. Hasil Lolos dihitung otomatis hanya bila semua centang terisi; selain itu Gagal dan alasannya wajib.'),
                Repeater::make('tests')
                    ->label('Hasil uji')
                    ->addable(false)->deletable(false)->reorderable(false)->cloneable(false)
                    ->itemLabel(fn (array $state) => $state['label'] ?? 'Unit')
                    ->columns(2)
                    ->schema([
                        TextInput::make('item_id')->hidden()->dehydratedWhenHidden(),
                        TextInput::make('label')->hidden()->dehydratedWhenHidden(),
                        Checkbox::make('powers_on')->label('Menyala'),
                        Checkbox::make('outputs')->label('Output keluar'),
                        Checkbox::make('no_leak')->label('Tidak bocor'),
                        Checkbox::make('no_abnormal')->label('Tidak ada bunyi atau panas tidak wajar'),
                        Textarea::make('fail_reason')->label('Alasan gagal (wajib bila ada yang tidak tercentang)')->rows(2)->columnSpanFull(),
                        Checkbox::make('retest')->label('Uji ulang (hanya satu kali, setelah gagal)')->visible(fn (Get $get) => $get('result') === GoodsReceiptItem::FAIL && ! $get('is_retest') && ! $get('saved')),
                        Text::make(fn (Get $get) => match ($get('result')) {
                            GoodsReceiptItem::PASS => 'Hasil tersimpan: LOLOS',
                            GoodsReceiptItem::FAIL => 'Hasil tersimpan: GAGAL',
                            default => 'Hasil belum dihitung. Tekan Lanjut untuk menyimpan.',
                        })->columnSpanFull(),
                    ]),
            ]);
    }

    // 6. Simpan unit dan label
    private static function step6(): Step
    {
        return Step::make('Simpan unit dan label')
            ->description('Buat data unit')
            ->schema([
                Text::make('Tombol "Simpan unit" membuat data unit: Tersedia bila lolos, Perlu servis bila gagal uji atau cacat, Ditahan bila terkait temuan dokumen. Setelah itu cetak label QR dan barcode, lalu scan satu kali untuk memastikan label terbaca.'),
                Actions::make([
                    Action::make('saveUnits')
                        ->label('Simpan unit')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalDescription('Unit dibuat dengan status sesuai hasil uji. Setelah disimpan, baris unit tidak bisa diubah dari sini.')
                        ->action(fn (EditReceipt $livewire) => $livewire->saveUnitsAction()),
                    Action::make('printLabels')
                        ->label('Cetak label unit')
                        ->color('gray')
                        ->visible(fn (EditReceipt $livewire) => $livewire->unitIds() !== [])
                        ->url(fn (EditReceipt $livewire) => Labels::getUrl(['units' => implode(',', $livewire->unitIds())]), shouldOpenInNewTab: true),
                ]),
                Repeater::make('labels')
                    ->label('Label unit')
                    ->addable(false)->deletable(false)->reorderable(false)->cloneable(false)
                    ->itemLabel(fn (array $state) => ($state['unit_code'] ?? '').' | '.($state['status'] ?? ''))
                    ->columns(2)
                    ->schema([
                        TextInput::make('item_id')->hidden()->dehydratedWhenHidden(),
                        TextInput::make('unit_code')->label('Kode unit')->disabled()->dehydrated(false),
                        TextInput::make('scan')->label('Scan label (satu kali)')->placeholder('Scan label yang sudah dicetak')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, EditReceipt $livewire, $state) => $livewire->confirmLabelScan((int) $get('item_id'), (string) $state))
                            ->helperText(fn (Get $get) => filled($get('read')) ? 'Label terbaca '.$get('read') : 'Belum dikonfirmasi.'),
                        TextInput::make('read')->hidden()->dehydratedWhenHidden(),
                    ]),
            ]);
    }

    // 7. Simpan dan laporkan
    private static function step7(): Step
    {
        return Step::make('Simpan dan laporkan')
            ->description('Rak, ringkasan, persetujuan')
            ->afterValidation(fn (Step $component, EditReceipt $livewire) => $livewire->persistStep(7, $component->getChildSchema()->getState()))
            ->schema([
                Repeater::make('shelves')
                    ->label('Lokasi rak tiap unit')
                    ->addable(false)->deletable(false)->reorderable(false)->cloneable(false)
                    ->itemLabel(fn (array $state) => $state['unit_code'] ?? 'Unit')
                    ->columns(2)
                    ->schema([
                        TextInput::make('item_id')->hidden()->dehydratedWhenHidden(),
                        TextInput::make('unit_code')->label('Kode unit')->disabled()->dehydrated(false),
                        TextInput::make('shelf')->label('Rak')->required()->maxLength(40)->placeholder('Misal: A1-02'),
                    ]),
                View::make('filament.receipts.summary'),
            ]);
    }
}
