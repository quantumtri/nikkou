<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    /** Inventory list: admin and warehouse only. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    /** Sales may look up a single unit (scan result) but not browse inventory. */
    public function view(User $user, Unit $unit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    /** Units carry history and are never deleted from the app. */
    public function delete(User $user, Unit $unit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function printLabel(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }
}
