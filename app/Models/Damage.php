<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Damage extends Model
{
    public const REASONS = [
        'broken' => 'Broken / damaged',
        'expired' => 'Expired',
        'lost' => 'Lost',
        'stolen' => 'Stolen',
        'other' => 'Other',
    ];

    protected $fillable = ['product_id', 'product_variant_id', 'quantity', 'reason', 'damage_date', 'notes', 'user_id'];

    protected function casts(): array
    {
        return ['damage_date' => 'date', 'quantity' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
