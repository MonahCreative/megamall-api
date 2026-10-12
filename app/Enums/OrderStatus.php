<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case AwaitingPayment = 'awaiting_payment'; // prepaid order created, mobile money not yet received
    case Pending = 'pending';                  // COD order received, not yet confirmed by staff
    case Confirmed = 'confirmed';              // staff called the customer / payment received
    case Processing = 'processing';            // being packed
    case Shipped = 'shipped';                  // with rider or on the bus
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** Wording the customer sees on the Track Order page. */
    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            self::Pending => 'Order received',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Being packed',
            self::Shipped => 'On the way',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Wording staff see in the admin panel. */
    public function getLabel(): string
    {
        return $this === self::Pending ? 'New: call to confirm' : $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'gray',
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Processing => 'info',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Statuses whose stock is still held for the customer. */
    public function holdsStock(): bool
    {
        return $this !== self::Cancelled;
    }
}
