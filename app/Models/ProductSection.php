<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductSection extends Model
{
    public const SOURCES = [
        'manual' => 'Products I choose',
        'featured' => 'Featured products',
        'newest' => 'Newest products',
        'best_selling' => 'Best sellers',
        'discounted' => 'Products on sale',
        'category' => 'From one category',
    ];

    protected $fillable = ['title', 'subtitle', 'source', 'category_id', 'product_limit', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'product_limit' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
