<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    protected $fillable = [
        'name', 'discount_type', 'discount_value', 'applies_to', 'starts_at', 'ends_at', 'is_active', 'badge',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** Switched on and running right now. */
    public function scopeRunning(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /** Price after this promotion's discount (never below 0). */
    public function apply(int $price): int
    {
        $off = $this->discount_type === 'percent'
            ? (int) round($price * min($this->discount_value, 100) / 100)
            : $this->discount_value;

        return max(0, $price - $off);
    }

    public function isRunning(): bool
    {
        return $this->is_active && $this->starts_at <= now() && $this->ends_at >= now();
    }
}
