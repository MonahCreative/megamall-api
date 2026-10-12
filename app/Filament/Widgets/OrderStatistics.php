<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\DateRange;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** "Order statistics": how many orders are in each stage, for the period chosen at the top of the dashboard. */
class OrderStatistics extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.order-statistics';

    protected function getViewData(): array
    {
        $range = DateRange::fromFilters($this->pageFilters);

        $counts = Order::query()
            ->whereBetween('created_at', [$range->start, $range->end])
            ->toBase() // plain strings, not enum objects, so they can be array keys
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (OrderStatus $status) => (int) ($counts[$status->value] ?? 0);

        $tiles = [
            ['Total orders', $counts->sum(), Heroicon::OutlinedShoppingBag, '#f97316', 'all'],
            ['New: call to confirm', $count(OrderStatus::Pending), Heroicon::OutlinedPhone, '#f59e0b', 'action'],
            ['Awaiting payment', $count(OrderStatus::AwaitingPayment), Heroicon::OutlinedClock, '#64748b', 'awaiting_payment'],
            ['Confirmed', $count(OrderStatus::Confirmed), Heroicon::OutlinedCheckCircle, '#16a34a', 'action'],
            ['Being packed', $count(OrderStatus::Processing), Heroicon::OutlinedArchiveBox, '#0ea5e9', 'action'],
            ['On the way', $count(OrderStatus::Shipped), Heroicon::OutlinedTruck, '#2563eb', 'shipped'],
            ['Delivered', $count(OrderStatus::Delivered), Heroicon::OutlinedHomeModern, '#7c3aed', 'delivered'],
            ['Cancelled', $count(OrderStatus::Cancelled), Heroicon::OutlinedXCircle, '#e11d48', 'cancelled'],
        ];

        return [
            'rangeLabel' => $range->label,
            'tiles' => array_map(fn (array $tile) => [
                'label' => $tile[0],
                'value' => number_format($tile[1]),
                'icon' => $tile[2],
                'color' => $tile[3],
                'url' => OrderResource::getUrl('index', ['tab' => $tile[4]]),
            ], $tiles),
        ];
    }
}
