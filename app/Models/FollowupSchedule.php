<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'loan_id', 'customer_id', 'template_id', 'step_key', 'sequence', 'send_at', 'status',
    'claimed_at', 'attempts', 'sent_at', 'waha_message_id', 'text', 'error',
])]
class FollowupSchedule extends Model
{
    public const PENDING = 'pending';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const CANCELLED = 'cancelled';

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        self::PENDING => 'Menunggu',
        self::SENDING => 'Sedang dikirim',
        self::SENT => 'Terkirim',
        self::FAILED => 'Gagal',
        self::SKIPPED => 'Dilewati',
        self::CANCELLED => 'Dibatalkan',
    ];

    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FollowupTemplate::class, 'template_id');
    }
}
