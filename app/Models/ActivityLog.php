<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only audit log (spec section 5). Rows can be created but never
 * updated or deleted; PostgreSQL enforces the same rule with a trigger.
 */
#[Fillable(['user_id', 'subject_type', 'subject_id', 'action', 'from', 'to', 'notes'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only.'));
    }

    public static function record(Model $subject, string $action, ?User $user = null, ?string $from = null, ?string $to = null, ?string $notes = null): self
    {
        return static::create([
            'user_id' => $user?->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'from' => $from,
            'to' => $to,
            'notes' => $notes,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
