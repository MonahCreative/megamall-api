<?php

namespace App\Filament\Resources\PosOrders\Pages;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\PosOrders\PosOrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPosOrder extends ViewRecord
{
    protected static string $resource = PosOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PosOrderResource::receiptAction(),
            OrderActions::startReturn(),
        ];
    }

    public function getTitle(): string
    {
        return 'Shop sale '.$this->getRecord()->order_number;
    }
}
