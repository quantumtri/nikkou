<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Rupiah;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Invoice list and detail. Created from a lead; admin confirms payment. */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Invoice';

    protected static ?string $modelLabel = 'invoice';

    protected static ?string $pluralModelLabel = 'Invoice';

    protected static ?string $slug = 'invoices';

    protected static ?int $navigationSort = 33;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['lead.customer', 'lead.sales:id,name', 'items.unit']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Cari no. invoice atau nama')
            ->columns([
                TextColumn::make('code')->label('No. invoice')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('lead.customer.name')->label('Pelanggan')->searchable(),
                TextColumn::make('lead.code')->label('ID lead')->fontFamily('mono')->searchable(),
                TextColumn::make('issued_on')->label('Tanggal')->date('j M Y')->sortable(),
                TextColumn::make('total')->label('Total')->formatStateUsing(fn ($state) => Rupiah::format($state))->alignEnd()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextColumn::make('lead.sales.name')->label('Sales')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(InvoiceStatus::options())])
            ->recordActions([ViewAction::make()->label('Lihat detail')->icon('heroicon-o-eye')])
            ->recordUrl(null);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')->columns(3)->schema([
                TextEntry::make('code')->label('No. invoice')->fontFamily('mono')->copyable(),
                TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                TextEntry::make('total')->label('Total')->formatStateUsing(fn ($state) => Rupiah::format($state))->weight('bold'),
                TextEntry::make('lead.customer.name')->label('Pelanggan')
                    ->url(fn (Invoice $r) => LeadResource::getUrl('view', ['record' => $r->lead_id])),
                TextEntry::make('lead.code')->label('ID lead')->fontFamily('mono'),
                TextEntry::make('lead.sales.name')->label('Sales'),
                TextEntry::make('issued_on')->label('Tanggal terbit')->date('j F Y'),
                TextEntry::make('paid_on')->label('Tanggal lunas')->date('j F Y')->placeholder('-'),
                TextEntry::make('notes')->label('Catatan')->placeholder('-'),
            ]),
            Section::make('Item')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('name')->label('Produk / aksesoris'),
                    TextEntry::make('unit.code')->label('ID inventori')->fontFamily('mono')->placeholder('-')
                        ->url(fn (InvoiceItem $i) => $i->unit_id ? UnitResource::getUrl('view', ['record' => $i->unit_id]) : null),
                    TextEntry::make('quantity')->label('Jumlah'),
                    TextEntry::make('unit_price')->label('Harga satuan')->formatStateUsing(fn ($state) => Rupiah::format($state)),
                    TextEntry::make('subtotal')->label('Subtotal')->state(fn (InvoiceItem $i) => Rupiah::format($i->subtotal())),
                ]),
            ]),
        ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Invoice::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
