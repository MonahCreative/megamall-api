@php
    $tzs = fn ($amount) => 'TZS '.number_format((int) $amount);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Packing slips ({{ $orders->count() }})</title>
    <style>
        /* One 100 x 150 mm label per order (standard parcel label). Prints one per page on A4 too. */
        @page { size: 100mm 150mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font: 12px/1.35 "Segoe UI", system-ui, -apple-system, sans-serif; color: #000; }
        .label { width: 100mm; height: 150mm; margin: 12px auto; background: #fff; padding: 5mm; display: flex; flex-direction: column; gap: 3mm; overflow: hidden; page-break-after: always; break-after: page; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 3mm; }
        .from { font-size: 10px; }
        .order { text-align: right; }
        .order .num { font-size: 17px; font-weight: 800; letter-spacing: .5px; }
        .to { border: 2px solid #000; border-radius: 3mm; padding: 3mm; }
        .tag { font-size: 9px; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; }
        .name { font-size: 18px; font-weight: 800; line-height: 1.15; }
        .phone { font-size: 20px; font-weight: 800; letter-spacing: .5px; margin: 1mm 0; }
        .region { font-size: 15px; font-weight: 800; text-transform: uppercase; }
        .money { border-radius: 3mm; padding: 2.5mm 3mm; text-align: center; font-weight: 800; }
        .money.collect { background: #000; color: #fff; font-size: 17px; }
        .money small { display: block; font-size: 9.5px; font-weight: 600; opacity: .85; }
        .money.paid { border: 2px dashed #000; font-size: 14px; }
        .items { flex: 1; overflow: hidden; }
        .items div { padding: 1mm 0; border-bottom: 1px dotted #999; }
        .box { display: inline-block; width: 3.2mm; height: 3.2mm; border: 1px solid #000; margin-right: 2mm; vertical-align: -0.5mm; }
        .notes { font-size: 11px; border-top: 1px solid #000; padding-top: 2mm; }
        .actions { text-align: center; margin: 14px; }
        .actions button { font: 14px system-ui; padding: 9px 16px; border-radius: 10px; border: 1px solid #9ca3af; background: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .label { margin: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions">
    <button onclick="window.print()">Print {{ $orders->count() }} label{{ $orders->count() === 1 ? '' : 's' }}</button>
    <button onclick="window.close()">Close</button>
</div>

@foreach ($orders as $order)
    @php
        $collect = ! $order->isPaid() && $order->payment_mode->value === 'cod';
    @endphp
    <div class="label">
        <div class="row">
            <div class="from">
                <strong>FROM: {{ $store['store_name'] ?? 'Mega Mall Tanzania' }}</strong><br>
                {{ $store['phone'] ?? '' }}
            </div>
            <div class="order">
                <div class="tag">Order</div>
                <div class="num">{{ $order->order_number }}</div>
                <div class="from">{{ $order->created_at->format('d M Y') }}</div>
            </div>
        </div>

        <div class="to">
            <div class="tag">Deliver to</div>
            <div class="name">{{ $order->customer_name }}</div>
            @if ($order->phone)
                <div class="phone">{{ \App\Support\Phone::display($order->phone) }}</div>
            @endif
            <div>{{ $order->address }}</div>
            <div>{{ collect([$order->area, $order->district?->name])->filter()->implode(', ') }}</div>
            <div class="region">{{ $order->region?->name }}</div>
        </div>

        @if ($collect)
            <div class="money collect">COLLECT {{ $tzs($order->total) }}<small>for the items · rider's delivery charge is separate</small></div>
        @else
            <div class="money paid">PAID · do not collect money for items</div>
        @endif

        <div class="items">
            <div class="tag">Packed ({{ $order->items->sum('quantity') }} item{{ $order->items->sum('quantity') === 1 ? '' : 's' }})</div>
            @foreach ($order->items as $item)
                <div><span class="box"></span>{{ $item->quantity }} × {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif</div>
            @endforeach
        </div>

        @if ($order->notes || $order->tracking_note)
            <div class="notes">
                @if ($order->notes) <strong>Customer note:</strong> {{ \Illuminate\Support\Str::limit($order->notes, 140) }}<br> @endif
                @if ($order->tracking_note) <strong>Delivery:</strong> {{ $order->tracking_note }} @endif
            </div>
        @endif
    </div>
@endforeach

<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
