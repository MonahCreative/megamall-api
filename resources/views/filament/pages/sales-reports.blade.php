@use('App\Support\Money')
@php
    $report = $this->report;
    $summary = $report->summary();
    $range = $this->range();
    $byDay = $report->byDay();
    $products = $report->byProduct();
@endphp

<x-filament-panels::page>
    <style>
        .mm-rep-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:12px; }
        .mm-rep-card { border:1px solid rgba(127,127,127,.22); border-radius:14px; padding:14px 16px; display:grid; gap:2px; }
        .mm-rep-card .label { font-size:.8rem; opacity:.7; }
        .mm-rep-card .value { font-size:1.35rem; font-weight:700; font-variant-numeric:tabular-nums; }
        .mm-rep-card .hint { font-size:.78rem; opacity:.65; }
        .mm-rep-card.good .value { color:#16a34a; }
        .mm-rep-card.bad .value { color:#dc2626; }
        .mm-rep-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
        @media (max-width: 1100px) { .mm-rep-grid { grid-template-columns:1fr; } }
        .mm-rep-scroll { max-height:420px; overflow:auto; }
        .mm-rep-table { width:100%; border-collapse:collapse; font-size:.88rem; }
        .mm-rep-table th { text-align:left; font-weight:600; opacity:.75; padding:8px 10px; border-bottom:1px solid rgba(127,127,127,.3); position:sticky; top:0; background:var(--color-white, #fff); }
        .dark .mm-rep-table th { background:var(--gray-900, #111827); }
        .mm-rep-table td { padding:8px 10px; border-bottom:1px solid rgba(127,127,127,.15); }
        .mm-rep-table .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .mm-rep-table tr.zero td { opacity:.45; }
        .mm-rep-table .neg { color:#dc2626; }
        .mm-rep-warn { border-radius:12px; padding:10px 14px; background:rgba(234,179,8,.15); font-size:.88rem; }
        .mm-rep-empty { opacity:.6; font-size:.9rem; padding:8px 0; }
    </style>

    {{ $this->form }}

    <p class="mm-rep-empty" style="padding:0">
        Showing <strong>{{ $range->start->format('d M Y') }} – {{ $range->end->format('d M Y') }}</strong>,
        {{ strtolower(\App\Filament\Pages\SalesReports::CHANNELS[$report->channel] ?? '') }}.
        A sale counts on the day it was paid. Cancelled orders are not included.
    </p>

    <div class="mm-rep-cards">
        <div class="mm-rep-card">
            <span class="label">Sales</span>
            <span class="value">{{ Money::tzs($summary['sales']) }}</span>
            <span class="hint">after {{ Money::tzs($summary['discounts']) }} discounts</span>
        </div>
        <div class="mm-rep-card {{ $summary['profit'] >= 0 ? 'good' : 'bad' }}">
            <span class="label">Gross profit</span>
            <span class="value">{{ Money::tzs($summary['profit']) }}</span>
            <span class="hint">{{ $summary['margin'] !== null ? $summary['margin'].'% margin' : 'no sales' }} · cost {{ Money::tzs($summary['cost']) }}</span>
        </div>
        <div class="mm-rep-card">
            <span class="label">Orders</span>
            <span class="value">{{ number_format($summary['orders']) }}</span>
            <span class="hint">{{ $summary['online_orders'] }} online · {{ $summary['pos_orders'] }} in shop</span>
        </div>
        <div class="mm-rep-card">
            <span class="label">Average order</span>
            <span class="value">{{ Money::tzs($summary['average_order']) }}</span>
            <span class="hint">{{ number_format($summary['items_sold']) }} items sold</span>
        </div>
        <div class="mm-rep-card {{ $summary['refunds'] > 0 ? 'bad' : '' }}">
            <span class="label">Refunds</span>
            <span class="value">{{ Money::tzs($summary['refunds']) }}</span>
            <span class="hint">net sales {{ Money::tzs($summary['net_sales']) }}</span>
        </div>
    </div>

    @if ($summary['items_without_cost'] > 0)
        <div class="mm-rep-warn">
            {{ $summary['items_without_cost'] }} item(s) sold have no cost price, so they count as pure profit.
            Add cost prices under Products (or record purchases) to make profit accurate.
        </div>
    @endif

    <div class="mm-rep-grid">
        <x-filament::section heading="Sales by day">
            <div class="mm-rep-scroll">
                <table class="mm-rep-table">
                    <thead><tr><th>Day</th><th class="num">Orders</th><th class="num">Items</th><th class="num">Sales</th><th class="num">Profit</th></tr></thead>
                    <tbody>
                        @foreach ($byDay as $row)
                            <tr @class(['zero' => $row['orders'] === 0])>
                                <td>{{ $row['label'] }}</td>
                                <td class="num">{{ $row['orders'] }}</td>
                                <td class="num">{{ $row['items'] }}</td>
                                <td class="num">{{ Money::tzs($row['sales']) }}</td>
                                <td @class(['num', 'neg' => $row['profit'] < 0])>{{ Money::tzs($row['profit']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div style="display:grid; gap:16px; align-content:start;">
            <x-filament::section heading="Payment methods">
                @php($methods = $report->byPaymentMethod())
                @if (count($methods))
                    <table class="mm-rep-table">
                        <thead><tr><th>Method</th><th class="num">Payments</th><th class="num">Received</th><th class="num">Refunded</th><th class="num">Net</th></tr></thead>
                        <tbody>
                            @foreach ($methods as $row)
                                <tr>
                                    <td>{{ $row['label'] }}</td>
                                    <td class="num">{{ $row['payments'] }}</td>
                                    <td class="num">{{ Money::tzs($row['received']) }}</td>
                                    <td class="num">{{ $row['refunded'] ? Money::tzs($row['refunded']) : '–' }}</td>
                                    <td class="num">{{ Money::tzs($row['net']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="mm-rep-empty">No payments in this period.</p>
                @endif
            </x-filament::section>

            <x-filament::section heading="Sales by category">
                @php($categories = $report->byCategory())
                @if (count($categories))
                    <table class="mm-rep-table">
                        <thead><tr><th>Category</th><th class="num">Items</th><th class="num">Sales</th><th class="num">Profit</th></tr></thead>
                        <tbody>
                            @foreach ($categories as $row)
                                <tr>
                                    <td>{{ $row['category'] }}</td>
                                    <td class="num">{{ $row['quantity'] }}</td>
                                    <td class="num">{{ Money::tzs($row['sales']) }}</td>
                                    <td @class(['num', 'neg' => $row['profit'] < 0])>{{ Money::tzs($row['profit']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="mm-rep-empty">No sales in this period.</p>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section heading="Best sellers" description="Most units sold in the period.">
            @php($best = $report->bestSellers())
            @if (count($best))
                <table class="mm-rep-table">
                    <thead><tr><th>#</th><th>Product</th><th class="num">Sold</th><th class="num">Sales</th></tr></thead>
                    <tbody>
                        @foreach ($best as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $row['name'] }}<br><span style="opacity:.6; font-size:.8rem">{{ $row['category'] }}</span></td>
                                <td class="num">{{ $row['quantity'] }}</td>
                                <td class="num">{{ Money::tzs($row['sales']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="mm-rep-empty">No sales in this period.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Slow sellers" description="In stock but sold least: consider a promotion, or don't re-order.">
            @php($slow = $report->slowSellers())
            @if (count($slow))
                <table class="mm-rep-table">
                    <thead><tr><th>Product</th><th class="num">Sold</th><th class="num">In stock</th><th class="num">Money tied up</th></tr></thead>
                    <tbody>
                        @foreach ($slow as $row)
                            <tr>
                                <td>{{ $row['name'] }}<br><span style="opacity:.6; font-size:.8rem">{{ $row['category'] }}</span></td>
                                <td class="num">{{ $row['quantity'] }}</td>
                                <td class="num">{{ $row['stock'] }}</td>
                                <td class="num">{{ Money::tzs($row['stock_value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="mm-rep-empty">Nothing in stock.</p>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="Sales by product" :description="count($products) > 50 ? 'Top 50 shown. Export to Excel for the full list.' : 'Profit here is before shop-counter discounts.'">
        @if (count($products))
            <div class="mm-rep-scroll">
                <table class="mm-rep-table">
                    <thead><tr><th>Product</th><th>Category</th><th class="num">Sold</th><th class="num">Sales</th><th class="num">Cost</th><th class="num">Profit</th></tr></thead>
                    <tbody>
                        @foreach (array_slice($products, 0, 50) as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['category'] }}</td>
                                <td class="num">{{ $row['quantity'] }}</td>
                                <td class="num">{{ Money::tzs($row['sales']) }}</td>
                                <td class="num">{{ Money::tzs($row['cost']) }}</td>
                                <td @class(['num', 'neg' => $row['profit'] < 0])>{{ Money::tzs($row['profit']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mm-rep-empty">No sales in this period.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
