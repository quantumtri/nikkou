<?php

namespace App\Enums;

enum UnitDocumentType: string
{
    case PackingList = 'packing_list';
    case Invoice = 'invoice';
    case BlAwb = 'bl_awb';
    case Pib = 'pib';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PackingList => 'Packing list',
            self::Invoice => 'Invoice',
            self::BlAwb => 'BL / AWB',
            self::Pib => 'PIB',
            self::Other => 'Lainnya',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
