<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $this->relationLoaded('images');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'discount_percent' => $this->discountPercent(),
            'in_stock' => $this->stock > 0 || $this->variants->where('is_active', true)->sum('stock') > 0,
            'stock' => $this->stock,
            'is_featured' => $this->is_featured,
            'has_video' => filled($this->video_url),
            'sold_count' => $this->sold_count,
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
            'variants' => $this->when($detail, fn () => $this->variants->where('is_active', true)->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'options' => $v->options,
                'price' => $v->price ?? $this->price,
                'stock' => $v->stock,
            ])->values()),
        ];
    }
}
