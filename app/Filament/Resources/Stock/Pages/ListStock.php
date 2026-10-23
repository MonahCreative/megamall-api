<?php

namespace App\Filament\Resources\Stock\Pages;

use App\Filament\Resources\Stock\StockResource;
use App\Filament\Widgets\StockOverview;
use Filament\Resources\Pages\ListRecords;

class ListStock extends ListRecords
{
    protected static string $resource = StockResource::class;

    protected function getHeaderWidgets(): array
    {
        return [StockOverview::class];
    }
}
