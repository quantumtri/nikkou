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

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
