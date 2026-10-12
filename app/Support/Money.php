<?php

namespace App\Support;

class Money
{
    /** 139000 -> "TZS 139,000" */
    public static function tzs(int|float|string|null $amount): string
    {
        return 'TZS '.number_format((float) ($amount ?? 0));
    }
}
