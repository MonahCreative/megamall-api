<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Services\Sms\SmsService;
use App\Support\Phone;
use App\Support\SmsTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** "Notify me when back in stock": save requests, and SMS everyone waiting when stock returns. */
class BackInStockNotifier
{
    public function __construct(private readonly SmsService $sms) {}

    public function request(Product $product, string $phone, ?int $variantId = null): StockAlert
    {
        $normalized = Phone::normalize($phone);
        if (! $normalized) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Tanzanian mobile number, for example 0712 345 678.']);
        }
        if ($variantId && ! $product->variants()->whereKey($variantId)->exists()) {
            throw ValidationException::withMessages(['variant_id' => 'Choose a size or option of this product.']);
        }

        $alert = StockAlert::firstOrNew(['product_id' => $product->id, 'product_variant_id' => $variantId, 'phone' => $normalized]);
        $alert->notified_at = null; // asking again after an earlier SMS: wait for the next restock
        $alert->save();

        return $alert;
    }

    /** Called when a product or size goes from 0 to some stock. Sends after the stock change is saved. */
    public function stockReturned(int $productId, ?int $variantId): void
    {
        DB::afterCommit(function () use ($productId, $variantId) {
            try {
                $this->send($productId, $variantId);
            } catch (Throwable $exception) {
                report($exception); // a failed SMS must never undo a purchase or stock count
            }
        });
    }

    private function send(int $productId, ?int $variantId): void
    {
        $product = Product::active()->find($productId);
        if (! $product || ! Setting::getValue('sms_enabled', true)) {
            return;
        }
        $variant = $variantId ? ProductVariant::find($variantId) : null;

        // Someone who asked for "any size" is told when any size comes back; a size request only for that size.
        $alerts = StockAlert::waiting()
            ->where('product_id', $productId)
            ->where(fn ($query) => $query->whereNull('product_variant_id')->when($variantId, fn ($inner) => $inner->orWhere('product_variant_id', $variantId)))
            ->get();

        foreach ($alerts as $alert) {
            $this->sms->send('back_in_stock', $alert->phone, SmsTemplates::renderForProduct('back_in_stock', $product, $alert->variant ?? $variant));
            $alert->update(['notified_at' => now()]);
        }
    }
}
