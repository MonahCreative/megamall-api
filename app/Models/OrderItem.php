<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name',
        'unit_price', 'unit_cost', 'quantity', 'line_total',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'unit_cost' => 'integer', 'quantity' => 'integer', 'line_total' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    /** How many of this line have already been asked back in non-rejected returns. */
    public function returnedQuantity(): int
    {
        return (int) $this->returnItems()
            ->whereHas('returnRequest', fn ($query) => $query->where('status', '!=', 'rejected'))
            ->sum('quantity');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
