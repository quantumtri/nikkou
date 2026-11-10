<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'default_components'])]
class UnitType extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['default_components' => 'array'];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
