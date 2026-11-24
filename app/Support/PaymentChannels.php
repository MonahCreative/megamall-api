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

    /** @return array<string, string> ways money is given back to a customer */
    public static function refundMethods(): array
    {
        return ['cash' => 'Cash', 'bank' => 'Bank transfer', ...config('payments.channels')];
    }

    public static function label(?string $key): string
    {
        return static::all()[$key] ?? ($key ? ucfirst($key) : 'Unknown');
    }
}
