<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * php artisan payments:simulate-callback MM261015-K7Q2D-AB12 [--fail]
 *
 * On your own PC AzamPay cannot reach you to report the payment. This plays the callback
 * AzamPay would send, so you can test the whole flow. Never needed on the live server.
 */
class SimulatePaymentCallback extends Command
{
    protected $signature = 'payments:simulate-callback {reference? : Payment reference; leave empty for the latest pending AzamPay payment} {--fail : Simulate a declined payment}';

    protected $description = 'Pretend AzamPay called back with the result of a payment (local testing only)';

    public function handle(PaymentService $payments): int
    {
        if (app()->isProduction()) {
            $this->error('Not allowed on the live server.');

            return self::FAILURE;
        }

        $payment = $this->argument('reference')
            ? Payment::where('reference', $this->argument('reference'))->first()
            : Payment::where('gateway', 'azampay')->where('status', 'pending')->latest('id')->first();

        if (! $payment) {
            $this->error('No matching payment found.');

            return self::FAILURE;
        }

        $request = Request::create('/api/payments/callback/azampay', 'POST', [
            'msisdn' => $payment->phone,
            'amount' => (string) $payment->amount,
            'message' => $this->option('fail') ? 'Insufficient balance' : 'Payment successful',
            'utilityref' => $payment->reference,
            'operator' => config("payments.azampay.providers.{$payment->channel}"),
            'reference' => 'SIM'.now()->format('His'),
            'transactionstatus' => $this->option('fail') ? 'failure' : 'success',
            'submerchantAcc' => '',
        ]);
        $request->query->set('key', (string) config('payments.azampay.callback_key'));

        $updated = $payments->gateway('azampay')->handleCallback($request);
        if (! $updated) {
            $this->error('The callback was refused. Is AZAMPAY_CALLBACK_KEY set in .env?');

            return self::FAILURE;
        }
        $payments->sync($updated);

        $this->info("Payment {$updated->reference} is now {$updated->status->value}. Order {$updated->order->order_number}: {$updated->order->fresh()->status->value}.");

        return self::SUCCESS;
    }
}
