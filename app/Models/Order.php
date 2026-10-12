<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'access_token', 'user_id', 'customer_name', 'phone', 'email',
        'region_id', 'district_id', 'area', 'address', 'notes',
        'payment_mode', 'payment_status', 'status',
        'subtotal', 'delivery_fee', 'total', 'paid_at', 'expires_at', 'tracking_note',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'payment_mode' => PaymentMode::class,
            'payment_status' => PaymentStatus::class,
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    /** Orders staff must act on: new COD orders to confirm, and confirmed/paid orders to pack and send. */
    public function scopeNeedsAction(Builder $query): void
    {
        $query->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function needsPrepayment(): bool
    {
        return $this->payment_mode === PaymentMode::Prepaid && ! $this->isPaid();
    }
}
