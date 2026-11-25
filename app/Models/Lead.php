<?php

namespace App\Models;

use App\Enums\LeadCancelReason;
use App\Enums\LeadPath;
use App\Enums\LeadStatus;
use App\Enums\OverallResponse;
use App\Models\Concerns\OwnedBySales;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['customer_id', 'sales_id', 'status', 'cancel_reason', 'cancel_note', 'notes', 'source', 'product_id', 'trial_started_on', 'symptom_form_filled', 'followup_count', 'overall_response', 'code', 'path'])]
class Lead extends Model
{
    use HasFactory, OwnedBySales;

    protected $attributes = ['status' => 'new', 'path' => 'trial'];

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            $lead->code ??= Sequence::code('LD', 'lead');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'path' => LeadPath::class,
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

    /** Accessories carried on trial, or put on the invoice for a direct purchase. */
    public function accessories(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'lead_accessories')->withPivot('quantity');
    }

    public function symptomForm(): HasOne
    {
        return $this->hasOne(SymptomForm::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class)->orderByDesc('followed_up_on')->orderByDesc('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
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
