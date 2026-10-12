<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function gateway(?string $driver = null): MobileMoneyGateway
    {
        $driver ??= config('payments.driver');

        return match ($driver) {
            'fake' => app(FakeGateway::class),
            // 'azampay' => app(AzamPayGateway::class),   // added when we connect the real gateway
            default => throw new InvalidArgumentException("Unknown payment driver [{$driver}]."),
        };
    }

    /** Start a mobile money payment for a prepaid order. */
    public function start(Order $order, string $channel, string $phone): Payment
    {
        if (! $order->needsPrepayment()) {
            throw ValidationException::withMessages(['order' => 'This order does not need a mobile money payment.']);
        }
        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['order' => 'This order was cancelled because it was not paid in time. Please order again.']);
        }
        if ($order->payments()->where('status', PaymentStatus::Pending)->where('created_at', '>', now()->subMinutes(2))->exists()) {
            throw ValidationException::withMessages(['order' => 'A payment request was just sent. Check your phone and enter your PIN.']);
        }

        $payment = $order->payments()->create([
            'gateway' => config('payments.driver'),
            'channel' => $channel,
            'phone' => Phone::normalize($phone),
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
            'reference' => $order->order_number.'-'.strtoupper(Str::random(4)),
        ]);

        $this->gateway($payment->gateway)->initiate($payment);
        $order->update(['payment_status' => PaymentStatus::Pending]);

        return $payment->refresh();
    }

    /** Check the provider for news and apply it to the order. */
    public function refresh(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::Pending) {
            $payment = $this->gateway($payment->gateway)->refresh($payment);
        }
        $this->sync($payment);

        return $payment;
    }

    /** Apply a payment's status to its order (idempotent: safe to call many times). */
    public function sync(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $order = Order::lockForUpdate()->find($payment->order_id);

            if ($payment->status === PaymentStatus::Paid && ! $order->isPaid()) {
                $order->update([
                    'payment_status' => PaymentStatus::Paid,
                    'paid_at' => $payment->paid_at ?? now(),
                    'expires_at' => null,
                    // If the order expired a moment before the money arrived, staff must restock-check it.
                    'status' => $order->status === OrderStatus::Cancelled ? OrderStatus::Cancelled : OrderStatus::Confirmed,
                    'tracking_note' => $order->status === OrderStatus::Cancelled ? 'Paid after cancellation: refund or reinstate.' : $order->tracking_note,
                ]);
            } elseif ($payment->status === PaymentStatus::Failed && $order->payment_status === PaymentStatus::Pending) {
                $order->update(['payment_status' => PaymentStatus::Failed]);
            }
        });
    }
}
