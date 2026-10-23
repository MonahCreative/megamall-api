<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReturnService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    /** POST /api/returns {order_number, phone, reason, details, items: [{order_item_id, quantity}]} */
    public function store(Request $request, ReturnService $returns): JsonResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string'],
            'reason' => ['required', Rule::in(array_keys(ReturnStatus::reasons()))],
            'details' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $order = Order::where('order_number', strtoupper(trim($data['order_number'])))
            ->where('phone', Phone::normalize($data['phone']))
            ->first();

        abort_unless($order, 404, 'No order found with that number and phone. Check both and try again.');

        $return = $returns->request($order, $data['items'], $data['reason'], $data['details'] ?? null);

        return response()->json([
            'data' => [
                'return_number' => $return->return_number,
                'status' => $return->status->value,
                'status_label' => $return->status->getLabel(),
                'refund_amount' => $return->refund_amount,
            ],
            'message' => 'We have received your return request. We will call you to arrange collection.',
        ], 201);
    }

    /** GET /api/returns/reasons — options for the return form. */
    public function reasons(): JsonResponse
    {
        return response()->json(['data' => collect(ReturnStatus::reasons())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()]);
    }
}
