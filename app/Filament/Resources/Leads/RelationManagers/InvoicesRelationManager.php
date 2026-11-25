<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\ProductCategory;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Lead;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Support\Rupiah;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Invoices of a lead, with a shortcut to issue a new one. */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoice';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Repeater::make('items')->label('Item')->addActionLabel('Tambah item')->minItems(1)->columns(6)->columnSpanFull()
                ->schema([
                    Select::make('product_id')->label('Produk / aksesoris')->required()->searchable()->live()->columnSpan(2)
                        ->options(fn () => Product::groupedOptions())
                        ->afterStateUpdated(function ($state, $set) {
                            $set('unit_price', Product::find($state)?->price ?? 0);
                            $set('unit_id', null);
                        }),
                    Select::make('unit_id')->label('ID inventori')->searchable()->columnSpan(2)
                        ->visible(fn ($get) => Product::find($get('product_id'))?->category === ProductCategory::Main)
                        ->required(fn ($get) => Product::find($get('product_id'))?->category === ProductCategory::Main)
                        ->options(fn ($get) => InvoiceService::availableUnits(Product::find($get('product_id')))),
                    TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    TextInput::make('unit_price')->label('Harga satuan')->numeric()->minValue(0)->prefix('Rp')->required(),
                ]),
            Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')->label('No. invoice')->fontFamily('mono'),
                TextColumn::make('issued_on')->label('Tanggal')->date('j M Y'),
                TextColumn::make('total')->label('Total')->formatStateUsing(fn ($state) => Rupiah::format($state)),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
            ])
            ->recordUrl(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                CreateAction::make()->label('Buat invoice')->modalHeading('Buat invoice')
                    ->fillForm(fn (RelationManager $livewire) => ['items' => $this->defaultItems($livewire->getOwnerRecord())])
                    ->using(function (array $data, RelationManager $livewire) {
                        /** @var Lead $lead */
                        $lead = $livewire->getOwnerRecord();
                        $service = app(InvoiceService::class);
                        $data = $service->validate(['items' => array_values($data['items'] ?? []), 'notes' => $data['notes'] ?? null]);

                        return $service->createForLead($lead, $data['items'], auth()->user(), $data['notes'] ?? null);
                    })
                    ->successNotificationTitle('Invoice dibuat'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada invoice');
    }

    /** Pre-fill with the lead's main product and accessories at today's catalog price. @return array<int, array<string, mixed>> */
    private function defaultItems(Lead $lead): array
    {
        $main = $lead->product ? [['product_id' => $lead->product_id, 'quantity' => 1, 'unit_id' => null, 'unit_price' => $lead->product->price]] : [];

        return array_merge($main, $lead->accessories->map(fn (Product $p) => [
            'product_id' => $p->id, 'quantity' => $p->pivot->quantity, 'unit_price' => $p->price,
        ])->all());
    }
}
