<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Shown on the Stock page only (not the dashboard). */
class StockOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $simpleUnits = (int) Product::whereDoesntHave('variants')->sum('stock');
        $variantUnits = (int) ProductVariant::sum('stock');
        $out = Product::where(fn ($query) => $query
            ->where(fn ($simple) => $simple->whereDoesntHave('variants')->where('stock', 0))
            ->orWhere(fn ($withVariants) => $withVariants->whereHas('variants')->whereDoesntHave('variants', fn ($v) => $v->where('stock', '>', 0))))
            ->count();
        $missingCost = Product::whereNull('cost_price')->count();

        return [
            Stat::make('Units in stock', number_format($simpleUnits + $variantUnits)),
            Stat::make('Stock value at cost', Money::tzs(app(InventoryService::class)->stockValue()))
                ->description($missingCost ? "{$missingCost} product(s) have no cost price yet" : 'All products have a cost price'),
            Stat::make('Out of stock', $out)->color($out ? 'danger' : 'success'),
        ];
    }
}
