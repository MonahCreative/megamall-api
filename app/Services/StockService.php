<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place stock levels change. Every change is written to stock_movements,
 * so "Stock history" in the admin always explains the current number.
 *
 * Products with sizes/colours keep stock on each variant; others keep it on the product.
 */
class StockService
{
    /**
     * Move stock in (+) or out (-).
     *
     * @param  bool  $allowNegative  sales check availability before calling; set false to refuse going below zero
     */
    public function move(
        Product|int $product,
        ProductVariant|int|null $variant,
        int $quantity,
        StockMovementType $type,
        ?Model $source = null,
        ?string $note = null,
        bool $allowNegative = false,
    ): StockMovement {
        return DB::transaction(function () use ($product, $variant, $quantity, $type, $source, $note, $allowNegative) {
            $productId = $product instanceof Product ? $product->id : $product;
            $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;

            $holder = $variantId
                ? ProductVariant::whereKey($variantId)->lockForUpdate()->firstOrFail()
                : Product::whereKey($productId)->lockForUpdate()->firstOrFail();

            $wasOutOfStock = $holder->stock <= 0;
            $newBalance = $holder->stock + $quantity;
            if ($newBalance < 0 && ! $allowNegative) {
                throw ValidationException::withMessages([
                    'quantity' => "Not enough stock: only {$holder->stock} available.",
                ]);
            }

            $holder->stock = max(0, $newBalance);
            $holder->save();

            if ($wasOutOfStock && $holder->stock > 0) {
                app(BackInStockNotifier::class)->stockReturned($productId, $variantId);
            }

            return StockMovement::create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $holder->stock,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'note' => $note,
                'user_id' => auth()->id(),
            ]);
        });
    }

    /**
     * Log the stock a product (and each size/colour) started with, the first time it is saved.
     * Stock itself is not changed, only explained in the history.
     */
    public function recordOpeningStock(Product $product): void
    {
        $holders = $product->variants()->get()->map(fn (ProductVariant $variant) => [$variant->id, $variant->stock])
            ->prepend([null, $product->stock]);

        foreach ($holders as [$variantId, $quantity]) {
            $logged = StockMovement::where('product_id', $product->id)->where('product_variant_id', $variantId)->exists();
            if (! $logged && $quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'type' => StockMovementType::Adjustment,
                    'quantity' => $quantity,
                    'balance_after' => $quantity,
                    'note' => 'Opening stock',
                    'user_id' => auth()->id(),
                ]);
            }
        }
    }

    /** Set stock to the number counted on the shelf, logging the difference. */
    public function setCount(Product $product, ?ProductVariant $variant, int $counted, ?string $note = null): ?StockMovement
    {
        $current = $variant ? $variant->fresh()->stock : $product->fresh()->stock;
        $difference = $counted - $current;

        if ($difference === 0) {
            return null;
        }

        return $this->move($product, $variant, $difference, StockMovementType::Adjustment, null, $note ?: 'Stock count');
    }
}
