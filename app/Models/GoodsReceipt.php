<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Status and approval fields are not fillable: they only change through ReceiptService.
#[Fillable(['received_on', 'po_number', 'supplier', 'forwarder', 'tracking_number', 'packages_expected', 'packages_received', 'from_paper'])]
class GoodsReceipt extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';

    public const HELD = 'held';

    public const AWAITING_APPROVAL = 'awaiting_approval';

    public const COMPLETED = 'completed';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::DRAFT => 'Draf',
        self::HELD => 'Ditahan',
        self::AWAITING_APPROVAL => 'Menunggu persetujuan',
        self::COMPLETED => 'Selesai',
    ];

    protected $attributes = ['status' => self::DRAFT, 'from_paper' => false];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'summary_sent_at' => 'datetime',
            'from_paper' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(GoodsReceiptCheck::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)->orderBy('position');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(GoodsReceiptFinding::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(GoodsReceiptFile::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function check(string $code): ?GoodsReceiptCheck
    {
        return $this->checks->firstWhere('code', $code);
    }
}
