<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Damage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Purchases (stock in) and damages (stock out). */
class InventoryService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Record goods received from a supplier and add them to stock.
     *
     * @param  array{supplier_id?:?int, purchase_date?:string, invoice_number?:?string, notes?:?string}  $data
     * @param  array<int, array{product_id:int, product_variant_id?:?int, quantity:int, unit_cost:int}>  $items
     */
    public function receivePurchase(array $data, array $items): Purchase
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Add at least one product.']);
        }

        return DB::transaction(function () use ($data, $items) {
            $purchase = Purchase::create([
                'reference' => 'PUR-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'invoice_number' => $data['invoice_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $total = 0;
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $variantId = $item['product_variant_id'] ?? null;
                if ($variantId && ! $product->variants()->whereKey($variantId)->exists()) {
                    throw ValidationException::withMessages(['items' => "That option does not belong to {$product->name}."]);
                }

                $qty = (int) $item['quantity'];
                $cost = (int) $item['unit_cost'];
                $purchase->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'line_total' => $qty * $cost,
                ]);
                $total += $qty * $cost;

                $this->stock->move($product, $variantId, $qty, StockMovementType::Purchase, $purchase, "Purchase {$purchase->reference}");
                $product->update(['cost_price' => $cost]);
            }

            $purchase->update(['total' => $total]);

            return $purchase->load('items');
        });
    }

    /** Record broken, expired, lost or stolen stock and remove it. */
    public function recordDamage(int $productId, ?int $variantId, int $quantity, string $reason, ?string $notes = null, ?string $date = null): Damage
    {
        return DB::transaction(function () use ($productId, $variantId, $quantity, $reason, $notes, $date) {
            $product = Product::findOrFail($productId);
            if ($variantId && ! $product->variants()->whereKey($variantId)->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => "That option does not belong to {$product->name}."]);
            }
            if (! $variantId && $product->variants()->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'Choose which size or option was damaged.']);
            }

            $damage = Damage::create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'reason' => $reason,
                'damage_date' => $date ?? now()->toDateString(),
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            $this->stock->move($product, $variantId, -$quantity, StockMovementType::Damage, $damage, Damage::REASONS[$reason] ?? $reason);

            return $damage;
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
