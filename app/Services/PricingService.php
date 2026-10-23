<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * Works out the price a customer pays right now, including any running promotion.
 * Registered as "scoped" in AppServiceProvider, so promotions are loaded once per request.
 */
class PricingService
{
    /** @var Collection<int, Promotion>|null */
    private ?Collection $running = null;

    /** @return Collection<int, Promotion> */
    public function runningPromotions(): Collection
    {
        return $this->running ??= Promotion::running()
            ->with(['products:id', 'categories:id'])
            ->get();
    }

    /** The promotion giving this product the lowest price, if any applies. */
    public function bestPromotion(Product $product, ?int $basePrice = null): ?Promotion
    {
        $basePrice ??= $product->price;
        $parentId = $product->relationLoaded('category') ? $product->category?->parent_id : $product->category()->value('parent_id');

        return $this->runningPromotions()
            ->filter(fn (Promotion $promotion) => match ($promotion->applies_to) {
                'all' => true,
                'categories' => $promotion->categories->pluck('id')->intersect(array_filter([$product->category_id, $parentId]))->isNotEmpty(),
                default => $promotion->products->contains('id', $product->id),
            })
            ->sortBy(fn (Promotion $promotion) => $promotion->apply($basePrice))
            ->first();
    }

    /** Price for one unit, after promotions. */
    public function unitPrice(Product $product, ?ProductVariant $variant = null): int
    {
        $base = $variant?->price ?? $product->price;
        $promotion = $this->bestPromotion($product, $base);

        return $promotion ? $promotion->apply($base) : $base;
    }

    /**
     * What the shop shows: the selling price, the crossed-out price, and the promotion (for a badge and countdown).
     *
     * @return array{price:int, compare_at_price:?int, discount_percent:?int, promotion:?array}
     */
    public function display(Product $product, ?ProductVariant $variant = null): array
    {
        $base = $variant?->price ?? $product->price;
        $promotion = $this->bestPromotion($product, $base);
        $price = $promotion ? $promotion->apply($base) : $base;

        // Crossed-out price: the normal price during a promotion, otherwise the product's "old price".
        $compare = $promotion ? max($base, (int) $product->compare_at_price) : $product->compare_at_price;
        if ($compare !== null && $compare <= $price) {
            $compare = null;
        }

        return [
            'price' => $price,
            'compare_at_price' => $compare,
            'discount_percent' => $compare ? (int) round(100 - $price / $compare * 100) : null,
            'promotion' => $promotion ? [
                'name' => $promotion->name,
                'badge' => $promotion->badge,
                'ends_at' => $promotion->ends_at->toIso8601String(),
            ] : null,
        ];
    }
}
