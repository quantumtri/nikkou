<?php

namespace App\Filament\Resources\Concerns;

/** Follow-up pages are for admins only (spec section 2: staf gudang and sales have no access, sales view only). */
trait AdminOnly
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
