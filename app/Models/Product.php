<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'brand', 'short_description', 'description',
        'price', 'compare_at_price', 'cost_price', 'stock', 'reorder_level', 'video_url', 'is_active', 'is_featured', 'sold_count',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost_price' => 'integer',
            'stock' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $base = Str::slug($product->name);
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->whereKeyNot($product->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $product->slug = $slug;
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /** Total stock across sizes/colours when the product has them, otherwise the product's own stock. */
    public function totalStock(): int
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : $this->variants()->get();
        $active = $variants->where('is_active', true);

        return $active->isNotEmpty() ? (int) $active->sum('stock') : (int) $this->stock;
    }

    /** Adds `rating` (average of published reviews) and `review_count` to each product. */
    public function scopeWithRatings(Builder $query): void
    {
        $query->withAvg(['reviews as rating' => fn ($reviews) => $reviews->where('status', 'approved')], 'rating')
            ->withCount(['reviews as review_count' => fn ($reviews) => $reviews->where('status', 'approved')]);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Percentage saved versus the "was" price, e.g. 22 for -22%. */
    public function discountPercent(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round(100 - ($this->price / $this->compare_at_price * 100));
    }
}
