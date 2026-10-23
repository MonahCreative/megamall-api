{{-- Shared by the dashboard widget and the Reorder list page. $rows from App\Services\Reports\ReorderList. --}}
<style>
    .mm-ro { width:100%; border-collapse:collapse; font-size:.88rem; }
    .mm-ro th { text-align:left; font-weight:600; opacity:.7; padding:8px 10px; border-bottom:1px solid rgba(127,127,127,.3); white-space:nowrap; }
    .mm-ro td { padding:8px 10px; border-bottom:1px solid rgba(127,127,127,.15); vertical-align:top; }
    .mm-ro .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .mm-ro .pill { display:inline-block; padding:1px 8px; border-radius:999px; font-size:.75rem; font-weight:600; }
    .mm-ro .out { background:rgba(220,38,38,.12); color:#dc2626; }
    .mm-ro .soon { background:rgba(234,179,8,.18); color:#a16207; }
    .mm-ro .low { background:rgba(127,127,127,.15); }
    .mm-ro .buy { font-weight:700; color:var(--primary-600); }
    .mm-ro .sub { opacity:.6; font-size:.78rem; }
</style>
<div style="overflow-x:auto">
    <table class="mm-ro">
        <thead>
            <tr>
                <th>Product</th>
                <th class="num">In stock</th>
                <th class="num">Sold (30 days)</th>
                <th class="num">Lasts</th>
                <th class="num">Order</th>
                <th>Last bought from</th>
                @if ($showCost ?? true)
                    <th class="num">Est. cost</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        {{ $row['name'] }}
                        <div class="sub">
                            {{ $row['category'] }} · reorder at {{ $row['level'] }}
                            @if ($row['customers_waiting'] > 0)
                                · <strong>{{ $row['customers_waiting'] }} customer{{ $row['customers_waiting'] === 1 ? '' : 's' }} waiting</strong>
                            @endif
                        </div>
                    </td>
                    <td class="num">
                        @if ($row['stock'] <= 0)
                            <span class="pill out">Out of stock</span>
                        @else
                            {{ $row['stock'] }}
                        @endif
                    </td>
                    <td class="num">{{ $row['sold_30_days'] }}</td>
                    <td class="num">
                        @if ($row['days_left'] === null)
                            <span class="pill low">No recent sales</span>
                        @elseif ($row['stock'] <= 0)
                            –
                        @else
                            <span @class(['pill', 'soon' => $row['days_left'] <= 7, 'low' => $row['days_left'] > 7])>{{ $row['days_left'] }} day{{ $row['days_left'] === 1 ? '' : 's' }}</span>
                        @endif
                    </td>
                    <td class="num buy">{{ $row['suggested'] }}</td>
                    <td>{{ $row['supplier'] ?? '–' }}</td>
                    @if ($showCost ?? true)
                        <td class="num">{{ $row['estimated_cost'] ? \App\Support\Money::tzs($row['estimated_cost']) : '–' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
