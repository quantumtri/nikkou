<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group', 'step_key', 'title', 'body', 'requires_review', 'is_active', 'approved_at', 'approved_by_id'])]
class FollowupTemplate extends Model
{
    public const GROUP_BORROWER = 'borrower';

    public const GROUP_SYSTEM = 'system';

    public const SAFETY_REPLY = 'safety_reply';

    public const STOP_CONFIRM = 'stop_confirm';

    /** Words that must never appear in customer messages: the device is not a medicine (spec section 10). */
    public const FORBIDDEN_CLAIMS = ['sembuh', 'menyembuhkan', 'mengobati', 'pengobatan', 'obat', 'mencegah', 'terapi', 'penyakit'];

    protected function casts(): array
    {
        return [
            'requires_review' => 'boolean',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** Usable for sending: switched on, and approved if it needs review. */
    public function isSendable(): bool
    {
        return $this->is_active && (! $this->requires_review || $this->approved_at !== null);
    }

    /** @return list<string> forbidden words found in the text */
    public static function forbiddenClaimsIn(string $text): array
    {
        $found = [];

        foreach (self::FORBIDDEN_CLAIMS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/iu', $text)) {
                $found[] = $word;
            }
        }

        return $found;
    }

    public static function systemText(string $stepKey): ?self
    {
        return self::query()->where('group', self::GROUP_SYSTEM)->where('step_key', $stepKey)->first();
    }
}
