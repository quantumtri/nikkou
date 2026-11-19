<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['goods_receipt_id', 'goods_receipt_item_id', 'type', 'description', 'action', 'due_on', 'responsible_id'])]
class GoodsReceiptFinding extends Model
{
    public const OPEN = 'open';

    public const SUBMITTED = 'submitted';

    public const CLOSED = 'closed';

    /** type => [label, default days to resolve (spec: same day to 2 working days), blocks the unit check] */
    public const TYPES = [
        'damaged_packaging' => ['Kemasan rusak', 1, false],
        'quantity_model_mismatch' => ['Jumlah atau model tidak sesuai', 0, true],
        'accessories_missing' => ['Aksesori kurang', 2, false],
        'unit_defect' => ['Unit cacat', 2, false],
        'failed_test' => ['Gagal uji', 2, false],
        'documents_incomplete' => ['Dokumen tidak lengkap', 1, true],
    ];

    public const ACTIONS = [
        'hold' => 'Tahan',
        'request_followup' => 'Minta susulan',
        'needs_service' => 'Status Perlu servis',
        'claim' => 'Klaim',
    ];

    public const STATUS_LABELS = [self::OPEN => 'Terbuka', self::SUBMITTED => 'Diajukan', self::CLOSED => 'Ditutup'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'is_blocking' => 'boolean',
            'closed_at' => 'datetime',
            'waived_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type][0] ?? $type;
    }

    /** Resolved for the receipt: closed, or the owner agreed it may stay open. */
    public function isResolved(): bool
    {
        return $this->status === self::CLOSED || $this->waived_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isResolved() && $this->due_on !== null && $this->due_on->lt(today());
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(GoodsReceiptFile::class);
    }
}
