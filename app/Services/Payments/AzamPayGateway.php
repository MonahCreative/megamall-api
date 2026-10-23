<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * AzamPay mobile money (M-Pesa, Mixx by Yas, Airtel Money, HaloPesa) by USSD push.
 *
 * 1. initiate(): get a token, then POST /azampay/mno/checkout. The customer gets a PIN prompt.
 * 2. AzamPay POSTs the result to /api/payments/callback/azampay?key=... (handleCallback).
 *    AzamPay has no status check for mobile money, so the callback is what marks the payment paid.
 */
class AzamPayGateway implements MobileMoneyGateway
{
    public function initiate(Payment $payment): void
    {
        $provider = config("payments.azampay.providers.{$payment->channel}");
        if (! $provider) {
            throw ValidationException::withMessages(['channel' => 'This payment method is not available. Choose another one.']);
        }

        try {
            $client = $this->client();
        } catch (RuntimeException $exception) {
            $this->fail($payment, $exception->getMessage()); // e.g. credentials missing or refused
        }

        try {
            $response = $client->post($this->url('checkout', '/azampay/mno/checkout'), [
                'accountNumber' => $payment->phone,           // 255XXXXXXXXX
                'amount' => (string) $payment->amount,
                'currency' => 'TZS',
                'externalId' => $payment->reference,          // comes back as "utilityref" in the callback
                'provider' => $provider,
                'additionalProperties' => ['order' => $payment->order?->order_number],
            ]);
        } catch (ConnectionException $exception) {
            $this->fail($payment, 'Could not reach AzamPay: '.$exception->getMessage());
        }

        if ($response->failed() || $response->json('success') === false) {
            $this->fail($payment, (string) ($response->json('message') ?? $response->body()));
        }

        $payment->update([
            'external_id' => $response->json('transactionId'),
            'status' => PaymentStatus::Pending,
            'payload' => ['checkout' => $response->json()],
        ]);
    }

    /**
     * Send a real sandbox/live checkout without an order, to check the credentials work
     * (used by `php artisan payments:azampay-check`).
     *
     * @return array{status: int, body: mixed}
     */
    public function testCheckout(string $phone, int $amount, string $channel): array
    {
        $response = $this->client()->post($this->url('checkout', '/azampay/mno/checkout'), [
            'accountNumber' => $phone,
            'amount' => (string) $amount,
            'currency' => 'TZS',
            'externalId' => 'TEST'.now()->format('ymdHis'),
            'provider' => config("payments.azampay.providers.{$channel}"),
        ]);

        return ['status' => $response->status(), 'body' => $response->json() ?? $response->body()];
    }

    /** No status endpoint for mobile money at AzamPay: the callback updates the payment. */
    public function refresh(Payment $payment): Payment
    {
        return $payment;
    }

    public function handleCallback(Request $request): ?Payment
    {
        $expectedKey = (string) config('payments.azampay.callback_key');
        if ($expectedKey === '' || ! hash_equals($expectedKey, (string) $request->query('key'))) {
            Log::warning('AzamPay callback with a wrong or missing key was ignored.', ['ip' => $request->ip()]);

            return null;
        }

        $reference = (string) $request->input('utilityref');
        $payment = Payment::where('gateway', 'azampay')->where('reference', $reference)->first();
        if (! $payment) {
            return null;
        }
        if ($payment->status === PaymentStatus::Paid) {
            return $payment; // AzamPay may call more than once
        }

        $success = strtolower((string) $request->input('transactionstatus')) === 'success';
        $amountMatches = (int) round((float) $request->input('amount')) === (int) $payment->amount;
        $payload = [...($payment->payload ?? []), 'callback' => $request->except('key')];

        if ($success && $amountMatches) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'external_id' => $request->input('reference') ?: $payment->external_id,
                'payload' => $payload,
            ]);
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_reason' => $success
                    ? 'Amount received ('.$request->input('amount').') does not match the order. Check with AzamPay.'
                    : ((string) $request->input('message') ?: 'The customer did not complete the payment.'),
                'payload' => $payload,
            ]);
        }

        return $payment;
    }

    /** Authenticated HTTP client. The token is reused until shortly before it expires. */
    private function client(): PendingRequest
    {
        $headers = ['X-API-Key' => (string) config('payments.azampay.api_key')];

        return Http::acceptJson()->timeout(30)->withToken($this->token())->withHeaders(array_filter($headers));
    }

    private function token(): string
    {
        $cacheKey = 'azampay.token.'.config('payments.azampay.environment').'.'.config('payments.azampay.client_id');

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            foreach (['app_name', 'client_id', 'client_secret'] as $setting) {
                if (blank(config("payments.azampay.{$setting}"))) {
                    throw new RuntimeException('AzamPay is not set up: AZAMPAY_'.strtoupper($setting).' is missing in .env');
                }
            }

            $response = Http::acceptJson()->timeout(30)->post($this->url('auth', '/AppRegistration/GenerateToken'), [
                'appName' => config('payments.azampay.app_name'),
                'clientId' => config('payments.azampay.client_id'),
                'clientSecret' => config('payments.azampay.client_secret'),
            ]);

            $token = $response->json('data.accessToken');
            if ($response->failed() || blank($token)) {
                throw new RuntimeException('AzamPay refused the credentials: '.($response->json('message') ?? $response->status()));
            }

            return $token;
        });
    }

    private function url(string $service, string $path): string
    {
        $environment = config('payments.azampay.environment') === 'live' ? 'live' : 'sandbox';

        return rtrim(config("payments.azampay.urls.{$environment}.{$service}"), '/').$path;
    }

    private function fail(Payment $payment, string $reason): never
    {
        $payment->update(['status' => PaymentStatus::Failed, 'failure_reason' => mb_substr($reason, 0, 250)]);
        Log::warning("AzamPay checkout failed for {$payment->reference}: {$reason}");

        throw ValidationException::withMessages([
            'payment' => 'We could not send the payment request to your phone. Check the number and try again, or choose another network.',
        ]);
    }
}
