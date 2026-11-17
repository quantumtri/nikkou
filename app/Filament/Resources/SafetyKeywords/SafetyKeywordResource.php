<?php

namespace App\Filament\Resources\SafetyKeywords;

use App\Filament\Resources\Concerns\AdminOnly;
use App\Filament\Resources\SafetyKeywords\Pages\CreateSafetyKeyword;
use App\Filament\Resources\SafetyKeywords\Pages\EditSafetyKeyword;
use App\Filament\Resources\SafetyKeywords\Pages\ListSafetyKeywords;
use App\Models\SafetyKeyword;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class SafetyKeywordResource extends Resource
{
    use AdminOnly {
        canCreate as private adminOnlyCanCreate;
        canDelete as private adminOnlyCanDelete;
    }

    protected static ?string $model = SafetyKeyword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?string $navigationLabel = 'Kata kunci keluhan';

    protected static ?string $modelLabel = 'kata kunci';

    protected static ?string $pluralModelLabel = 'Kata kunci keluhan serius';

    protected static ?string $slug = 'safety-keywords';

    protected static ?int $navigationSort = 73;

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keyword')
                ->label('Kata atau frasa')
                ->required()
                ->maxLength(100)
                // Compared trimmed and case-insensitively, like it is stored.
                ->rule(fn (?SafetyKeyword $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    $taken = SafetyKeyword::query()
                        ->whereRaw('lower(keyword) = ?', [mb_strtolower(trim((string) $value))])
                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                        ->exists();

                    if ($taken) {
                        $fail('Kata kunci ini sudah ada.');
                    }
                })
                ->dehydrateStateUsing(fn (?string $state) => mb_strtolower(trim((string) $state)))
                ->helperText('Dicocokkan dari awal kata, huruf besar atau kecil sama saja. Misal "sesak" juga mengenali "sesaknya".'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('keyword')
            ->columns([
                TextColumn::make('keyword')->label('Kata atau frasa')->searchable(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([EditAction::make()->label('Ubah'), DeleteAction::make()->label('Hapus')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSafetyKeywords::route('/'),
            'create' => CreateSafetyKeyword::route('/create'),
            'edit' => EditSafetyKeyword::route('/{record}/edit'),
        ];
    }
}
