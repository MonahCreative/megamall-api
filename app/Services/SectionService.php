<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSection;
use Illuminate\Database\Eloquent\Collection;

/** Picks the products for each home page section. */
class SectionService
{
    public function __construct(private readonly PricingService $pricing) {}

    public function products(ProductSection $section): Collection
    {
        $base = Product::active()->with(['mainImage', 'category', 'variants'])->withRatings();
        $limit = $section->product_limit ?: 10;

        return match ($section->source) {
            'manual' => $base->whereIn('id', $section->products()->pluck('products.id'))
                ->get()
                ->sortBy(fn (Product $product) => $section->products->firstWhere('id', $product->id)?->pivot->sort_order ?? 0)
                ->take($limit)
                ->values(),
            'featured' => $base->where('is_featured', true)->latest('id')->limit($limit)->get(),
            'best_selling' => $base->orderByDesc('sold_count')->limit($limit)->get(),
            'category' => $base->whereIn('category_id', $section->category
                ? $section->category->children()->pluck('id')->push($section->category_id)
                : [])->latest('id')->limit($limit)->get(),
            'discounted' => $this->discounted($base, $limit),
            default => $base->latest('id')->limit($limit)->get(), // newest
        };
    }

    /** Products with an "old price" or a running promotion. */
    private function discounted($query, int $limit): Collection
    {
        return $query->latest('id')->limit(200)->get()
            ->filter(fn (Product $product) => $this->pricing->display($product)['compare_at_price'] !== null)
            ->take($limit)
            ->values();
    }
}
