<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMode: string implements HasColor, HasLabel
{
    case CashOnDelivery = 'cod';
    case Prepaid = 'prepaid';
    case InStore = 'in_store'; // POS sale, paid at the counter

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Pay on delivery',
            self::Prepaid => 'Pay before delivery',
            self::InStore => 'Paid in shop',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'success',
            self::Prepaid => 'info',
            self::InStore => 'gray',
        };
    }
}
