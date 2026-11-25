<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Sellable catalog (main products and accessories). Separate from physical inventory units. */
#[Fillable(['name', 'category', 'price', 'is_active'])]
class Product extends Model
{
    protected function casts(): array
    {
        return ['category' => ProductCategory::class, 'is_active' => 'boolean', 'price' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfCategory(Builder $query, ProductCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    /** Options grouped by category for select inputs. @return array<string, array<int, string>> */
    public static function groupedOptions(): array
    {
        return static::active()->orderBy('name')->get()
            ->groupBy(fn (self $p) => $p->category->label())
            ->map(fn ($items) => $items->pluck('name', 'id')->all())
            ->sortKeys()->all();
    }
}
