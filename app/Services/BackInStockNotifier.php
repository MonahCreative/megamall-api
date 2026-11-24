<?php

namespace App\Services;

use App\Filament\Resources\StockAlerts\StockAlertResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * "Tell me when it's back in stock": save the customer's number, and when stock returns
 * send staff a WhatsApp with the numbers to contact. Staff then mark each request as contacted.
 */
class BackInStockNotifier
{
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
        $alert->notified_at = null; // asking again after being contacted: wait for the next restock
        $alert->save();

        return $alert;
    }

    /** Called when a product or size goes from 0 to some stock. Sends after the stock change is saved. */
    public function stockReturned(int $productId, ?int $variantId): void
    {
        DB::afterCommit(function () use ($productId, $variantId) {
            try {
                $text = $this->staffText($productId, $variantId);
                if ($text) {
                    defer(fn () => app(StaffWhatsApp::class)->sendToAll($text));
                }
            } catch (Throwable $exception) {
                report($exception); // a failed alert must never undo a stock change
            }
        });
    }

    /** The WhatsApp text for staff, or null when nobody is waiting. */
    public function staffText(int $productId, ?int $variantId): ?string
    {
        $product = Product::active()->find($productId);
        if (! $product) {
            return null;
        }
        $variant = $variantId ? ProductVariant::find($variantId) : null;

        // Someone who asked for "any size" is waiting for any size; a size request only for that size.
        $phones = StockAlert::waiting()
            ->where('product_id', $productId)
            ->where(fn ($query) => $query->whereNull('product_variant_id')->when($variantId, fn ($inner) => $inner->orWhere('product_variant_id', $variantId)))
            ->pluck('phone');

        if ($phones->isEmpty()) {
            return null;
        }

        return implode("\n", [
            '*Back in stock* '.$product->name.($variant ? " ({$variant->name})" : ''),
            $phones->count().' customer'.($phones->count() === 1 ? ' is' : 's are').' waiting. Please contact them:',
            ...$phones->map(fn (string $phone) => Phone::display($phone))->all(),
            StockAlertResource::getUrl('index', panel: 'admin'),
        ]);
    }
}
