<?php

namespace App\Policies;

use App\Models\UnitType;
use App\Models\User;

class UnitTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    public function view(User $user, UnitType $unitType): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, UnitType $unitType): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, UnitType $unitType): bool
    {
        return $user->isAdmin() && ! $unitType->units()->exists();
    }
}
