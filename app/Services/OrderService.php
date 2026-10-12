<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * All checkout maths happens here, on the server.
 * The browser only sends product ids and quantities; prices, fees and the
 * payment rule are never trusted from the client.
 */
class OrderService
{
    /**
     * Price a cart for a region without saving anything.
     *
     * @param  array<int, array{product_id:int, variant_id?:int|null, quantity:int}>  $items
     */
    public function quote(array $items, Region $region, ?District $district = null): array
    {
        $lines = $this->priceLines($items, lock: false);
        $subtotal = array_sum(array_column($lines, 'line_total'));
        $fee = $this->deliveryFee($region, $district);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'delivery_fee' => $fee,
            'total' => $subtotal + $fee,
            'payment_mode' => $region->payment_mode->value,
            'payment_mode_label' => $region->payment_mode->label(),
            'delivery_days' => [$region->delivery_days_min, $region->delivery_days_max],
        ];
    }

    /** Create the order, reserve stock, and apply the Dar = COD / elsewhere = prepaid rule. */
    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $region = Region::active()->findOrFail($data['region_id']);
            $district = isset($data['district_id'])
                ? District::where('region_id', $region->id)->findOrFail($data['district_id'])
                : null;

            if ($region->districts()->exists() && ! $district) {
                throw ValidationException::withMessages(['district_id' => 'Choose your district.']);
            }

            $lines = $this->priceLines($data['items'], lock: true);
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $fee = $this->deliveryFee($region, $district);
            $cod = $region->payment_mode === PaymentMode::CashOnDelivery;

            $order = Order::create([
                'order_number' => $this->newOrderNumber(),
                'access_token' => Str::random(48),
                'user_id' => $data['user_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'phone' => Phone::normalize($data['phone']),
                'email' => $data['email'] ?? null,
                'region_id' => $region->id,
                'district_id' => $district?->id,
                'area' => $data['area'],
                'address' => $data['address'],
                'notes' => $data['notes'] ?? null,
                'payment_mode' => $region->payment_mode,
                'payment_status' => PaymentStatus::Unpaid,
                'status' => $cod ? OrderStatus::Pending : OrderStatus::AwaitingPayment,
                'subtotal' => $subtotal,
                'delivery_fee' => $fee,
                'total' => $subtotal + $fee,
                'expires_at' => $cod ? null : now()->addMinutes((int) Setting::getValue('prepaid_order_expiry_minutes', 60)),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'product_variant_id' => $line['variant_id'],
                    'product_name' => $line['name'],
                    'variant_name' => $line['variant_name'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                // Reserve stock now so two customers can't buy the last item.
                $line['variant_id']
                    ? ProductVariant::whereKey($line['variant_id'])->decrement('stock', $line['quantity'])
                    : Product::whereKey($line['product_id'])->decrement('stock', $line['quantity']);
                Product::whereKey($line['product_id'])->increment('sold_count', $line['quantity']);
            }

            return $order->load('items', 'region', 'district');
        });
    }

    /** Cancel an order and give its stock back. */
    public function cancel(Order $order, string $reason = 'Cancelled'): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order->refresh();
            if ($order->status === OrderStatus::Cancelled) {
                return;
            }

            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                } elseif ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => OrderStatus::Cancelled, 'tracking_note' => $reason]);
        });
    }

    public function deliveryFee(Region $region, ?District $district = null): int
    {
        return $district?->delivery_fee ?? $region->delivery_fee;
    }

    /**
     * @return array<int, array{product_id:int, variant_id:?int, name:string, variant_name:?string, unit_price:int, quantity:int, line_total:int}>
     */
    private function priceLines(array $items, bool $lock): array
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Your cart is empty.']);
        }

        $lines = [];
        foreach (array_values($items) as $i => $item) {
            $qty = (int) $item['quantity'];
            $query = Product::active()->whereKey($item['product_id']);
            $product = ($lock ? $query->lockForUpdate() : $query)->first();

            if (! $product) {
                throw ValidationException::withMessages(["items.$i.product_id" => 'This product is no longer available.']);
            }

            $variant = null;
            if (! empty($item['variant_id'])) {
                $vq = $product->variants()->where('is_active', true)->whereKey($item['variant_id']);
                $variant = ($lock ? $vq->lockForUpdate() : $vq)->first();
                if (! $variant) {
                    throw ValidationException::withMessages(["items.$i.variant_id" => "Choose an available option for {$product->name}."]);
                }
            } elseif ($product->variants()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["items.$i.variant_id" => "Choose a size or option for {$product->name}."]);
            }

            $available = $variant ? $variant->stock : $product->stock;
            if ($qty > $available) {
                throw ValidationException::withMessages([
                    "items.$i.quantity" => $available > 0
                        ? "Only {$available} left of {$product->name}."
                        : "{$product->name} is out of stock.",
                ]);
            }

            $unit = $variant?->price ?? $product->price;
            $lines[] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->name,
                'variant_name' => $variant?->name,
                'unit_price' => $unit,
                'quantity' => $qty,
                'line_total' => $unit * $qty,
            ];
        }

        return $lines;
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'MM'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
