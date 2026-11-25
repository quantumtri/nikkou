<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->isSales() && $invoice->lead->sales_id === $user->id);
    }

    /** Invoices are issued from a lead (InvoiceService checks lead ownership); there is no standalone create page. */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSales();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /** Only admin confirms payment or cancels. */
    public function changeStatus(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin();
    }
}
