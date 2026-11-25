<?php

namespace App\Enums;

enum LeadPath: string
{
    case Trial = 'trial';
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Direct => 'Beli langsung',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
