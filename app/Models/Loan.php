<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Concerns\OwnedBySales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'unit_id', 'customer_id', 'lead_id', 'sales_id', 'type', 'event_name', 'starts_on', 'due_on',
    'status', 'returned_at', 'returned_by_id', 'return_notes', 'override_by_id', 'override_reason',
])]
class Loan extends Model
{
    use HasFactory, OwnedBySales;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'type' => LoanType::class,
            'status' => LoanStatus::class,
            'starts_on' => 'date',
            'due_on' => 'date',
            'returned_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function returnChecks(): HasMany
    {
        return $this->hasMany(LoanReturnCheck::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UnitPhoto::class);
    }

    public function isActive(): bool
    {
        return $this->status === LoanStatus::Active;
    }

    /** Overdue = still on loan and the due date has passed (spec section 5). */
    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_on->lt(today());
    }

    /** Who or what holds the unit: customer name, or the event name for showcases. */
    public function borrowerName(): string
    {
        return $this->customer?->name ?? $this->event_name ?? '-';
    }
}
