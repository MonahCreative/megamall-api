@php
    $brand = \App\Support\Theme::colours()['theme_primary'];
    $paid = $order->isPaid() || $order->payment_status->value === 'refunded';
    $payment = $order->payments->firstWhere('status', \App\Enums\PaymentStatus::Paid);
    $refunded = (int) $order->refunds->sum('amount');
    $address = collect([$order->address, $order->area, $order->district?->name, $order->region?->name])->filter()->implode(', ');
    $tzs = fn ($amount) => 'TZS '.number_format((int) $amount);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $paid ? 'Receipt' : 'Invoice' }} {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111827; font: 14px/1.45 "Segoe UI", system-ui, -apple-system, sans-serif; }
        .sheet { max-width: 210mm; margin: 24px auto; background: #fff; padding: 16mm 14mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .top { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; }
        .logo { display: flex; gap: 12px; align-items: center; }
        .mark { width: 48px; height: 48px; border-radius: 12px; background: {{ $brand }}; color: #fff; display: grid; place-items: center; font-weight: 900; font-size: 18px; letter-spacing: -1px; }
        .shop { font-size: 20px; font-weight: 800; }
        .muted { color: #6b7280; }
        .small { font-size: 12px; }
        .doc { text-align: right; }
        .doc h1 { margin: 0; font-size: 26px; letter-spacing: 1px; color: {{ $brand }}; }
        .stamp { display: inline-block; margin-top: 8px; padding: 4px 12px; border: 2px solid; border-radius: 8px; font-weight: 800; letter-spacing: 1px; font-size: 13px; }
        .stamp.paid { color: #15803d; border-color: #15803d; }
        .stamp.due { color: #b45309; border-color: #b45309; }
        .stamp.cancelled { color: #b91c1c; border-color: #b91c1c; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 28px 0 22px; }
        .label { text-transform: uppercase; font-size: 11px; letter-spacing: .08em; color: #6b7280; margin-bottom: 4px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; border-bottom: 2px solid #111827; padding: 8px 6px; }
        td { padding: 10px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .totals { margin-left: auto; width: 300px; margin-top: 14px; }
        .totals td { border: 0; padding: 4px 6px; }
        .totals .grand td { border-top: 2px solid #111827; font-size: 17px; font-weight: 800; padding-top: 8px; }
        .note { margin-top: 26px; padding: 12px 14px; border-radius: 10px; background: #f9fafb; font-size: 12.5px; }
        .foot { margin-top: 26px; text-align: center; font-size: 12px; color: #6b7280; }
        .actions { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
        .actions button { font: inherit; padding: 9px 16px; border-radius: 10px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; }
        .actions .primary { background: {{ $brand }}; border-color: {{ $brand }}; color: #fff; font-weight: 600; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
<div class="actions">
    <button onclick="window.close()">Close</button>
    <button class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="sheet">
    <div class="top">
        <div class="logo">
            <div class="mark">MM</div>
            <div>
                <div class="shop">{{ $store['store_name'] ?? 'Mega Mall Tanzania' }}</div>
                <div class="muted small">
                    @if (! empty($store['business_address'])) {{ $store['business_address'] }}<br> @endif
                    {{ $store['phone'] ?? '' }}@if (! empty($store['email'])) · {{ $store['email'] }} @endif
                    @if (! empty($store['tin'])) <br>TIN: {{ $store['tin'] }} @endif
                </div>
            </div>
        </div>
        <div class="doc">
            <h1>{{ $paid ? 'RECEIPT' : 'INVOICE' }}</h1>
            <div><strong>{{ $order->order_number }}</strong></div>
            <div class="muted small">Date: {{ $order->created_at->format('d M Y') }}</div>
            @if ($order->status->value === 'cancelled')
                <span class="stamp cancelled">CANCELLED</span>
            @elseif ($paid)
                <span class="stamp paid">PAID</span>
            @else
                <span class="stamp due">{{ $order->payment_mode->value === 'cod' ? 'PAY ON DELIVERY' : 'AWAITING PAYMENT' }}</span>
            @endif
        </div>
    </div>

    <div class="parties">
        <div>
            <div class="label">{{ $order->isPos() ? 'Customer' : 'Deliver to' }}</div>
            <strong>{{ $order->customer_name }}</strong><br>
            @if ($order->phone) {{ \App\Support\Phone::display($order->phone) }}<br> @endif
            @if ($order->email) {{ $order->email }}<br> @endif
            @if ($address) {{ $address }} @endif
        </div>
        <div>
            <div class="label">Payment</div>
            {{ $order->payment_mode->label() }}<br>
            @if ($payment)
                Paid {{ $tzs($payment->amount) }} with {{ \App\Support\PaymentChannels::label($payment->channel) }}
                on {{ ($payment->paid_at ?? $payment->created_at)->format('d M Y') }}<br>
                @if ($payment->external_id) <span class="muted small">Ref: {{ $payment->external_id }}</span> @endif
            @elseif (! $paid)
                <span class="muted">Not paid yet</span>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}@if ($item->variant_name) <span class="muted">({{ $item->variant_name }})</span> @endif</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $tzs($item->unit_price) }}</td>
                    <td class="num">{{ $tzs($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $tzs($order->subtotal) }}</td></tr>
        @if ($order->discount > 0)
            <tr><td>Discount</td><td class="num">-{{ $tzs($order->discount) }}</td></tr>
        @endif
        @if ($order->delivery_fee > 0)
            <tr><td>Delivery</td><td class="num">{{ $tzs($order->delivery_fee) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="num">{{ $tzs($order->total) }}</td></tr>
        @if ($refunded > 0)
            <tr><td>Refunded</td><td class="num">-{{ $tzs($refunded) }}</td></tr>
        @endif
    </table>

    @unless ($order->isPos())
        <div class="note">
            {{ $store['delivery_note'] ?? 'Delivery is paid to the rider or bus company directly and is not included in this total.' }}
        </div>
    @endunless

    <div class="foot">
        Asante kwa kununua! Returns accepted within {{ (int) ($store['return_window_days'] ?? 7) }} days of delivery.
    </div>
</div>
</body>
</html>
