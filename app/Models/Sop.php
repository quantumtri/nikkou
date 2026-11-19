<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Status and the active version only change through SopService.
#[Fillable(['code', 'title', 'owner_id', 'review_on'])]
class Sop extends Model
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    /** @var array<string, string> */
    public const STATUS_LABELS = [self::DRAFT => 'Draf', self::ACTIVE => 'Aktif', self::ARCHIVED => 'Diarsipkan'];

    protected $attributes = ['status' => self::DRAFT];

    protected function casts(): array
    {
        return ['review_on' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SopVersion::class)->orderByDesc('number');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(SopVersion::class, 'active_version_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SopAssignment::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** The newest draft, if someone is working on one. */
    public function draftVersion(): ?SopVersion
    {
        return $this->versions()->where('status', SopVersion::DRAFT)->first();
    }
}
