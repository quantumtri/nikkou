<?php

namespace App\Filament\Resources\Receipts\Tables;

use App\Filament\Resources\Receipts\ReceiptResource;
use App\Models\GoodsReceipt;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Cari nomor, PO, atau pemasok')
            ->recordUrl(fn (GoodsReceipt $record) => ReceiptResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable()->color('primary'),
                TextColumn::make('received_on')->label('Tanggal terima')->date('j M Y')->sortable(),
                TextColumn::make('supplier')->label('Pemasok')->searchable()->description(fn (GoodsReceipt $record) => $record->po_number ? 'PO '.$record->po_number : null),
                TextColumn::make('items_count')->label('Unit'),
                TextColumn::make('findings_count')->label('Temuan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => GoodsReceipt::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        GoodsReceipt::COMPLETED => 'info',
                        GoodsReceipt::HELD => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('creator.name')->label('Penerima')->formatStateUsing(fn (string $state) => str($state)->before(' ')->toString()),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->placeholder('Semua status')->options(GoodsReceipt::STATUS_LABELS),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Lanjutkan')->visible(fn (GoodsReceipt $record) => auth()->user()->can('update', $record)),
            ])
            ->emptyStateHeading('Belum ada penerimaan')
            ->emptyStateDescription('Setiap kiriman barang masuk dicatat di sini, bukan langsung lewat tambah unit.');
    }
}
