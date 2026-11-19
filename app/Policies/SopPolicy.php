<?php

namespace App\Policies;

use App\Models\Sop;
use App\Models\User;
use App\Services\SopService;

class SopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    /** Admin sees everything; others only active SOPs assigned to them. */
    public function view(User $user, Sop $sop): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $sop->status === Sop::ACTIVE && app(SopService::class)->isAssigned($sop, $user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Sop $sop): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, Sop $sop): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Sop $sop): bool
    {
        return false;
    }
}
