<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\OrderNotifier;
use App\Support\LipaNamba;
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

        if ($driver === 'fake' && app()->isProduction()) {
            throw new InvalidArgumentException('The fake payment driver cannot be used on the live server. Set PAYMENT_DRIVER in .env.');
        }

        return match ($driver) {
            'fake' => app(FakeGateway::class),
            'azampay' => app(AzamPayGateway::class),
            default => throw new InvalidArgumentException("Unknown payment driver [{$driver}]."),
        };
    }

    /** Start a mobile money payment for a prepaid order. */
    public function start(Order $order, string $channel, string $phone): Payment
    {
        if (! $order->needsPrepayment()) {
            throw ValidationException::withMessages(['order' => 'This order does not need a mobile money payment.']);
        }
        if (LipaNamba::enabled()) {
            throw ValidationException::withMessages(['order' => 'Please pay to our Lipa Namba and enter the transaction ID.']);
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
        // Lipa Namba payments are checked by staff, so there is nobody to ask.
        if ($payment->status === PaymentStatus::Pending && ! in_array($payment->gateway, [LipaNamba::GATEWAY, 'manual'], true)) {
            $payment = $this->gateway($payment->gateway)->refresh($payment);
        }
        $this->sync($payment);

        return $payment;
    }

    /** Apply a payment's status to its order (idempotent: safe to call many times). */
    public function sync(Payment $payment, bool $alertStaff = true): void
    {
        DB::transaction(function () use ($payment, $alertStaff) {
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
                app(OrderNotifier::class)->paymentReceived($order, $alertStaff);
            } elseif ($payment->status === PaymentStatus::Failed && $order->payment_status === PaymentStatus::Pending) {
                $order->update(['payment_status' => PaymentStatus::Failed]);
            }
        });
    }

    /**
     * The customer paid to the shop's Lipa Namba and typed the transaction ID.
     * The order stops counting down and waits for staff to check the payment.
     */
    public function submitLipaNamba(Order $order, string $channel, string $phone, string $transactionText): Payment
    {
        if (! LipaNamba::enabled()) {
            throw ValidationException::withMessages(['order' => 'Lipa Namba payments are switched off. Please refresh the page.']);
        }
        $transactionId = LipaNamba::cleanTransactionId($transactionText);
        if (! $transactionId) {
            throw ValidationException::withMessages(['transaction_id' => 'Enter the transaction ID from the confirmation SMS, for example QJK3X7Y2AB.']);
        }

        $payment = DB::transaction(function () use ($order, $channel, $phone, $transactionId) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['order' => 'This order was cancelled because it was not paid in time. Please order again, or call us if you already paid.']);
            }
            if (! $order->needsPrepayment()) {
                throw ValidationException::withMessages(['order' => 'This order is already paid.']);
            }
            if ($order->payments()->where('gateway', LipaNamba::GATEWAY)->where('status', PaymentStatus::Pending)->exists()) {
                throw ValidationException::withMessages(['order' => 'We already have your transaction ID and are checking it.']);
            }
            // One payment can only pay for one order.
            if (Payment::where('external_id', $transactionId)->where('status', '!=', PaymentStatus::Failed)->exists()) {
                throw ValidationException::withMessages(['transaction_id' => 'This transaction ID was already used. Check the SMS and enter the ID of this payment.']);
            }

            $payment = $order->payments()->create([
                'gateway' => LipaNamba::GATEWAY,
                'channel' => $channel,
                'phone' => Phone::normalize($phone),
                'amount' => $order->total,
                'status' => PaymentStatus::Pending,
                'reference' => $order->order_number.'-L'.strtoupper(Str::random(4)),
                'external_id' => $transactionId,
            ]);

            // Waiting for staff now, not for the customer: don't cancel the order while it is being checked.
            $order->update(['payment_status' => PaymentStatus::Pending, 'expires_at' => null]);

            return $payment;
        });

        app(OrderNotifier::class)->paymentToCheck($payment);

        return $payment;
    }

    /** Staff found the money in their mobile money messages. */
    public function confirmLipaNamba(Payment $payment, ?int $staffId = null): void
    {
        $this->guardCheckable($payment);

        $payment->update([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'payload' => [...($payment->payload ?? []), 'checked_by' => $staffId, 'checked_at' => now()->toIso8601String()],
        ]);

        // Staff did this themselves, so no "paid order" alert to staff; the customer gets the receipt SMS.
        $this->sync($payment, alertStaff: false);
    }

    /** Staff could not find the money. The customer can send the right ID before the order times out. */
    public function rejectLipaNamba(Payment $payment, string $reason, ?int $staffId = null): void
    {
        $this->guardCheckable($payment);

        DB::transaction(function () use ($payment, $reason, $staffId) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $reason,
                'payload' => [...($payment->payload ?? []), 'checked_by' => $staffId, 'checked_at' => now()->toIso8601String()],
            ]);
            $this->sync($payment);

            $order = $payment->order()->first();
            if ($order->status === OrderStatus::AwaitingPayment) {
                $order->update(['expires_at' => now()->addMinutes((int) Setting::getValue('prepaid_order_expiry_minutes', 60))]);
            }
        });

        app(OrderNotifier::class)->paymentNotFound($payment->order()->first(), $payment, $reason);
    }

    private function guardCheckable(Payment $payment): void
    {
        if ($payment->gateway !== LipaNamba::GATEWAY || $payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => 'This payment was already checked.']);
        }
    }
}
