<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    /** POST /api/payments/callback/{driver} — the payment provider calls this when the customer pays. */
    public function __invoke(Request $request, string $driver, PaymentService $payments): JsonResponse
    {
        // Only gateways that call back. Anything else (lipa_namba, manual, typos) is simply not found.
        abort_unless(in_array($driver, ['azampay'], true), 404);

        Log::channel('stack')->info("Payment callback from {$driver}", $request->all());

        $payment = $payments->gateway($driver)->handleCallback($request);

        if (! $payment) {
            return response()->json(['ok' => false], 404);
        }

        $payments->sync($payment);

        return response()->json(['ok' => true]);
    }
}
