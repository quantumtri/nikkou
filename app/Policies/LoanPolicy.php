<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    public function view(User $user, Loan $loan): bool
    {
        return $user->isAdmin() || ($user->isSales() && $loan->sales_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    /** Lending a unit that is not available requires an admin and a reason. */
    public function override(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Returns: admin and warehouse for any loan, sales for their own loans. */
    public function processReturn(User $user, Loan $loan): bool
    {
        if (! $loan->isActive()) {
            return false;
        }

        return $user->isAdmin() || $user->isWarehouse() || ($user->isSales() && $loan->sales_id === $user->id);
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Loan $loan): bool
    {
        return false;
    }
}
