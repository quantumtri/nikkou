<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Sales = 'sales';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Sales => 'Sales',
            self::Warehouse => 'Staf gudang',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
