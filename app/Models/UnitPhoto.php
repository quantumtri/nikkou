<?php

namespace App\Models;

use App\Enums\UnitPhotoStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['unit_id', 'loan_id', 'stage', 'file_path', 'notes'])]
class UnitPhoto extends Model
{
    protected function casts(): array
    {
        return ['stage' => UnitPhotoStage::class];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
