<?php

namespace App\Models;

use App\Enums\LeadCancelReason;
use App\Enums\LeadStatus;
use App\Enums\OverallResponse;
use App\Models\Concerns\OwnedBySales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['customer_id', 'sales_id', 'status', 'cancel_reason', 'cancel_note', 'notes', 'source', 'product_id', 'trial_started_on', 'symptom_form_filled', 'followup_count', 'overall_response'])]
class Lead extends Model
{
    use HasFactory, OwnedBySales;

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'cancel_reason' => LeadCancelReason::class,
            'overall_response' => OverallResponse::class,
            'trial_started_on' => 'date',
            'symptom_form_filled' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** Trial start: the lead's own date, else the first loan's start (leads created from a loan). */
    public function trialStart(): ?Carbon
    {
        return $this->trial_started_on ?? $this->loans->min('starts_on');
    }

    /** Product name: the chosen product, else the unit type of the first loan. */
    public function productName(): ?string
    {
        return $this->product?->name ?? $this->loans->sortBy('starts_on')->first()?->unit?->type?->name;
    }
}
