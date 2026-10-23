<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->order_number }}</title>
    <style>
        /* Sized for 80mm thermal receipt printers; also fine on A4. */
        body { font-family: "Courier New", ui-monospace, monospace; font-size: 12px; color: #000; background: #fff; margin: 0; }
        .receipt { width: 72mm; margin: 0 auto; padding: 6mm 0; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        .big { font-size: 15px; font-weight: bold; }
        .actions { text-align: center; margin: 16px 0; font-family: system-ui, sans-serif; }
        .actions button { padding: 10px 18px; font-size: 14px; cursor: pointer; }
        @media print { .actions { display: none; } @page { margin: 0; } }
    </style>
</head>
<body>
<div class="actions">
    <button onclick="window.print()">Print receipt</button>
    <button onclick="window.close()">Close</button>
</div>
<div class="receipt">
    <div class="center big">{{ $store['store_name'] ?? 'Mega Mall Tanzania' }}</div>
    <div class="center">{{ $store['phone'] ?? '' }}</div>
    <div class="rule"></div>
    <div class="row"><span>Receipt</span><span>{{ $order->order_number }}</span></div>
    <div class="row"><span>Date</span><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>
    @if ($order->servedBy)
        <div class="row"><span>Served by</span><span>{{ $order->servedBy->name }}</span></div>
    @endif
    @if ($order->customer_name && $order->customer_name !== 'Walk-in customer')
        <div class="row"><span>Customer</span><span>{{ $order->customer_name }}</span></div>
    @endif
    <div class="rule"></div>
    @foreach ($order->items as $item)
        <div>{{ $item->product_name }}{{ $item->variant_name ? ' ('.$item->variant_name.')' : '' }}</div>
        <div class="row"><span>{{ $item->quantity }} x {{ number_format($item->unit_price) }}</span><span>{{ number_format($item->line_total) }}</span></div>
    @endforeach
    <div class="rule"></div>
    <div class="row"><span>Subtotal</span><span>{{ number_format($order->subtotal) }}</span></div>
    @if ($order->discount)
        <div class="row"><span>Discount</span><span>-{{ number_format($order->discount) }}</span></div>
    @endif
    <div class="row big"><span>TOTAL TZS</span><span>{{ number_format($order->total) }}</span></div>
    @php($payment = $order->payments->first())
    @if ($payment)
        <div class="row"><span>Paid with</span><span>{{ $payment->channel === 'cash' ? 'Cash' : config('payments.channels.'.$payment->channel, ucfirst($payment->channel)) }}</span></div>
        @if ($payment->external_id)
            <div class="row"><span>Ref</span><span>{{ $payment->external_id }}</span></div>
        @endif
    @endif
    <div class="rule"></div>
    <div class="center">Asante kwa kununua!</div>
    <div class="center">Thank you for shopping with us.</div>
    <div class="center">Returns accepted within {{ $store['return_window_days'] ?? 7 }} days with this receipt.</div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
