<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Contract every mobile money provider (AzamPay, Selcom, ClickPesa, ...) implements.
 * Swapping providers means writing one new class and changing PAYMENT_DRIVER in .env.
 */
interface MobileMoneyGateway
{
    /** Send the USSD push (PIN prompt) to the customer's phone. Must set external_id / status on $payment. */
    public function initiate(Payment $payment): void;

    /** Ask the provider for the latest status. Return the updated payment. */
    public function refresh(Payment $payment): Payment;

    /**
     * Handle the provider's webhook. Returns the matching payment with its new status,
     * or null if the request isn't recognised.
     */
    public function handleCallback(Request $request): ?Payment;
}
