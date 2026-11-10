<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Available = 'available';
    case OnLoan = 'on_loan';
    case NeedsService = 'needs_service';
    case InService = 'in_service';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::OnLoan => 'Dipinjam',
            self::NeedsService => 'Perlu servis',
            self::InService => 'Servis',
            self::Sold => 'Terjual',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
