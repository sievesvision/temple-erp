<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Tickets — Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: #ddd; color: #000; }

        /* ---------- On-screen toolbar (hidden when actually printed) ---------- */
        .toolbar { max-width: 400px; margin: 0 auto 16px; text-align: center; background: #fff; border-radius: 10px; padding: 14px 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
        .toolbar button { background: #6B0F1A; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.95rem; }
        .toolbar .width-picker { display: flex; justify-content: center; gap: 8px; margin-bottom: 12px; }
        .toolbar .width-picker button { background: #fff; color: #6B0F1A; border: 1.5px solid #6B0F1A; padding: 6px 16px; font-size: 0.85rem; border-radius: 20px; }
        .toolbar .width-picker button.active { background: #6B0F1A; color: #fff; }
        .toolbar p { font-size: 0.78rem; color: #666; margin: 10px 0 0; }

        /* ---------- Thermal receipt stub ---------- */
        /* Width matches the paper exactly (58mm or 80mm — the two standard thermal receipt
           roll widths); @page below is what actually controls the physical printed size —
           this on-screen width just previews it at the same proportions. Monochrome by
           design (no colour, no gradients): thermal printers are black-on-white only, so
           legibility here comes purely from size/weight/contrast, never colour. */
        .stub-page { page-break-after: always; }
        .stub-page:last-child { page-break-after: auto; }
        .stub {
            width: var(--paper-width, 80mm); margin: 0 auto 16px; background: #fff;
            padding: 4mm 3mm; font-family: 'Consolas', 'Courier New', monospace;
        }
        .stub-temple { text-align: center; font-weight: 800; font-size: 1.05rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .stub-temple-sub { text-align: center; font-size: 0.75rem; margin-top: 1px; }
        .stub-divider { border-top: 1px dashed #000; margin: 6px 0; }
        .stub-title { text-align: center; font-weight: 800; font-size: 1.3rem; margin: 6px 0 2px; text-transform: uppercase; word-break: break-word; }
        .stub-price { text-align: center; font-weight: 800; font-size: 1.6rem; margin: 4px 0; }
        .stub-meta { font-size: 0.78rem; text-align: center; margin: 2px 0; }
        .stub-number { text-align: center; font-weight: 800; font-size: 1.05rem; letter-spacing: 0.06em; margin-top: 8px; padding-top: 6px; border-top: 1px dashed #000; }
        .stub-cut-hint { text-align: center; font-size: 0.68rem; color: #999; margin-top: 4px; }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .stub { margin: 0 auto; }
            .stub-cut-hint { display: none; }
        }
    </style>
    <style id="pageSizeStyle">@page { size: 80mm auto; margin: 0; }</style>
</head>
<body>
    <div class="toolbar">
        <div class="width-picker">
            <button type="button" class="active" data-width="80mm" onclick="setPaperWidth('80mm', this)">80mm</button>
            <button type="button" data-width="58mm" onclick="setPaperWidth('58mm', this)">58mm</button>
        </div>
        <button onclick="window.print()">Print {{ $order->items->sum('quantity') }} Ticket(s)</button>
        <p>Choose the width that matches your thermal printer's paper roll before printing. No thermal printer handy? Print to <strong>"Save as PDF"</strong> — the PDF comes out at the exact paper width selected, so you can preview precisely what the physical printer would produce.</p>
    </div>

    @foreach($order->items as $item)
        @foreach($item->stubs as $stub)
        <div class="stub-page">
            <div class="stub">
                <div class="stub-temple">{{ $temple['name'] ?? 'Temple' }}</div>
                @if($temple['subtitle'] ?? null)
                <div class="stub-temple-sub">{{ $temple['subtitle'] }}</div>
                @endif
                <div class="stub-divider"></div>
                <div class="stub-title">{{ $item->ticket_name }}</div>
                <div class="stub-price">{{ $temple['currency'] ?? '' }} {{ number_format($item->unit_price, 2) }}</div>
                <div class="stub-meta">Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }} &middot; {{ $order->order_date->format('d M Y') }}</div>
                @if($order->customer_name)
                <div class="stub-meta">{{ $order->customer_name }}</div>
                @endif
                <div class="stub-number">{{ $stub->stub_number }}</div>
                <div class="stub-cut-hint">- - - - - cut here - - - - -</div>
            </div>
        </div>
        @endforeach
    @endforeach

    <script>
        function setPaperWidth(width, btn) {
            document.documentElement.style.setProperty('--paper-width', width);
            document.getElementById('pageSizeStyle').textContent = '@page { size: ' + width + ' auto; margin: 0; }';
            document.querySelectorAll('.width-picker button').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
        }
    </script>
</body>
</html>
