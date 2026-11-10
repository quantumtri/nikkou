<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// `status` is intentionally not fillable: it only changes through UnitStatusService.
#[Fillable(['code', 'unit_type_id', 'received_on', 'created_by_id', 'forwarder', 'location', 'initial_notes', 'label_printed_at'])]
class Unit extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'available'];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'label_printed_at' => 'datetime',
            'status' => UnitStatus::class,
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(UnitComponent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UnitDocument::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UnitPhoto::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->where('status', LoanStatus::Active)->latestOfMany();
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /** Where the unit is now: the borrower (with the sales name) or its storage location. */
    public function currentPosition(): string
    {
        $loan = $this->status === UnitStatus::OnLoan ? $this->activeLoan : null;

        if (! $loan) {
            return $this->location;
        }

        return $loan->borrowerName().' ('.str($loan->sales->name)->before(' ').')';
    }

    /** Status text shown to users; an overdue loan replaces "Dipinjam". */
    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Lewat jatuh tempo' : $this->status->label();
    }

    public function isOverdue(): bool
    {
        return $this->status === UnitStatus::OnLoan && (bool) $this->activeLoan?->isOverdue();
    }
}
