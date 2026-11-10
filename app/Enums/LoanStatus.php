<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Active = 'active';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Dipinjam',
            self::Returned => 'Sudah kembali',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
