<?php

namespace App\Filament\Resources\Sops;

use App\Filament\Resources\Sops\Pages\CreateSop;
use App\Filament\Resources\Sops\Pages\EditSop;
use App\Filament\Resources\Sops\Pages\ListSops;
use App\Filament\Resources\Sops\Pages\ViewSop;
use App\Filament\Resources\Sops\Tables\SopsTable;
use App\Models\Sop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** SOP library (spec A19). Admin manages; warehouse staff read the active SOPs assigned to them. */
class SopResource extends Resource
{
    protected static ?string $model = Sop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'SOP';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $modelLabel = 'SOP';

    protected static ?string $pluralModelLabel = 'SOP';

    protected static ?string $slug = 'sops';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 50;

    public static function table(Table $table): Table
    {
        return SopsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['owner:id,name', 'activeVersion.forms', 'activeVersion.acknowledgements']);
        $user = auth()->user();

        // Non-admins only see active SOPs assigned to them.
        if ($user && ! $user->isAdmin()) {
            $query->where('status', Sop::ACTIVE)->whereHas('assignments', fn (Builder $q) => $q
                ->where('user_id', $user->id)->orWhere('role', $user->role->value));
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', Sop::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Sop::class) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSops::route('/'),
            'create' => CreateSop::route('/create'),
            'view' => ViewSop::route('/{record}'),
            'edit' => EditSop::route('/{record}/edit'),
        ];
    }
}
