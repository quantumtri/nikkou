<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sop_id', 'number', 'change_summary'])]
class SopVersion extends Model
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    protected $attributes = ['status' => self::DRAFT];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'effective_from' => 'date'];
    }

    public function sop(): BelongsTo
    {
        return $this->belongsTo(Sop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(SopRole::class)->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SopStep::class)->orderBy('position');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(SopEdge::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(SopForm::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(SopAcknowledgement::class);
    }

    public function label(): string
    {
        return 'Versi '.$this->number;
    }
}
