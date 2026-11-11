<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Tables\LoansTable;
use App\Models\Loan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only admin view of all loans (spec A3). Loans are created from the
 * sales app, so this resource has no create, edit or delete pages.
 */
class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Peminjaman';

    protected static ?string $modelLabel = 'peminjaman';

    protected static ?string $pluralModelLabel = 'Peminjaman';

    protected static ?string $slug = 'loans';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return LoansTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with(['unit:id,code', 'customer:id,name', 'sales:id,name']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListLoans::route('/')];
    }
}
