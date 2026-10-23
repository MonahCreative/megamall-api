<?php

namespace App\Filament\Resources\StockAlerts\Pages;

use App\Filament\Resources\StockAlerts\StockAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListStockAlerts extends ListRecords
{
    protected static string $resource = StockAlertResource::class;

    public function getSubheading(): ?string
    {
        return 'When these products are restocked (purchase, stock count or import), the customers get an SMS automatically.';
    }
}
