<?php

namespace App\Enums;

enum OverallResponse: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positif',
            self::Neutral => 'Biasa',
            self::Negative => 'Negatif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Neutral => 'gray',
            self::Negative => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
