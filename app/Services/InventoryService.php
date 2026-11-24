<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Goods coming in, and what the stock on the shelves is worth. */
class InventoryService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * New goods arrived (or a customer brought an item back): add them to stock.
     * When a cost price is given it becomes the product's cost price, so profit stays accurate.
     */
    public function receiveStock(Product $product, ?int $variantId, int $quantity, ?int $unitCost = null, ?string $note = null): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Enter how many arrived.']);
        }
        if ($variantId && ! $product->variants()->whereKey($variantId)->exists()) {
            throw ValidationException::withMessages(['product_variant_id' => "That option does not belong to {$product->name}."]);
        }
        if (! $variantId && $product->variants()->exists()) {
            throw ValidationException::withMessages(['product_variant_id' => 'Choose which size or option arrived.']);
        }

        DB::transaction(function () use ($product, $variantId, $quantity, $unitCost, $note) {
            $this->stock->move($product, $variantId, $quantity, StockMovementType::Purchase, null, $note ?: 'Stock received');
            if ($unitCost !== null) {
                $product->update(['cost_price' => $unitCost]);
            }
        });
    }

    /** Value of stock on hand at cost price (products without a cost price are skipped). */
    public function stockValue(): int
    {
        $simple = (int) Product::whereDoesntHave('variants')->whereNotNull('cost_price')->sum(DB::raw('stock * cost_price'));
        $variants = (int) ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNotNull('products.cost_price')
            ->sum(DB::raw('product_variants.stock * products.cost_price'));

        return $simple + $variants;
    }
}
