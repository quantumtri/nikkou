<?php

namespace App\Support;

class WhatsApp
{
    /** Normalize Indonesian numbers to international digits: 0812... / +62 812... => 62812... */
    public static function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /** Click-to-chat link; without a number WhatsApp lets the user pick the chat. */
    public static function chatUrl(?string $number, string $text): string
    {
        return 'https://wa.me/'.(self::normalize($number) ?? '').'?text='.rawurlencode($text);
    }
}
