<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'goods_receipt_id', 'position', 'unit_type_id', 'serial_number', 'components', 'physical_ok', 'notes', 'shelf',
])]
class GoodsReceiptItem extends Model
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    /** The checks that must all be true for a unit to pass (spec A18 step 5). */
    public const TEST_FLAGS = ['powers_on', 'outputs', 'no_leak', 'no_abnormal'];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'is_complete' => 'boolean',
            'physical_ok' => 'boolean',
            'powers_on' => 'boolean',
            'outputs' => 'boolean',
            'no_leak' => 'boolean',
            'no_abnormal' => 'boolean',
            'is_retest' => 'boolean',
            'label_read_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(GoodsReceiptFile::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(GoodsReceiptFinding::class);
    }

    public function passed(): bool
    {
        return $this->result === self::PASS;
    }
}
