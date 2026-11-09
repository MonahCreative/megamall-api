<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How upcountry customers pay, chosen under Store settings > Mobile money orders:
 *
 *  - "lipa_namba": the customer pays to the shop's own till number(s) and types the transaction ID;
 *    staff check their mobile money messages and press "Confirm payment". No payment company needed.
 *  - "automatic": a payment gateway (AzamPay) sends a PIN prompt and confirms the payment by itself.
 *    Needs the gateway's live keys in .env (PAYMENT_DRIVER).
 */
class LipaNamba
{
    public const METHOD = 'lipa_namba';

    public const AUTOMATIC = 'automatic';

    /** Gateway name stored on payments made this way. */
    public const GATEWAY = 'lipa_namba';

    public const DEFAULT_NOTE = 'We check payments every day from 8:00 to 20:00 and confirm them within 30 minutes. You will get an SMS once your payment is confirmed.';

    public static function method(): string
    {
        return Setting::getValue('payment_method', self::METHOD) === self::AUTOMATIC ? self::AUTOMATIC : self::METHOD;
    }

    public static function enabled(): bool
    {
        return self::method() === self::METHOD;
    }

    /** @return array<int, array{channel: string, label: string, number: string, name: string}> */
    public static function accounts(): array
    {
        $channels = config('payments.channels');
        $saved = Setting::getValue('lipa_numbers', []);

        return collect(is_array($saved) ? $saved : [])
            ->filter(fn ($row) => is_array($row) && isset($channels[$row['channel'] ?? '']) && filled($row['number'] ?? null))
            ->map(fn (array $row) => [
                'channel' => $row['channel'],
                'label' => $channels[$row['channel']],
                'number' => trim((string) $row['number']),
                'name' => trim((string) ($row['name'] ?? '')),
            ])
            ->values()
            ->all();
    }

    public static function note(): string
    {
        return (string) (Setting::getValue('lipa_note') ?: self::DEFAULT_NOTE);
    }

    /** One line for SMS and invoices, e.g. "M-Pesa Lipa Namba 123456 (MEGA MALL)". */
    public static function summary(): string
    {
        return collect(self::accounts())
            ->map(fn (array $account) => "{$account['label']} Lipa Namba {$account['number']}".($account['name'] ? " ({$account['name']})" : ''))
            ->implode(' or ');
    }

    /**
     * Pull the transaction ID out of what the customer typed. Many paste the whole confirmation SMS,
     * e.g. "QJK3X7Y2AB Confirmed. Tsh32,000.00 paid to ...", so we take the first code-like word.
     */
    public static function cleanTransactionId(string $input): ?string
    {
        $input = strtoupper(trim($input));
        if (preg_match('/^[A-Z0-9.\-]{6,30}$/', $input) && preg_match('/\d/', $input)) {
            return $input;
        }

        foreach (preg_split('/[\s,:;]+/', $input, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $word = trim($word, '.()');
            if (preg_match('/^[A-Z0-9]{8,20}$/', $word) && preg_match('/[A-Z]/', $word) && preg_match('/\d/', $word)) {
                return $word;
            }
        }

        return null;
    }
}
