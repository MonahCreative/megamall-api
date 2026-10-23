<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Models\SmsMessage;
use App\Support\Phone;
use InvalidArgumentException;
use Throwable;

class SmsService
{
    public function gateway(?string $driver = null): SmsGateway
    {
        $driver ??= config('sms.driver');

        return match ($driver) {
            'log' => app(LogSmsGateway::class),
            // 'beem' => app(BeemSmsGateway::class),   // added when the shop has an SMS account
            default => throw new InvalidArgumentException("Unknown SMS driver [{$driver}]."),
        };
    }

    /** Send (or log) one SMS and keep a copy in the outbox. Never throws: a failed SMS must not break an order. */
    public function send(string $event, ?string $phone, string $body, ?Order $order = null): ?SmsMessage
    {
        $to = Phone::normalize($phone);
        if (! $to || trim($body) === '') {
            return null;
        }

        $driver = (string) config('sms.driver');
        $message = SmsMessage::create([
            'order_id' => $order?->id,
            'event' => $event,
            'to' => $to,
            'body' => $body,
            'driver' => $driver,
            'status' => 'pending',
        ]);

        try {
            $result = $this->gateway($driver)->send($to, $body);
            $message->update([
                'status' => $result['status'],
                'provider_id' => $result['provider_id'] ?? null,
                'error' => $result['error'] ?? null,
                'sent_at' => $result['status'] === 'sent' ? now() : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $message->update(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 250)]);
        }

        return $message;
    }
}
