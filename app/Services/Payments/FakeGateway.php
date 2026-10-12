<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Local development gateway. No real money moves.
 * The payment turns "paid" a few seconds after it is started, unless the
 * phone number ends in the configured fail suffix (default 999).
 */
class FakeGateway implements MobileMoneyGateway
{
    public function initiate(Payment $payment): void
    {
        $payment->update([
            'external_id' => 'FAKE'.strtoupper(Str::random(10)),
            'status' => PaymentStatus::Pending,
        ]);
    }

    public function refresh(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        if ($payment->created_at->diffInSeconds(now()) < config('payments.fake.approve_after')) {
            return $payment;
        }

        if (str_ends_with((string) $payment->phone, (string) config('payments.fake.fail_suffix'))) {
            $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => 'Customer did not approve the payment (test).']);
        } else {
            $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
        }

        return $payment;
    }

    public function handleCallback(Request $request): ?Payment
    {
        return null; // the fake gateway never calls back
    }
}
