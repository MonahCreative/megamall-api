<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Sales at the shop counter: paid on the spot, handed over immediately. */
class PosService
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * @param  array<int, array{product_id:int, variant_id?:?int, quantity:int}>  $items
     * @param  string  $channel  cash, mpesa, mixx, airtel, halopesa, card, bank
     */
    public function sell(
        array $items,
        string $channel,
        int $discount = 0,
        ?string $customerName = null,
        ?string $phone = null,
        ?string $reference = null,
    ): Order {
        $normalizedPhone = filled($phone) ? Phone::normalize($phone) : null;
        if (filled($phone) && ! $normalizedPhone) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Tanzanian phone number, or leave it empty.']);
        }

        return DB::transaction(function () use ($items, $channel, $discount, $customerName, $normalizedPhone, $reference) {
            $lines = $this->orders->priceLines($items, lock: true);
            $subtotal = array_sum(array_column($lines, 'line_total'));

            if ($discount < 0 || $discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'The discount cannot be more than the total.']);
            }

            $order = Order::create([
                'order_number' => $this->orders->newOrderNumber('POS'),
                'access_token' => Str::random(48),
                'channel' => 'pos',
                'served_by' => auth()->id(),
                'customer_name' => filled($customerName) ? $customerName : 'Walk-in customer',
                'phone' => $normalizedPhone,
                'payment_mode' => PaymentMode::InStore,
                'payment_status' => PaymentStatus::Paid,
                'status' => OrderStatus::Delivered,
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'paid_at' => now(),
                'delivered_at' => now(),
            ]);

            $this->orders->saveLinesAndTakeStock($order, $lines, StockMovementType::PosSale);

            $order->payments()->create([
                'gateway' => 'pos',
                'channel' => $channel,
                'phone' => $normalizedPhone,
                'amount' => $order->total,
                'status' => PaymentStatus::Paid,
                'reference' => $order->order_number.'-P',
                'external_id' => $reference,
                'paid_at' => now(),
            ]);

            return $order->load('items', 'payments');
        });
    }
}
