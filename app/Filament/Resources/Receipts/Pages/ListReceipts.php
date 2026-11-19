<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceipts extends ListRecords
{
    protected static string $resource = ReceiptResource::class;

    protected static ?string $title = 'Penerimaan barang';

    protected ?string $subheading = 'Setiap kedatangan barang diperiksa dan dicatat di sini sebelum unit masuk inventori.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Terima kiriman baru')];
    }
}
