<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer asked for an SMS when a product (or one size of it) is back in stock. */
class StockAlert extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'phone', 'notified_at'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeWaiting(Builder $query): void
    {
        $query->whereNull('notified_at');
    }
}
