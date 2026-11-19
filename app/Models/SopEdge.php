<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sop_version_id', 'from_step_id', 'to_step_id', 'label', 'type'])]
class SopEdge extends Model
{
    public const NORMAL = 'normal';

    public const PROBLEM = 'problem';

    public const DELAYED = 'delayed';

    /** @var array<string, string> */
    public const TYPE_LABELS = [self::NORMAL => 'Normal', self::PROBLEM => 'Masalah', self::DELAYED => 'Tertunda'];

    public $timestamps = false;

    public function from(): BelongsTo
    {
        return $this->belongsTo(SopStep::class, 'from_step_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(SopStep::class, 'to_step_id');
    }
}
