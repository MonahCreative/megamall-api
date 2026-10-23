<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReturnStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';   // customer may send the items back
    case Rejected = 'rejected';
    case Received = 'received';   // items are back in the shop
    case Refunded = 'refunded';   // money given back

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'New request',
            self::Approved => 'Approved: waiting for items',
            self::Rejected => 'Rejected',
            self::Received => 'Items received',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Received => 'primary',
            self::Refunded => 'success',
        };
    }

    public static function reasons(): array
    {
        return [
            'damaged' => 'Arrived damaged',
            'wrong_item' => 'Wrong item sent',
            'not_as_described' => 'Not as described',
            'not_working' => 'Does not work',
            'changed_mind' => 'Changed my mind',
            'other' => 'Other',
        ];
    }
}
