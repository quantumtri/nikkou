<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

/** Goods receipts: admin and warehouse staff work on them; sales have no access (spec A18). */
class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isWarehouse();
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, GoodsReceipt $receipt): bool
    {
        return $this->viewAny($user) && in_array($receipt->status, [GoodsReceipt::DRAFT, GoodsReceipt::HELD], true);
    }

    /** Approving and closing findings is for the owner or manager (admin). */
    public function approve(User $user, GoodsReceipt $receipt): bool
    {
        return $user->isAdmin() && $receipt->status === GoodsReceipt::AWAITING_APPROVAL;
    }

    public function manageFindings(User $user, GoodsReceipt $receipt): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, GoodsReceipt $receipt): bool
    {
        return false;
    }
}
