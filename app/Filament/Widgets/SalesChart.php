<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\DateRange;
use App\Support\Money;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** "Sales summary": money received per day (or per month for long periods). */
class SalesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Sales summary';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): ?string
    {
        $range = DateRange::fromFilters($this->pageFilters);
        $total = $this->paidOrders($range)->sum('total');

        return Money::tzs($total).' received · '.Money::tzs(intdiv((int) $total, $range->days())).' per day on average';
    }

    protected function getData(): array
    {
        $range = DateRange::fromFilters($this->pageFilters);
        $monthly = $range->days() > 62;

        $buckets = [];
        $cursor = $monthly ? $range->start->startOfMonth() : $range->start;
        while ($cursor->lessThanOrEqualTo($range->end)) {
            $buckets[$cursor->format($monthly ? 'Y-m' : 'Y-m-d')] = 0;
            $cursor = $monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        foreach ($this->paidOrders($range)->get(['paid_at', 'total']) as $order) {
            $key = $order->paid_at->format($monthly ? 'Y-m' : 'Y-m-d');
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += $order->total;
            }
        }

        return [
            'datasets' => [[
                'label' => 'Money received (TZS)',
                'data' => array_values($buckets),
                'borderColor' => '#f97316',
                'backgroundColor' => 'rgba(249, 115, 22, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(
                fn (string $key) => $monthly
                    ? \Carbon\CarbonImmutable::createFromFormat('Y-m', $key)->format('M Y')
                    : \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $key)->format('d M'),
                array_keys($buckets),
            ),
        ];
    }

    private function paidOrders(DateRange $range)
    {
        return Order::where('payment_status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$range->start, $range->end]);
    }
}
