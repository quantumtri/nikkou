<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use App\Services\ReceiptService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** Step 1 of the wizard, saved right away so the draft gets its number and can be resumed. */
class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    protected static ?string $title = 'Terima kiriman baru';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Persiapan')->description('Nomor lembar PB-YYYYMM-0001 dibuat otomatis saat disimpan.')->columns(3)->columnSpanFull()->schema([
                DatePicker::make('received_on')->label('Tanggal terima')->required()->default(today())->native(false)->displayFormat('d/m/Y')->maxDate(today()),
                TextInput::make('po_number')->label('Nomor PO')->maxLength(100),
                TextInput::make('packages_expected')->label('Koli tercatat')->numeric()->minValue(0)->maxValue(999),
                TextInput::make('supplier')->label('Pemasok')->maxLength(255),
                TextInput::make('forwarder')->label('Kurir atau forwarder')->maxLength(255),
                TextInput::make('tracking_number')->label('Nomor resi atau BL')->maxLength(100),
                Toggle::make('from_paper')->label('Disalin dari kertas')->helperText('Foto lembar kertas wajib diunggah di langkah berikutnya.'),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(ReceiptService::class)->create(auth()->user(), $data);
    }

    protected function getFormActions(): array
    {
        return [$this->getCreateFormAction()->label('Mulai penerimaan'), $this->getCancelFormAction()];
    }

    protected function getRedirectUrl(): string
    {
        return ReceiptResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Draf penerimaan dibuat.';
    }
}
