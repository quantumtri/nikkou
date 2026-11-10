<?php

namespace App\Enums;

enum LoanType: string
{
    case HomeTrial = 'home_trial';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::HomeTrial => 'Home trial',
            self::Event => 'Event / showcase',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
