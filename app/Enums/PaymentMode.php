<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentMode: string implements HasColor, HasLabel
{
    case CashOnDelivery = 'cod';
    case Prepaid = 'prepaid';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Pay on delivery',
            self::Prepaid => 'Pay before delivery',
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
        };
    }
}
