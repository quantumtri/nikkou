<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case TrialActive = 'trial_active';
    case OfferSent = 'offer_sent';
    case Purchased = 'purchased';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::TrialActive => 'Trial berjalan',
            self::OfferSent => 'Tawaran dikirim',
            self::Purchased => 'Membeli',
            self::Cancelled => 'Batal',
        };
    }

    /** Badge color in the admin panel: Membeli is green. */
    public function color(): string
    {
        return match ($this) {
            self::Purchased => 'success',
            self::Cancelled => 'danger',
            self::New => 'gray',
            default => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
