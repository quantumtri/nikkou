<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || ($user->isSales() && $lead->sales_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return false;
    }
}
