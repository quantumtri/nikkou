<?php

namespace App\Models;

use App\Enums\LeadCancelReason;
use App\Enums\LeadStatus;
use App\Models\Concerns\OwnedBySales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'sales_id', 'status', 'cancel_reason', 'cancel_note', 'notes', 'source'])]
class Lead extends Model
{
    use HasFactory, OwnedBySales;

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'cancel_reason' => LeadCancelReason::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
