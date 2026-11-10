<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Server-side data scoping (spec section 2): sales only ever see their own
 * rows, admins see everything, other roles see nothing.
 */
trait OwnedBySales
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isSales()) {
            return $query->where($this->qualifyColumn('sales_id'), $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
