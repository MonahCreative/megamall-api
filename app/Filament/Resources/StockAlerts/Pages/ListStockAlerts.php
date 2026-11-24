<?php

namespace App\Filament\Resources\StockAlerts\Pages;

use App\Filament\Resources\StockAlerts\StockAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListStockAlerts extends ListRecords
{
    protected static string $resource = StockAlertResource::class;

    public function getSubheading(): ?string
    {
        return 'When a product is restocked, staff get a WhatsApp with the numbers. Tap WhatsApp to message the customer, then Mark contacted.';
    }
}
