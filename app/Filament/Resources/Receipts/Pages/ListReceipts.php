<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceipts extends ListRecords
{
    protected static string $resource = ReceiptResource::class;

    protected static ?string $title = 'Penerimaan barang';

    protected ?string $subheading = 'Setiap kedatangan barang diperiksa dan dicatat di sini sebelum unit masuk inventori.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('blank')
                ->label('Cetak formulir kosong')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('receipts.blank'), shouldOpenInNewTab: true),
            CreateAction::make()->label('Terima kiriman baru'),
        ];
    }
}
