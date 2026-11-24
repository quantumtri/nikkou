<?php

namespace App\Filament\Resources\Products;

use App\Enums\ProductCategory;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Sellable catalog: main products and accessories (admin only). */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Produk';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?string $modelLabel = 'produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?string $slug = 'products';

    protected static ?int $navigationSort = 55;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama produk')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('category')->label('Kategori')->options(ProductCategory::options())->required()->selectablePlaceholder(false),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('category')->label('Kategori')->badge()->formatStateUsing(fn (ProductCategory $state) => $state->label())
                    ->color(fn (ProductCategory $state) => $state === ProductCategory::Main ? 'primary' : 'gray'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([SelectFilter::make('category')->label('Kategori')->options(ProductCategory::options())])
            ->recordActions([EditAction::make()->label('Ubah')])
            ->headerActions([CreateAction::make()->label('Tambah produk')]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
