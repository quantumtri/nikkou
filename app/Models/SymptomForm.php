<?php

namespace App\Models;

use App\Support\HealthSurvey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'lead_id', 'filled_by', 'filled_on', 'activity_on', 'session_location', 'unit_id', 'cup_serial', 'machine_taken_on', 'conditions', 'other_conditions', 'cancer_type', 'cancer_treatment', 'sales_notes'])]
class SymptomForm extends Model
{
    protected function casts(): array
    {
        return [
            'filled_on' => 'date',
            'activity_on' => 'date',
            'machine_taken_on' => 'date',
            'conditions' => 'array',
            'other_conditions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SymptomForm $form) {
            $form->code ??= Sequence::code('FG', 'symptom_form');
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function filler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return list<string> */
    public function summaryLines(): array
    {
        return HealthSurvey::summary($this->conditions ?? [], $this->other_conditions ?? [], $this->cancer_type, $this->cancer_treatment);
    }
}
