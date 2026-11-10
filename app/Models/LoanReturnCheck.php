<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_id', 'component', 'is_complete', 'notes'])]
class LoanReturnCheck extends Model
{
    protected function casts(): array
    {
        return ['is_complete' => 'boolean'];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
