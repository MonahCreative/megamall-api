<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;

/** Only real buyers can review: the order number and phone must match a delivered order containing the product. */
class ReviewService
{
    public function submit(Product $product, string $orderNumber, string $phone, int $rating, ?string $comment): Review
    {
        $order = Order::where('order_number', strtoupper(trim($orderNumber)))
            ->where('phone', Phone::normalize($phone))
            ->first();

        if (! $order || ! $order->items()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['order_number' => 'We could not find this product in an order with that number and phone.']);
        }
        if ($order->status !== OrderStatus::Delivered) {
            throw ValidationException::withMessages(['order_number' => 'You can review this product once your order has been delivered.']);
        }
        if (Review::where('product_id', $product->id)->where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['order_number' => 'You have already reviewed this product. Thank you!']);
        }

        return Review::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'customer_name' => $order->customer_name,
            'phone' => $order->phone,
            'rating' => max(1, min(5, $rating)),
            'comment' => $comment,
            'status' => Setting::getValue('auto_approve_reviews', false) ? ReviewStatus::Approved : ReviewStatus::Pending,
        ]);
    }

    /** @return array{average: float|null, count: int, breakdown: array<int,int>} */
    public function summary(Product $product): array
    {
        $counts = $product->reviews()->approved()->toBase()
            ->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');
        $count = (int) $counts->sum();
        $sum = $counts->map(fn ($total, $rating) => $rating * $total)->sum();

        return [
            'average' => $count ? round($sum / $count, 1) : null,
            'count' => $count,
            'breakdown' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($star) => [$star => (int) ($counts[$star] ?? 0)])->all(),
        ];
    }
}
