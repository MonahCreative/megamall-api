<?php

namespace App\Support;

/** Every way money can come in, with the names staff see. */
class PaymentChannels
{
    /** @return array<string, string> key => label, cash first */
    public static function all(): array
    {
        return ['cash' => 'Cash', ...config('payments.channels'), 'card' => 'Card', 'bank' => 'Bank transfer'];
    }

    public static function label(?string $key): string
    {
        return static::all()[$key] ?? ($key ? ucfirst($key) : 'Unknown');
    }
}
