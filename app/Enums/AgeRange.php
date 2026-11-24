<?php

namespace App\Enums;

enum AgeRange: string
{
    case Under20 = '<20';
    case From20 = '20-29';
    case From30 = '30-39';
    case From40 = '40-49';
    case From50 = '50-59';
    case Over60 = '60+';

    public function label(): string
    {
        return match ($this) {
            self::Under20 => 'Di bawah 20 tahun',
            self::From20 => '20 sampai 29 tahun',
            self::From30 => '30 sampai 39 tahun',
            self::From40 => '40 sampai 49 tahun',
            self::From50 => '50 sampai 59 tahun',
            self::Over60 => '60 tahun ke atas',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
