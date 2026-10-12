<?php

namespace App\Models;

use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = [
        'name', 'slug', 'payment_mode', 'delivery_fee', 'delivery_days_min', 'delivery_days_max', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payment_mode' => PaymentMode::class,
            'delivery_fee' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('name');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment_mode === PaymentMode::CashOnDelivery;
    }
}
