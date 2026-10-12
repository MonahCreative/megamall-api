<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\QuoteRequest;
use App\Http\Resources\OrderResource;
use App\Models\District;
use App\Models\Order;
use App\Models\Region;
use App\Rules\TanzanianPhone;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    /** POST /api/checkout/quote — totals and payment rule for the chosen region. Saves nothing. */
    public function quote(QuoteRequest $request): JsonResponse
    {
        $region = Region::active()->findOrFail($request->integer('region_id'));
        $district = $request->filled('district_id')
            ? District::where('region_id', $region->id)->findOrFail($request->integer('district_id'))
            : null;

        return response()->json(['data' => $this->orders->quote($request->validated('items'), $region, $district)]);
    }

    /** POST /api/orders — place an order. Dar es Salaam: done. Elsewhere: response says to pay next. */
    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->orders->place([
            ...$request->validated(),
            'user_id' => $request->user()?->id,
        ]);

        $resource = OrderResource::make($order->load('payments'));
        $resource->withToken = true;

        return response()->json([
            'data' => $resource,
            'next_step' => $order->needsPrepayment() ? 'pay' : 'done',
        ], 201);
    }

    /** GET /api/orders/{number}?token=... — order page for the buyer who placed it. */
    public function show(Request $request, string $number): OrderResource
    {
        return OrderResource::make($this->findForBuyer($request, $number));
    }

    /** POST /api/orders/{number}/pay — send the mobile money PIN prompt. */
    public function pay(Request $request, string $number): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'channel' => ['required', Rule::in(array_keys(config('payments.channels')))],
            'phone' => ['required', 'string', new TanzanianPhone],
        ]);

        $order = $this->findForBuyer($request, $number);
        $this->payments->start($order, $data['channel'], $data['phone']);

        return response()->json([
            'data' => OrderResource::make($order->fresh(['items', 'region', 'district', 'payments'])),
            'message' => 'Check your phone and enter your PIN to approve the payment.',
        ]);
    }

    /** GET /api/orders/{number}/payment-status?token=... — the checkout page polls this every few seconds. */
    public function paymentStatus(Request $request, string $number): OrderResource
    {
        $order = $this->findForBuyer($request, $number);
        if ($payment = $order->payments->first()) {
            $this->payments->refresh($payment);
        }

        return OrderResource::make($order->fresh(['items', 'region', 'district', 'payments']));
    }

    /** POST /api/orders/track {order_number, phone} — public "Track Order" page. */
    public function track(Request $request): OrderResource
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string'],
        ]);

        $order = Order::where('order_number', strtoupper(trim($data['order_number'])))
            ->where('phone', Phone::normalize($data['phone']))
            ->with(['items', 'region', 'district', 'payments'])
            ->first();

        abort_unless($order, 404, 'No order found with that number and phone. Check both and try again.');

        return OrderResource::make($order);
    }

    private function findForBuyer(Request $request, string $number): Order
    {
        $order = Order::where('order_number', $number)
            ->with(['items', 'region', 'district', 'payments'])
            ->firstOrFail();

        abort_unless(hash_equals($order->access_token, (string) $request->input('token')), 403, 'This link is not valid for this order.');

        return $order;
    }
}
