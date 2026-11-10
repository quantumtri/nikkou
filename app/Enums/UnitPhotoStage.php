<?php

namespace App\Enums;

enum UnitPhotoStage: string
{
    case Initial = 'initial';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Kondisi awal',
            self::Return => 'Saat kembali',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
