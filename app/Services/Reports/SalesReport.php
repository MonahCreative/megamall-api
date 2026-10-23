<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Support\PaymentChannels;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sales figures for a period.
 *
 * A sale counts on the day it was paid: at the counter for POS, on delivery for pay-on-delivery orders,
 * and when the mobile money arrived for upcountry orders. Cancelled orders are left out.
 * Sales = items sold minus discounts. Profit = sales minus what the items cost us.
 */
class SalesReport
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    /** @var Collection<int, Order> */
    private Collection $orders;

    /** @param  string  $channel  all | online | pos */
    public function __construct(CarbonInterface $start, CarbonInterface $end, public readonly string $channel = 'all')
    {
        $this->start = CarbonImmutable::instance($start)->startOfDay();
        $this->end = CarbonImmutable::instance($end)->endOfDay();

        $this->orders = $this->salesQuery()
            ->select(['id', 'order_number', 'channel', 'subtotal', 'discount', 'total', 'paid_at'])
            ->with(['items:id,order_id,product_id,product_name,variant_name,unit_price,unit_cost,quantity,line_total', 'items.product:id,category_id', 'items.product.category:id,name'])
            ->get();
    }

    /** Orders that count as sales in this period. */
    public function salesQuery(): Builder
    {
        return Order::query()
            ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Refunded])
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('paid_at', [$this->start, $this->end])
            ->when($this->channel !== 'all', fn (Builder $query) => $query->where('channel', $this->channel));
    }

    /** @return array<string, int|float|null> */
    public function summary(): array
    {
        $items = $this->orders->flatMap->items;
        $gross = (int) $this->orders->sum('subtotal');
        $discounts = (int) $this->orders->sum('discount');
        $sales = $gross - $discounts;
        $cost = (int) $items->sum(fn ($item) => ($item->unit_cost ?? 0) * $item->quantity);
        $profit = $sales - $cost;
        $refunds = $this->refundsTotal();

        return [
            'orders' => $this->orders->count(),
            'online_orders' => $this->orders->where('channel', 'online')->count(),
            'pos_orders' => $this->orders->where('channel', 'pos')->count(),
            'items_sold' => (int) $items->sum('quantity'),
            'gross_sales' => $gross,
            'discounts' => $discounts,
            'sales' => $sales,
            'average_order' => $this->orders->count() ? intdiv($sales, $this->orders->count()) : 0,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $sales > 0 ? round($profit / $sales * 100, 1) : null,
            'items_without_cost' => (int) $items->whereNull('unit_cost')->sum('quantity'),
            'refunds' => $refunds,
            'net_sales' => $sales - $refunds,
        ];
    }

    /** One row per day, including days with no sales. */
    public function byDay(): array
    {
        $grouped = $this->orders->groupBy(fn (Order $order) => $order->paid_at->toDateString());

        $rows = [];
        foreach (CarbonPeriod::create($this->start, '1 day', $this->end->startOfDay()) as $day) {
            $orders = $grouped->get($day->toDateString(), collect());
            $items = $orders->flatMap->items;
            $sales = (int) $orders->sum('subtotal') - (int) $orders->sum('discount');
            $cost = (int) $items->sum(fn ($item) => ($item->unit_cost ?? 0) * $item->quantity);

            $rows[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('D d M'),
                'orders' => $orders->count(),
                'items' => (int) $items->sum('quantity'),
                'sales' => $sales,
                'profit' => $sales - $cost,
            ];
        }

        return array_reverse($rows); // newest first
    }

    /** Every product sold in the period, best-selling (by money) first. Profit here is before order discounts. */
    public function byProduct(): array
    {
        return $this->orders->flatMap->items
            ->groupBy(fn ($item) => $item->product_id ?? 'deleted:'.$item->product_name)
            ->map(function (Collection $lines) {
                $first = $lines->first();
                $sales = (int) $lines->sum('line_total');
                $cost = (int) $lines->sum(fn ($item) => ($item->unit_cost ?? 0) * $item->quantity);

                return [
                    'product_id' => $first->product_id,
                    'name' => $first->product_name,
                    'category' => $first->product?->category?->name ?? 'Unknown',
                    'quantity' => (int) $lines->sum('quantity'),
                    'sales' => $sales,
                    'cost' => $cost,
                    'profit' => $sales - $cost,
                ];
            })
            ->sortByDesc('sales')
            ->values()
            ->all();
    }

    /** Most units sold. */
    public function bestSellers(int $limit = 10): array
    {
        return collect($this->byProduct())->sortByDesc('quantity')->take($limit)->values()->all();
    }

    /**
     * Products in stock that sold least (or nothing) in the period: candidates for a promotion
     * or for not re-ordering. Money tied up = stock x cost price.
     */
    public function slowSellers(int $limit = 10): array
    {
        $sold = collect($this->byProduct())->whereNotNull('product_id')->pluck('quantity', 'product_id');

        return Product::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'variants:id,product_id,stock'])
            ->get(['id', 'name', 'category_id', 'stock', 'cost_price', 'price'])
            ->map(function (Product $product) use ($sold) {
                $stock = $product->variants->isNotEmpty() ? (int) $product->variants->sum('stock') : (int) $product->stock;

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? 'Unknown',
                    'quantity' => (int) ($sold[$product->id] ?? 0),
                    'stock' => $stock,
                    'stock_value' => $stock * (int) ($product->cost_price ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['stock'] > 0)
            ->sortBy([['quantity', 'asc'], ['stock_value', 'desc']])
            ->take($limit)
            ->values()
            ->all();
    }

    public function byCategory(): array
    {
        return collect($this->byProduct())
            ->groupBy('category')
            ->map(fn (Collection $rows, $category) => [
                'category' => (string) $category,
                'quantity' => (int) $rows->sum('quantity'),
                'sales' => (int) $rows->sum('sales'),
                'profit' => (int) $rows->sum('profit'),
            ])
            ->sortByDesc('sales')
            ->values()
            ->all();
    }

    /** Money received per payment method, and money given back. */
    public function byPaymentMethod(): array
    {
        $received = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereIn('order_id', $this->salesQuery()->select('id'))
            ->get(['channel', 'amount'])
            ->groupBy('channel');

        $refunded = $this->refundsQuery()->get(['method', 'amount'])->groupBy('method');

        $methods = collect(PaymentChannels::all())->keys()
            ->merge($received->keys())->merge($refunded->keys())
            ->filter()->unique();

        return $methods
            ->map(fn ($key) => [
                'method' => (string) $key,
                'label' => PaymentChannels::label((string) $key),
                'payments' => $received->get($key, collect())->count(),
                'received' => (int) $received->get($key, collect())->sum('amount'),
                'refunded' => (int) $refunded->get($key, collect())->sum('amount'),
            ])
            ->map(fn (array $row) => [...$row, 'net' => $row['received'] - $row['refunded']])
            ->filter(fn (array $row) => $row['received'] > 0 || $row['refunded'] > 0)
            ->sortByDesc('received')
            ->values()
            ->all();
    }

    /** Refunds given in the period for orders that were real sales. */
    private function refundsQuery(): Builder
    {
        return Refund::query()
            ->whereBetween('created_at', [$this->start, $this->end])
            ->whereHas('order', fn (Builder $query) => $query
                ->where('status', '!=', OrderStatus::Cancelled)
                ->when($this->channel !== 'all', fn (Builder $inner) => $inner->where('channel', $this->channel)));
    }

    private function refundsTotal(): int
    {
        return (int) $this->refundsQuery()->sum('amount');
    }
}
