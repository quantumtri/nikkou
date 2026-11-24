<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Main = 'main';
    case Accessory = 'accessory';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Produk utama',
            self::Accessory => 'Aksesoris',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
