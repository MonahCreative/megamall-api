<?php

namespace App\Services;

use App\Enums\PaymentMode;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Setting;
use App\Support\LipaNamba;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * New-order alerts to staff phones on WhatsApp, through CallMeBot.
 * Staff phones are listed under Store settings > Staff alerts, each with its own CallMeBot key.
 * A failed WhatsApp never affects the order: it is only written to the log.
 */
class StaffWhatsApp
{
    /** @return array<int, array{name: string, phone: string, apikey: string}> */
    public function recipients(): array
    {
        $saved = Setting::getValue('staff_whatsapp', []);

        return collect(is_array($saved) ? $saved : [])
            ->filter(fn ($row) => is_array($row) && Phone::normalize($row['phone'] ?? null) && filled($row['apikey'] ?? null))
            ->map(fn (array $row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'phone' => Phone::normalize($row['phone']),
                'apikey' => trim((string) $row['apikey']),
            ])
            ->unique('phone')
            ->values()
            ->all();
    }

    /** Called when an order needs staff attention. Sends once the order is saved, after the customer's page is done. */
    public function notifyOrder(Order $order): void
    {
        if ($order->isPos() || $this->recipients() === []) {
            return;
        }

        DB::afterCommit(function () use ($order) {
            $text = $this->orderText($order->fresh(['items', 'region', 'district']) ?? $order);
            defer(fn () => $this->sendToAll($text));
        });
    }

    public function orderText(Order $order): string
    {
        $order->loadMissing(['items', 'region', 'district']);
        $cod = $order->payment_mode === PaymentMode::CashOnDelivery;
        $waiting = ! $cod && ! $order->isPaid();

        $lines = [
            ($cod || $waiting ? '*New order* ' : '*Paid order* ').$order->order_number,
            $order->customer_name.($order->phone ? ' · '.Phone::display($order->phone) : ''),
            collect([$order->area, $order->district?->name, $order->region?->name])->filter()->implode(', '),
            '',
            ...$order->items->map(fn ($item) => "{$item->quantity} × {$item->product_name}".($item->variant_name ? " ({$item->variant_name})" : ''))->all(),
            '',
            '*Total: '.Money::tzs($order->total).'*'.match (true) {
                $cod => ' · pay on delivery',
                $waiting => ' · waiting for '.(LipaNamba::enabled() ? 'Lipa Namba ' : '').'payment',
                default => ' · paid, ready to send',
            },
            OrderResource::getUrl('view', ['record' => $order], panel: 'admin'),
        ];

        return implode("\n", $lines);
    }

    /** @return array<string, string|null> phone => null when sent, or the problem */
    public function sendToAll(string $text): array
    {
        $results = [];
        foreach ($this->recipients() as $recipient) {
            $results[$recipient['phone']] = $this->send($recipient, $text);
        }

        return $results;
    }

    /** @param  array{name: string, phone: string, apikey: string}  $recipient  @return string|null the problem, or null when sent */
    public function send(array $recipient, string $text): ?string
    {
        if (config('whatsapp.driver') === 'log') {
            Log::info("WhatsApp to staff {$recipient['phone']}: {$text}");

            return null;
        }

        try {
            $response = Http::timeout(config('whatsapp.timeout', 15))->get(config('whatsapp.callmebot_url'), [
                'phone' => '+'.$recipient['phone'],
                'text' => $text,
                'apikey' => $recipient['apikey'],
            ]);
        } catch (Throwable $exception) {
            Log::warning("WhatsApp to staff {$recipient['phone']} failed: {$exception->getMessage()}");

            return 'Could not reach CallMeBot. Try again in a few minutes.';
        }

        // CallMeBot answers with a short web page; a wrong key or an unactivated phone says so in it.
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())));
        if ($response->successful() && ! preg_match('/invalid|error|not (yet )?(allowed|activated|registered)|blocked/i', $body)) {
            return null;
        }

        Log::warning("WhatsApp to staff {$recipient['phone']} failed ({$response->status()}): ".mb_substr($body, 0, 200));

        return mb_substr($body, 0, 160) ?: "CallMeBot answered with error {$response->status()}.";
    }
}
