<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case Purchase = 'purchase';
    case OnlineSale = 'online_sale';
    case PosSale = 'pos_sale';
    case OrderCancelled = 'order_cancelled';
    case Return = 'return';
    case Damage = 'damage';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase received',
            self::OnlineSale => 'Online order',
            self::PosSale => 'Shop sale (POS)',
            self::OrderCancelled => 'Order cancelled',
            self::Return => 'Customer return',
            self::Damage => 'Damaged / lost',
            self::Adjustment => 'Stock count correction',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase, self::OrderCancelled, self::Return => 'success',
            self::OnlineSale, self::PosSale => 'info',
            self::Damage => 'danger',
            self::Adjustment => 'warning',
        };
    }
}
