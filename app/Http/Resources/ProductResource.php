<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $this->relationLoaded('images');
        $pricing = app(PricingService::class);
        $shown = $pricing->display($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'short_description' => $this->short_description,
            // Selling price right now (after any promotion) and the crossed-out price.
            'price' => $shown['price'],
            'compare_at_price' => $shown['compare_at_price'],
            'discount_percent' => $shown['discount_percent'],
            'promotion' => $shown['promotion'],
            'in_stock' => $this->totalStock() > 0,
            'stock' => $this->totalStock(),
            'is_featured' => $this->is_featured,
            'has_video' => filled($this->video_url),
            // Products with sizes/colours must be added to the cart from their own page, after choosing one.
            'has_variants' => $this->variants->where('is_active', true)->isNotEmpty(),
            'sold_count' => $this->sold_count,
            'rating' => $this->rating !== null ? round((float) $this->rating, 1) : null,
            'review_count' => (int) ($this->review_count ?? 0),
            'image' => $this->mainImage?->url(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            // Only on the product page
            'description' => $this->when($detail, $this->description),
            'video_url' => $this->when($detail, $this->video_url),
            'images' => $this->when($detail, fn () => $this->images->map(fn ($img) => $img->url())->values()),
            'variants' => $this->when($detail, fn () => $this->variants->where('is_active', true)->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'options' => $variant->options,
                'price' => $pricing->unitPrice($this->resource, $variant),
                'stock' => $variant->stock,
            ])->values()),
        ];
    }
}
