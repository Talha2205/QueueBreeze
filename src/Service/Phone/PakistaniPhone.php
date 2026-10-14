<?php

namespace App\Service\Phone;

final class PakistaniPhone
{
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', $input) ?? '';

        if (str_starts_with($digits, '+92')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return 1 === preg_match('/^3\d{9}$/', $digits) ? '+92'.$digits : null;
    }

    public static function mask(string $e164): string
    {
        $national = substr($e164, -10);
        $masked = $national[0].str_repeat('X', 6).substr($national, -3);

        return '+92 '.substr($masked, 0, 3).' '.substr($masked, 3, 3).' '.substr($masked, 6);
    }

    public static function format(string $e164): string
    {
        $national = substr($e164, -10);

        return '+92 '.substr($national, 0, 3).' '.substr($national, 3, 3).' '.substr($national, 6);
    }
}
