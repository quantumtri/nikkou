<?php

namespace App\Enums;

enum FollowUpChannel: string
{
    case WhatsApp = 'whatsapp';
    case Call = 'call';
    case Visit = 'visit';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Call => 'Telepon',
            self::Visit => 'Kunjungan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
