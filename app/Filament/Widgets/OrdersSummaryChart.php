<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\DateRange;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** "Orders summary": share of orders in each stage for the chosen period. */
class OrdersSummaryChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    protected ?string $heading = 'Orders summary';

    protected ?string $maxHeight = '260px';

    private const COLORS = [
        'awaiting_payment' => '#94a3b8',
        'pending' => '#f59e0b',
        'confirmed' => '#16a34a',
        'processing' => '#0ea5e9',
        'shipped' => '#2563eb',
        'delivered' => '#7c3aed',
        'cancelled' => '#e11d48',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getDescription(): ?string
    {
        $range = DateRange::fromFilters($this->pageFilters);
        $total = Order::whereBetween('created_at', [$range->start, $range->end])->count();
        $delivered = Order::whereBetween('created_at', [$range->start, $range->end])
            ->where('status', OrderStatus::Delivered)->count();

        return $total === 0
            ? 'No orders in this period yet'
            : "{$delivered} of {$total} delivered (".round($delivered / $total * 100).'%)';
    }

    protected function getData(): array
    {
        $range = DateRange::fromFilters($this->pageFilters);

        $counts = Order::whereBetween('created_at', [$range->start, $range->end])
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = $data = $colors = [];
        foreach (OrderStatus::cases() as $status) {
            $labels[] = $status->getLabel();
            $data[] = (int) ($counts[$status->value] ?? 0);
            $colors[] = self::COLORS[$status->value];
        }

        return [
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $colors,
                'borderWidth' => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
