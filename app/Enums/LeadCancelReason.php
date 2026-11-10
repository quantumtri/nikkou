<?php

namespace App\Enums;

enum LeadCancelReason: string
{
    case PriceTooHigh = 'price_too_high';
    case NoBudget = 'no_budget';
    case Undecided = 'undecided';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PriceTooHigh => 'Harga tinggi',
            self::NoBudget => 'Anggaran belum ada',
            self::Undecided => 'Ragu atau tunda',
            self::Other => 'Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
