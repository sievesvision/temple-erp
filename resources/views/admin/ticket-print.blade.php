<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Tickets — Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: #f2f2f2; }
        .toolbar { max-width: 380px; margin: 0 auto 16px; text-align: center; }
        .toolbar button { background: #6B0F1A; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .stub {
            width: 350px; margin: 0 auto 16px; background: #fff; border: 2px dashed #999; border-radius: 10px;
            padding: 18px 20px; page-break-after: always;
        }
        .stub-temple { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #6B0F1A; font-weight: 700; margin-bottom: 4px; }
        .stub-title { font-size: 1.3rem; font-weight: 800; margin: 4px 0; }
        .stub-price { font-size: 1.6rem; font-weight: 800; font-family: 'IBM Plex Mono', 'Consolas', monospace; margin: 8px 0; }
        .stub-meta { font-size: 0.85rem; color: #444; margin: 2px 0; }
        .stub-number { font-family: 'IBM Plex Mono', 'Consolas', monospace; font-size: 1rem; font-weight: 700; letter-spacing: 0.05em; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 8px; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .stub { border: 1px dashed #999; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print {{ $order->items->sum('quantity') }} Ticket(s)</button>
    </div>

    @foreach($order->items as $item)
        @foreach($item->stubs as $stub)
        <div class="stub">
            <div class="stub-temple">{{ $temple['name'] ?? 'Temple' }}</div>
            <div class="stub-title">{{ $item->ticket_name }}</div>
            <div class="stub-price">{{ $temple['currency'] ?? '' }} {{ number_format($item->unit_price, 2) }}</div>
            <div class="stub-meta">Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }} &middot; {{ $order->order_date->format('d M Y') }}</div>
            @if($order->customer_name)
            <div class="stub-meta">{{ $order->customer_name }}</div>
            @endif
            <div class="stub-number">{{ $stub->stub_number }}</div>
        </div>
        @endforeach
    @endforeach
</body>
</html>
