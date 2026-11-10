<?php

namespace App\Models;

use App\Enums\UnitDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

#[Fillable(['unit_id', 'type', 'file_path', 'original_name'])]
class UnitDocument extends Model
{
    protected function casts(): array
    {
        return ['type' => UnitDocumentType::class];
    }

    public function signedUrl(): string
    {
        return URL::temporarySignedRoute('files.unit-document', now()->addMinutes(15), ['document' => $this->id]);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
