<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sop_version_id', 'key', 'number', 'type', 'title', 'description', 'sop_role_id', 'deadline', 'related_route', 'position'])]
class SopStep extends Model
{
    public const START = 'start';

    public const STEP = 'step';

    public const DECISION = 'decision';

    public const END = 'end';

    /** @var array<string, string> */
    public const TYPE_LABELS = [self::START => 'Mulai', self::STEP => 'Langkah', self::DECISION => 'Keputusan', self::END => 'Selesai'];

    public $timestamps = false;

    public function role(): BelongsTo
    {
        return $this->belongsTo(SopRole::class, 'sop_role_id');
    }

    public function outgoing(): HasMany
    {
        return $this->hasMany(SopEdge::class, 'from_step_id');
    }

    public function incoming(): HasMany
    {
        return $this->hasMany(SopEdge::class, 'to_step_id');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(SopForm::class);
    }
}
