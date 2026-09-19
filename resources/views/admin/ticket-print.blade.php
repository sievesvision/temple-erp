<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Tickets — Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</title>
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; margin: 0; padding: 24px; background: #ececec; }
        .toolbar { max-width: 680px; margin: 0 auto 20px; text-align: center; }
        .toolbar button { background: #6B0F1A; color: #fff; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.95rem; }

        /* ---------- Ticket stub — one per printed unit, page-break-after so the browser's
           print dialog puts each on its own sheet. Each ticket type's own background_color
           (see App\Models\Ticket) supplies the accent used throughout: a light tint for the
           card background (via color-mix), the deep tone for borders/text/price badge — so
           every archana type gets a visually distinct, correctly-themed stub automatically,
           without a second "print colour" field to keep in sync. ---------- */
        .stub-page { page-break-after: always; }
        .stub-page:last-child { page-break-after: auto; }
        .stub {
            width: 680px; margin: 0 auto 24px; border-radius: 14px; padding: 10px;
            background: color-mix(in srgb, var(--accent) 10%, white);
            border: 2px solid var(--accent);
        }
        .stub-frame {
            position: relative; border: 1.5px solid color-mix(in srgb, var(--accent) 55%, white);
            border-radius: 10px; padding: 22px 30px; overflow: hidden;
            background: color-mix(in srgb, var(--accent) 5%, white);
        }
        .stub-corner { position: absolute; width: 20px; height: 20px; border: var(--accent) solid; opacity: 0.6; }
        .stub-corner.tl { top: 6px; left: 6px; border-width: 2px 0 0 2px; }
        .stub-corner.tr { top: 6px; right: 6px; border-width: 2px 2px 0 0; }
        .stub-corner.bl { bottom: 6px; left: 6px; border-width: 0 0 2px 2px; }
        .stub-corner.br { bottom: 6px; right: 6px; border-width: 0 2px 2px 0; }

        .stub-temple-name { text-align: center; font-family: 'Playfair Display', Georgia, serif; font-weight: 800; font-size: 1.5rem; letter-spacing: 0.03em; color: var(--accent); text-transform: uppercase; }
        .stub-temple-sub { text-align: center; font-weight: 700; font-size: 0.78rem; letter-spacing: 0.18em; color: var(--accent); text-transform: uppercase; opacity: 0.8; margin-top: 2px; }
        .stub-divider { width: 160px; height: 1px; background: color-mix(in srgb, var(--accent) 45%, white); margin: 10px auto 16px; }

        .stub-body { display: flex; align-items: center; gap: 24px; }
        .stub-image-wrap {
            flex-shrink: 0; width: 110px; height: 110px; border-radius: 50%;
            background: color-mix(in srgb, var(--accent) 14%, white);
            border: 2px solid color-mix(in srgb, var(--accent) 50%, white);
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .stub-image-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .stub-title-wrap { flex: 1; min-width: 0; text-align: center; }
        .stub-title { font-family: 'Playfair Display', Georgia, serif; font-weight: 800; font-size: 2rem; line-height: 1.15; color: var(--accent); text-transform: uppercase; }
        .stub-price-badge {
            flex-shrink: 0; background: var(--accent); color: #fff; border-radius: 10px;
            padding: 14px 22px; text-align: center; border: 2px solid color-mix(in srgb, var(--accent) 70%, black);
        }
        .stub-price-badge .amt { font-family: 'IBM Plex Mono', 'Consolas', monospace; font-weight: 700; font-size: 1.7rem; white-space: nowrap; }

        .stub-mantra { text-align: center; font-weight: 700; font-size: 0.92rem; letter-spacing: 0.08em; color: var(--accent); margin-top: 18px; }
        .stub-meta-row {
            display: flex; justify-content: space-between; align-items: center; margin-top: 14px; padding-top: 10px;
            border-top: 1px dashed color-mix(in srgb, var(--accent) 40%, white);
            font-family: 'IBM Plex Mono', 'Consolas', monospace; font-size: 0.78rem; color: color-mix(in srgb, var(--accent) 80%, black);
        }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .stub { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print {{ $order->items->sum('quantity') }} Ticket(s)</button>
    </div>

    @foreach($order->items as $item)
        @php
            $accent = $item->ticket->background_color ?? '#6B0F1A';
            $image = $item->ticket->image ?? null;
        @endphp
        @foreach($item->stubs as $stub)
        <div class="stub-page">
            <div class="stub" style="--accent: {{ $accent }};">
                <div class="stub-frame">
                    <div class="stub-corner tl"></div>
                    <div class="stub-corner tr"></div>
                    <div class="stub-corner bl"></div>
                    <div class="stub-corner br"></div>

                    <div class="stub-temple-name">{{ $temple['name'] ?? 'Temple' }}</div>
                    @if($temple['subtitle'] ?? null)
                    <div class="stub-temple-sub">{{ $temple['subtitle'] }}</div>
                    @endif
                    <div class="stub-divider"></div>

                    <div class="stub-body">
                        <div class="stub-image-wrap">
                            @if($image)
                            <img src="{{ $image }}" alt="">
                            @elseif($temple['logo'] ?? null)
                            <img src="{{ $temple['logo'] }}" alt="">
                            @endif
                        </div>
                        <div class="stub-title-wrap">
                            <div class="stub-title">{{ $item->ticket_name }}</div>
                        </div>
                        <div class="stub-price-badge">
                            <div class="amt">{{ $temple['currency'] ?? '' }} {{ number_format($item->unit_price, 2) }}</div>
                        </div>
                    </div>

                    <div class="stub-mantra">&#2384; GANAPATHAYE NAMAHA &#2384;</div>

                    <div class="stub-meta-row">
                        <span>Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }} &middot; {{ $order->order_date->format('d M Y') }}{{ $order->customer_name ? ' · ' . $order->customer_name : '' }}</span>
                        <span>{{ $stub->stub_number }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    @endforeach
</body>
</html>
