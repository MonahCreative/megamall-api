<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise a Tanzanian mobile number to 255XXXXXXXXX (the format mobile money gateways expect).
     * Accepts 0712345678, 712345678, +255712345678, 255 712 345 678. Returns null if invalid.
     */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if (str_starts_with($digits, '255')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Tanzanian mobile numbers: 9 digits starting with 6 or 7
        return preg_match('/^[67]\d{8}$/', $digits) ? '255'.$digits : null;
    }

    /** 255712345678 -> 0712 345 678 */
    public static function display(string $normalized): string
    {
        $local = '0'.substr($normalized, 3);

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }
}
