<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Ticket Kiosk</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF; --border: #F0E5D6; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; height: 100%; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); display: flex; flex-direction: column; }
        button, input, select, textarea { font-family: inherit; }

        .pos-topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 12px 20px; display: flex; align-items: center; gap: 14px; flex-shrink: 0; box-shadow: 0 4px 18px rgba(74,10,18,0.25); z-index: 20; }
        .pos-topbar-title { flex: 1; min-width: 0; }
        .pos-topbar-title h1 { font-size: clamp(1.05rem, 2.6vw, 1.35rem); font-weight: 800; color: var(--gold); margin: 0; }
        .pos-topbar-title .pos-subtitle { font-size: 0.7rem; color: rgba(255,255,255,0.65); text-transform: uppercase; letter-spacing: 0.06em; }
        .pos-topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; width: 42px; height: 42px; border-radius: 12px; font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .pos-topbar-btn:hover { background: rgba(255,255,255,0.22); }
        .pos-terminal-btn { background: rgba(255,255,255,0.12); border: none; color: white; height: 42px; padding: 0 14px; border-radius: 12px; font-size: 0.82rem; font-weight: 700; flex-shrink: 0; display: flex; align-items: center; gap: 8px; max-width: 160px; }
        .pos-terminal-btn:hover { background: rgba(255,255,255,0.22); }
        .pos-terminal-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* At-a-glance online/offline dot on the terminal picker button — so the operator can
           tell a terminal has gone offline without opening the picker. */
        .terminal-status-dot { width: 9px; height: 9px; border-radius: 50%; background: #9AA7B4; flex-shrink: 0; }
        .terminal-status-dot.online { background: #34D399; box-shadow: 0 0 0 3px rgba(52,211,153,0.3); }
        .terminal-status-dot.offline { background: #F87171; box-shadow: 0 0 0 3px rgba(248,113,113,0.3); }
        .terminal-status-dot.unknown { background: #FBBF24; }

        /* ---------- Full-width kiosk: items grid on the left, cart panel on the right ---------- */
        .pos-body { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        @media (min-width: 900px) { .pos-body { flex-direction: row; } }

        .pos-items-pane { flex: 1; min-height: 0; overflow-y: auto; padding: 18px; }
        .pos-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }

        /* Each tile is a miniature version of the temple's own printed ticket design — an
           ornate bordered card themed by the ticket's own background_color (see App\Models\
           Ticket), with a circular image frame, the ticket name, and a price badge — rather
           than a plain photo-background button, so the kiosk itself looks like the physical
           tickets it's selling. */
        .pos-item-tile {
            position: relative; border-radius: 14px; padding: 8px; border: none; cursor: pointer;
            text-align: center; background: color-mix(in srgb, var(--tile-accent) 12%, white);
            box-shadow: 0 2px 8px rgba(31,42,55,0.08); transition: transform 0.1s;
        }
        .pos-item-tile:active { transform: scale(0.97); }
        .pos-item-tile.in-cart { box-shadow: 0 0 0 3px var(--tile-accent), 0 4px 14px rgba(31,42,55,0.16); }
        .tile-frame {
            position: relative; border: 1.5px solid color-mix(in srgb, var(--tile-accent) 55%, white);
            border-radius: 10px; padding: 14px 10px 12px; background: color-mix(in srgb, var(--tile-accent) 4%, white);
            display: flex; flex-direction: column; align-items: center; gap: 6px;
        }
        .tile-corner { position: absolute; width: 14px; height: 14px; border: var(--tile-accent) solid; opacity: 0.6; }
        .tile-corner.tl { top: 4px; left: 4px; border-width: 2px 0 0 2px; }
        .tile-corner.tr { top: 4px; right: 4px; border-width: 2px 2px 0 0; }
        .tile-corner.bl { bottom: 4px; left: 4px; border-width: 0 0 2px 2px; }
        .tile-corner.br { bottom: 4px; right: 4px; border-width: 0 2px 2px 0; }
        .tile-image-wrap {
            width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
            background: color-mix(in srgb, var(--tile-accent) 16%, white);
            border: 2px solid color-mix(in srgb, var(--tile-accent) 50%, white);
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .tile-image-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .tile-image-wrap i { font-size: 1.6rem; color: var(--tile-accent); }
        .tile-name { font-family: 'Playfair Display', Georgia, serif; font-weight: 800; font-size: 0.95rem; line-height: 1.2; color: var(--tile-accent); text-transform: uppercase; min-height: 2.3em; display: flex; align-items: center; }
        .tile-price-badge { background: var(--tile-accent); color: #fff; border-radius: 8px; padding: 5px 14px; font-family: 'IBM Plex Mono', monospace; font-weight: 700; font-size: 0.88rem; }
        .tile-mantra { font-size: 0.62rem; font-weight: 700; letter-spacing: 0.06em; color: color-mix(in srgb, var(--tile-accent) 80%, black); opacity: 0.75; }
        .tile-qty-badge { position: absolute; top: -8px; right: -8px; background: var(--tile-accent); color: #fff; font-weight: 800; font-size: 0.85rem; min-width: 28px; height: 28px; border-radius: 50%; display: none; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.3); border: 2px solid #fff; }
        .pos-item-tile.in-cart .tile-qty-badge { display: flex; }

        .pos-cart-pane { width: 100%; flex-shrink: 0; background: var(--white); border-left: 1px solid var(--border); display: flex; flex-direction: column; min-height: 0; }
        @media (min-width: 900px) { .pos-cart-pane { width: 400px; } }
        .pos-cart-header { padding: 16px 18px 8px; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; }
        .pos-cart-list { flex: 1; min-height: 80px; overflow-y: auto; padding: 0 18px; }
        .pos-cart-empty { text-align: center; color: var(--text-secondary); padding: 30px 10px; }
        .cart-line { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .cart-line-info { flex: 1; min-width: 0; }
        .cart-line-name { font-weight: 700; font-size: 0.92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cart-line-unit { font-size: 0.76rem; color: var(--text-secondary); font-family: 'IBM Plex Mono', monospace; }
        .cart-line-qty-controls { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .cart-qty-btn { width: 30px; height: 30px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--white); font-size: 1rem; font-weight: 800; color: var(--gold-hover); }
        .cart-qty-input { width: 40px; text-align: center; border: 1.5px solid var(--border); border-radius: 8px; padding: 4px 2px; font-weight: 700; font-family: 'IBM Plex Mono', monospace; }
        .cart-line-total { width: 66px; text-align: right; font-weight: 700; font-family: 'IBM Plex Mono', monospace; font-size: 0.88rem; flex-shrink: 0; }
        .cart-line-remove { width: 30px; height: 30px; border-radius: 8px; border: none; background: var(--error); color: #fff; font-size: 0.85rem; flex-shrink: 0; }

        .pos-cart-footer { flex-shrink: 0; padding: 12px 18px 16px; border-top: 2px solid var(--border); }
        .cart-total-row { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.3rem; color: var(--text-primary); padding: 6px 0 12px; }
        .cart-total-row span:last-child { font-family: 'IBM Plex Mono', 'Inter', monospace; }

        .pos-field-label { display: block; font-weight: 700; font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .pos-input { width: 100%; padding: 9px 10px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.88rem; font-weight: 600; color: var(--text-primary); background: var(--white); min-height: 38px; }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
        .pos-row.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }

        .pos-method-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .pos-method-btn { flex: 1 1 calc(50% - 8px); min-width: 90px; padding: 10px 8px; min-height: 46px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); font-weight: 700; font-size: 0.82rem; color: var(--text-secondary); }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.3); }
        .pos-method-btn i { display: block; font-size: 1.1rem; margin-bottom: 2px; }

        .pos-save-btn { width: 100%; padding: 16px; border-radius: 14px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 1.1rem; box-shadow: 0 10px 26px rgba(200,155,60,0.35); min-height: 56px; }
        .pos-save-btn:disabled { opacity: 0.55; }
        .pos-save-btn:active { transform: scale(0.98); }

        .pos-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 18px 26px; border-radius: 14px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 14px 34px rgba(0,0,0,0.2); z-index: 999; display: none; }
        .pos-toast.error { background: var(--error); }

        /* ---------- Quantity picker modal (opened by tapping an item tile) ---------- */
        .qty-modal-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 900; display: none; align-items: center; justify-content: center; padding: 20px; }
        .qty-modal-overlay.active { display: flex; }
        .qty-modal { background: var(--white); border-radius: 22px; width: 100%; max-width: 340px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; }
        .qty-modal-header { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 16px 20px; font-weight: 800; font-size: 1.05rem; }
        .qty-modal-body { padding: 24px 22px; }
        .qty-modal-price { color: var(--gold-hover); font-weight: 700; font-family: 'IBM Plex Mono', monospace; margin-bottom: 18px; }
        .qty-modal-controls { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 22px; }
        .qty-modal-btn { width: 54px; height: 54px; border-radius: 16px; border: 2px solid var(--border); background: var(--cream); font-size: 1.6rem; font-weight: 800; color: var(--gold-hover); }
        .qty-modal-value { font-size: 2rem; font-weight: 800; font-family: 'IBM Plex Mono', monospace; min-width: 60px; }
        .qty-modal-actions { display: flex; gap: 10px; }
        .qty-modal-cancel { flex: 1; padding: 13px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; }
        .qty-modal-confirm { flex: 2; padding: 13px; border-radius: 12px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; }

        .eft-modal-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal { background: var(--white); border-radius: 22px; width: 100%; max-width: 380px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; }
        .eft-modal-header { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em; font-size: 0.95rem; text-transform: uppercase; }
        .eft-modal-body { padding: 30px 26px 26px; }
        .eft-modal-amount { font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; font-size: 2.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 18px; }
        .eft-modal-status-box { background: var(--cream); border: 2px solid var(--border); border-radius: 14px; padding: 18px 16px; min-height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; margin-bottom: 22px; }
        .eft-modal-spinner { width: 26px; height: 26px; border-radius: 50%; border: 3px solid rgba(200,155,60,0.25); border-top-color: var(--gold); animation: eftSpin 0.8s linear infinite; margin-bottom: 4px; display: none; }
        .eft-modal-status-box.pending .eft-modal-spinner { display: block; }
        .eft-modal-status-icon { font-size: 1.6rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error { display: block; color: var(--error); }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { color: var(--error); }
        @keyframes eftSpin { to { transform: rotate(360deg); } }
        .eft-modal-cancel-btn { width: 100%; padding: 14px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem; }
        .eft-modal-cancel-btn:active { background: var(--cream); }
        .eft-modal-keys { display: none; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .eft-modal-keys.active { display: flex; }
        .eft-modal-key-btn { flex: 1 1 auto; min-width: 90px; padding: 13px 10px; border-radius: 12px; border: 2px solid transparent; font-weight: 700; font-size: 0.95rem; color: #fff; }
        .eft-modal-key-btn:active { filter: brightness(0.92); }
        .eft-modal-key-btn.key-ok, .eft-modal-key-btn.key-yes, .eft-modal-key-btn.key-authorise { background: var(--success); }
        .eft-modal-key-btn.key-no { background: var(--error); }
    </style>
</head>
<body>
    <header class="pos-topbar">
        <div class="pos-topbar-title">
            <h1>Ticket Kiosk</h1>
            <div class="pos-subtitle">Sell &amp; Print Tickets</div>
        </div>
        <button type="button" class="pos-terminal-btn" id="terminalPickerBtn" title="This station's EFT terminal">
            <i class="bi bi-credit-card-2-front-fill"></i><span class="terminal-status-dot" id="terminalStatusDot" title="Terminal status"></span><span id="terminalPickerLabel">Terminal</span>
        </button>
        <button type="button" class="pos-topbar-btn" id="posFullscreenBtn" title="Toggle fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
        @if($canManageConsole)
        <a href="{{ route('admin.tickets.index') }}" class="pos-topbar-btn" title="Ticket Console"><i class="bi bi-grid-1x2-fill"></i></a>
        @endif
        <a href="{{ route('logout') }}" class="pos-topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </header>

    <div class="pos-body">
        <div class="pos-items-pane">
            <div class="pos-items-grid" id="itemsGrid">
                @forelse($tickets as $ticket)
                @php $accent = $ticket->background_color ?: '#6B0F1A'; @endphp
                <button type="button" class="pos-item-tile" data-id="{{ $ticket->id }}" data-name="{{ $ticket->name }}" data-price="{{ $ticket->price }}"
                    style="--tile-accent: {{ $accent }};">
                    <span class="tile-qty-badge">0</span>
                    <span class="tile-frame">
                        <span class="tile-corner tl"></span>
                        <span class="tile-corner tr"></span>
                        <span class="tile-corner bl"></span>
                        <span class="tile-corner br"></span>
                        <span class="tile-image-wrap">
                            @if($ticket->image)
                            <img src="{{ $ticket->image }}" alt="">
                            @elseif($temple['logo'] ?? null)
                            <img src="{{ $temple['logo'] }}" alt="">
                            @else
                            <i class="bi bi-flower1"></i>
                            @endif
                        </span>
                        <span class="tile-name">{{ $ticket->name }}</span>
                        <span class="tile-price-badge">{{ $temple['currency'] ?? '' }} {{ number_format($ticket->price, 2) }}</span>
                        <span class="tile-mantra">&#2384; GANAPATHAYE NAMAHA</span>
                    </span>
                </button>
                @empty
                <p class="text-muted text-center py-4">No active ticket types — add one in the Ticket Console.</p>
                @endforelse
            </div>
        </div>

        <div class="pos-cart-pane">
            <div class="pos-cart-header"><i class="bi bi-cart-fill me-2"></i>Order</div>
            <div class="pos-cart-list" id="cartList">
                <div class="pos-cart-empty" id="cartEmptyMsg">Tap a ticket to add it to the order.</div>
            </div>
            <div class="pos-cart-footer">
                <div class="cart-total-row"><span>Total</span><span id="cartTotal">{{ $temple['currency'] ?? '' }} 0.00</span></div>

                <div class="pos-row two-col">
                    <div>
                        <label class="pos-field-label">Name</label>
                        <input type="text" class="pos-input" id="ticketCustomerName" placeholder="Optional" autocomplete="off">
                    </div>
                    <div>
                        <label class="pos-field-label">Mobile</label>
                        <input type="text" class="pos-input" id="ticketCustomerMobile" placeholder="Optional" autocomplete="off">
                    </div>
                </div>

                <div class="pos-method-row" id="posMethodRow"></div>

                @if($canSell)
                <button type="button" class="pos-save-btn" id="posSaveBtn"><i class="bi bi-printer-fill me-2"></i>Complete Sale &amp; Print</button>
                @else
                <button type="button" class="pos-save-btn" disabled title="View-only access"><i class="bi bi-eye-fill me-2"></i>View Only</button>
                @endif
            </div>
        </div>
    </div>

    <div class="pos-toast" id="posToast"></div>

    <div id="eftResumeBanner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:2000; background:#7a1f1f; color:#fff; padding:12px 18px; align-items:center; gap:14px; flex-wrap:wrap; justify-content:center;">
        <span id="eftResumeBannerText"></span>
        <button type="button" id="eftResumeBannerDismissBtn" style="background:transparent; color:#fff; border:1px solid #fff; border-radius:8px; padding:6px 16px;">Dismiss</button>
    </div>

    <!-- Quantity picker — opened by tapping an item tile -->
    <div class="qty-modal-overlay" id="qtyModalOverlay">
        <div class="qty-modal">
            <div class="qty-modal-header" id="qtyModalTitle">Ticket</div>
            <div class="qty-modal-body">
                <div class="qty-modal-price" id="qtyModalPrice"></div>
                <div class="qty-modal-controls">
                    <button type="button" class="qty-modal-btn" id="qtyModalMinus">−</button>
                    <span class="qty-modal-value" id="qtyModalValue">1</span>
                    <button type="button" class="qty-modal-btn" id="qtyModalPlus">+</button>
                </div>
                <div class="qty-modal-actions">
                    <button type="button" class="qty-modal-cancel" id="qtyModalCancel">Cancel</button>
                    <button type="button" class="qty-modal-confirm" id="qtyModalConfirm">Add to Order</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Terminal picker — which EFT terminal THIS station uses, saved per-browser so two
         computers can each pick a different one and run concurrent counters. -->
    <div class="qty-modal-overlay" id="terminalModalOverlay">
        <div class="qty-modal">
            <div class="qty-modal-header">This Station's EFT Terminal</div>
            <div class="qty-modal-body">
                <div id="terminalModalList" style="display:flex; flex-direction:column; gap:10px; margin-bottom:18px;"></div>
                <div class="qty-modal-actions">
                    <button type="button" class="qty-modal-cancel" id="terminalModalCancel">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="eft-modal-overlay" id="eftModalOverlay">
        <div class="eft-modal">
            <div class="eft-modal-header"><i class="bi bi-credit-card-2-front-fill me-2"></i>Card Payment</div>
            <div class="eft-modal-body">
                <div class="eft-modal-amount" id="eftModalAmount">{{ $temple['currency'] ?? '' }} 0.00</div>
                <div class="eft-modal-status-box pending" id="eftModalStatusBox">
                    <div class="eft-modal-spinner"></div>
                    <i class="bi bi-check-circle-fill eft-modal-status-icon icon-success"></i>
                    <i class="bi bi-x-circle-fill eft-modal-status-icon icon-error"></i>
                    <span class="eft-modal-status-line" id="eftModalStatusLine1">Starting…</span>
                    <span class="eft-modal-status-line" id="eftModalStatusLine2"></span>
                </div>
                <div class="eft-modal-keys" id="eftModalKeys">
                    <button type="button" class="eft-modal-key-btn key-yes" id="eftModalKeyYes">Yes</button>
                    <button type="button" class="eft-modal-key-btn key-ok" id="eftModalKeyOk">OK</button>
                    <button type="button" class="eft-modal-key-btn key-no" id="eftModalKeyNo">No</button>
                    <button type="button" class="eft-modal-key-btn key-authorise" id="eftModalKeyAuthorise">Authorise</button>
                </div>
                <button type="button" class="eft-modal-cancel-btn" id="eftModalCancelBtn">Cancel Payment</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @php
        $pendingEftRecoveryForJs = $pendingEftRecovery ? [
            'sessionId' => $pendingEftRecovery->linkly_session_id,
            'clientRef' => $pendingEftRecovery->client_ref,
            'amount' => (float) $pendingEftRecovery->amount,
            'name' => $pendingEftRecovery->meta['customer_name'] ?? 'Customer',
            'email' => $pendingEftRecovery->meta['email'] ?? '',
            'mobile' => $pendingEftRecovery->meta['mobile'] ?? '',
        ] : null;
    @endphp
    <script>
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_ORDER_URL = @json(route('admin.tickets.storeOrder'));
        const EFT_CHARGE_START_URL = @json(route('admin.eft.charge.start'));
        const EFT_CHARGE_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const EFT_CHARGE_CANCEL_URL_BASE = @json(url('/admin/eft/charge/cancel'));
        const EFT_CHARGE_SENDKEY_URL_BASE = @json(url('/admin/eft/charge/sendkey'));
        const CURRENCY_CODE = @json($temple['currency'] ?? '');
        const CAN_SELL = @json($canSell);
        const PENDING_EFT_RECOVERY = @json($pendingEftRecoveryForJs);
        const EFT_TERMINALS = @json($eftTerminalsForJs);

        document.getElementById('posFullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { document.documentElement.requestFullscreen().catch(function () {}); }
            else { document.exitFullscreen(); }
        });

        // ---------- This station's EFT terminal ----------
        // Two storage layers, deliberately: sessionStorage is scoped per TAB (never shared,
        // even between two tabs of the same browser/computer showing this same page) and
        // always wins once this tab has explicitly picked a terminal — this is what keeps
        // two kiosk tabs on ONE computer (e.g. two virtual PIN pads for testing) genuinely
        // independent. localStorage is shared across every tab of this browser and is only
        // ever used as the *suggested default* for a brand-new tab that hasn't picked yet
        // (and is what the Ticket Console's "This Computer's EFT Terminal" Settings control
        // writes to) — a tab that has made its own explicit choice never has it silently
        // overridden by another tab picking something else. Without this split, reloading a
        // tab after a different tab picked a different terminal would silently move THIS
        // tab onto that other terminal too — which is what previously caused two concurrent
        // sessions to land on the same physical/virtual terminal and get rejected by Linkly
        // as offline/auto-cancelled.
        const TERMINAL_LOCAL_KEY = 'ticketPosEftTerminalId';
        const TERMINAL_SESSION_KEY = 'ticketPosEftTerminalId_tab';
        function loadSelectedTerminalId() {
            let saved = null;
            try { saved = sessionStorage.getItem(TERMINAL_SESSION_KEY); } catch (e) {}
            if (saved && EFT_TERMINALS.some(function (t) { return String(t.id) === String(saved); })) { return saved; }
            try { saved = localStorage.getItem(TERMINAL_LOCAL_KEY); } catch (e) {}
            if (saved && EFT_TERMINALS.some(function (t) { return String(t.id) === String(saved); })) { return saved; }
            const def = EFT_TERMINALS.find(function (t) { return t.is_default; }) || EFT_TERMINALS[0];
            return def ? String(def.id) : null;
        }
        function saveSelectedTerminalId(id) {
            try { sessionStorage.setItem(TERMINAL_SESSION_KEY, id); } catch (e) {}
            try { localStorage.setItem(TERMINAL_LOCAL_KEY, id); } catch (e) {}
        }
        let selectedTerminalId = loadSelectedTerminalId();
        function currentTerminalLabel() {
            const t = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
            return t ? t.label : 'No terminal';
        }
        // Online/offline is inferred from the terminal's own most recent transaction result
        // (see EftTerminal::lastKnownStatus()) — 'unknown' just means no transaction has
        // gone through yet on this terminal, never a guess.
        function terminalStatusInfo(t) {
            if (t.status === 'online') { return { color: 'var(--success)', text: 'Online', dot: 'online' }; }
            if (t.status === 'offline') { return { color: 'var(--error)', text: 'Offline', dot: 'offline' }; }
            return { color: '#B7791F', text: 'Not checked', dot: 'unknown' };
        }
        function renderTerminalPickerButton() {
            document.getElementById('terminalPickerLabel').textContent = currentTerminalLabel();
            const dot = document.getElementById('terminalStatusDot');
            if (dot) {
                const t = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                const info = t ? terminalStatusInfo(t) : { text: 'No terminal selected', dot: 'unknown' };
                dot.className = 'terminal-status-dot ' + info.dot;
                dot.title = info.text + (t && t.status_at ? ' (' + t.status_at + ')' : '');
            }
        }
        function renderTerminalModalList() {
            const list = document.getElementById('terminalModalList');
            list.innerHTML = '';
            if (!EFT_TERMINALS.length) {
                list.innerHTML = '<p class="text-muted small mb-0">No terminals registered yet — add one from Settings.</p>';
                return;
            }
            EFT_TERMINALS.forEach(function (t) {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'qty-modal-btn';
                row.style.cssText = 'width:100%; height:auto; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; font-size:0.95rem; border-radius:12px;' + (String(t.id) === String(selectedTerminalId) ? ' border-color:var(--gold); background:var(--cream);' : '');
                const statusInfo = terminalStatusInfo(t);
                row.innerHTML = '<span>' + t.label + (t.is_default ? ' <span style="font-size:0.7rem; color:var(--text-secondary);">(default)</span>' : '') + '</span>' +
                    '<span style="display:flex; align-items:center; gap:10px;">' +
                    '<span style="font-size:0.75rem; font-weight:700; color:' + (t.paired ? 'var(--success)' : 'var(--error)') + ';">' + (t.paired ? 'Paired' : 'Not paired') + '</span>' +
                    '<span style="font-size:0.75rem; font-weight:700; color:' + statusInfo.color + ';">' + statusInfo.text + '</span>' +
                    '</span>';
                row.addEventListener('click', function () {
                    selectedTerminalId = String(t.id);
                    saveSelectedTerminalId(selectedTerminalId);
                    renderTerminalPickerButton();
                    renderTerminalModalList();
                });
                list.appendChild(row);
            });
        }
        document.getElementById('terminalPickerBtn').addEventListener('click', function () {
            renderTerminalModalList();
            document.getElementById('terminalModalOverlay').classList.add('active');
        });
        document.getElementById('terminalModalCancel').addEventListener('click', function () {
            document.getElementById('terminalModalOverlay').classList.remove('active');
        });
        renderTerminalPickerButton();

        // ---------- Cart ----------
        let cart = {}; // ticket_id -> {id, name, price, quantity}

        function renderCart() {
            const list = document.getElementById('cartList');
            const lines = Object.values(cart);
            list.innerHTML = '';
            if (!lines.length) {
                list.innerHTML = '<div class="pos-cart-empty" id="cartEmptyMsg">Tap a ticket to add it to the order.</div>';
            } else {
                lines.forEach(function (line) {
                    const row = document.createElement('div');
                    row.className = 'cart-line';
                    row.dataset.id = line.id;
                    row.innerHTML =
                        '<div class="cart-line-info">' +
                            '<div class="cart-line-name">' + line.name + '</div>' +
                            '<div class="cart-line-unit">' + CURRENCY_CODE + ' ' + line.price.toFixed(2) + ' each</div>' +
                        '</div>' +
                        '<div class="cart-line-qty-controls">' +
                            '<button type="button" class="cart-qty-btn cart-line-minus">−</button>' +
                            '<input type="number" class="cart-qty-input cart-line-qty-input" min="0" value="' + line.quantity + '">' +
                            '<button type="button" class="cart-qty-btn cart-line-plus">+</button>' +
                        '</div>' +
                        '<div class="cart-line-total">' + (line.price * line.quantity).toFixed(2) + '</div>' +
                        '<button type="button" class="cart-line-remove"><i class="bi bi-trash-fill"></i></button>';
                    list.appendChild(row);
                });
            }

            document.querySelectorAll('.pos-item-tile').forEach(function (tile) {
                const id = tile.dataset.id;
                const qty = (cart[id] && cart[id].quantity) || 0;
                tile.classList.toggle('in-cart', qty > 0);
                tile.querySelector('.tile-qty-badge').textContent = qty;
            });

            recalcCartTotal();
        }

        function recalcCartTotal() {
            let total = 0;
            Object.values(cart).forEach(function (line) { total += line.price * line.quantity; });
            document.getElementById('cartTotal').textContent = CURRENCY_CODE + ' ' + total.toFixed(2);
            return total;
        }

        function setLineQuantity(id, name, price, quantity) {
            quantity = Math.max(0, quantity);
            if (quantity === 0) { delete cart[id]; }
            else { cart[id] = { id: id, name: name, price: price, quantity: quantity }; }
            renderCart();
        }

        document.getElementById('cartList').addEventListener('click', function (e) {
            const row = e.target.closest('.cart-line');
            if (!row) { return; }
            const id = row.dataset.id;
            const line = cart[id];
            if (!line) { return; }
            if (e.target.closest('.cart-line-plus')) { setLineQuantity(id, line.name, line.price, line.quantity + 1); }
            else if (e.target.closest('.cart-line-minus')) { setLineQuantity(id, line.name, line.price, line.quantity - 1); }
            else if (e.target.closest('.cart-line-remove')) { setLineQuantity(id, line.name, line.price, 0); }
        });
        document.getElementById('cartList').addEventListener('change', function (e) {
            if (!e.target.classList.contains('cart-line-qty-input')) { return; }
            const row = e.target.closest('.cart-line');
            const id = row.dataset.id;
            const line = cart[id];
            if (!line) { return; }
            setLineQuantity(id, line.name, line.price, parseInt(e.target.value, 10) || 0);
        });

        function resetCart() {
            cart = {};
            renderCart();
            document.getElementById('ticketCustomerName').value = '';
            document.getElementById('ticketCustomerMobile').value = '';
        }
        function cartAsArray() {
            return Object.values(cart).map(function (l) { return { ticket_id: l.id, name: l.name, price: l.price, quantity: l.quantity }; });
        }

        // ---------- Quantity modal (tap-to-add) ----------
        const qtyModalOverlay = document.getElementById('qtyModalOverlay');
        let qtyModalTicket = null;
        function openQtyModal(tile) {
            if (!CAN_SELL) { return; }
            const id = tile.dataset.id;
            qtyModalTicket = { id: id, name: tile.dataset.name, price: parseFloat(tile.dataset.price) };
            document.getElementById('qtyModalTitle').textContent = qtyModalTicket.name;
            document.getElementById('qtyModalPrice').textContent = CURRENCY_CODE + ' ' + qtyModalTicket.price.toFixed(2) + ' each';
            document.getElementById('qtyModalValue').textContent = (cart[id] && cart[id].quantity) || 1;
            qtyModalOverlay.classList.add('active');
        }
        document.getElementById('itemsGrid').addEventListener('click', function (e) {
            const tile = e.target.closest('.pos-item-tile');
            if (tile) { openQtyModal(tile); }
        });
        document.getElementById('qtyModalPlus').addEventListener('click', function () {
            const el = document.getElementById('qtyModalValue');
            el.textContent = parseInt(el.textContent, 10) + 1;
        });
        document.getElementById('qtyModalMinus').addEventListener('click', function () {
            const el = document.getElementById('qtyModalValue');
            el.textContent = Math.max(0, parseInt(el.textContent, 10) - 1);
        });
        document.getElementById('qtyModalCancel').addEventListener('click', function () {
            qtyModalOverlay.classList.remove('active');
        });
        document.getElementById('qtyModalConfirm').addEventListener('click', function () {
            const qty = parseInt(document.getElementById('qtyModalValue').textContent, 10) || 0;
            setLineQuantity(qtyModalTicket.id, qtyModalTicket.name, qtyModalTicket.price, qty);
            qtyModalOverlay.classList.remove('active');
        });

        // ---------- Payment method ----------
        const methodRow = document.getElementById('posMethodRow');
        let selectedMethod = null;
        const methodIcons = { Cash: 'bi-cash-coin', UPI: 'bi-phone-fill', 'Bank Transfer': 'bi-bank2', 'EFT Terminal': 'bi-credit-card-2-front-fill' };
        const PAYMENT_METHODS = @json($paymentMethods);
        (PAYMENT_METHODS.length ? PAYMENT_METHODS : ['Cash']).forEach(function (m, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-method-btn' + (idx === 0 ? ' active' : '');
            btn.innerHTML = '<i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i>' + m;
            btn.addEventListener('click', function () {
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
            });
            methodRow.appendChild(btn);
            if (idx === 0) { selectedMethod = m; }
        });

        let toastHideTimer = null;
        function showToast(message, isError) {
            if (toastHideTimer) { clearTimeout(toastHideTimer); toastHideTimer = null; }
            const toast = document.getElementById('posToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            toastHideTimer = setTimeout(function () { toast.style.display = 'none'; }, 2200);
        }

        const eftModalOverlay = document.getElementById('eftModalOverlay');
        const eftModalAmount = document.getElementById('eftModalAmount');
        const eftModalStatusBox = document.getElementById('eftModalStatusBox');
        const eftModalStatusLine1 = document.getElementById('eftModalStatusLine1');
        const eftModalStatusLine2 = document.getElementById('eftModalStatusLine2');
        const eftModalKeys = document.getElementById('eftModalKeys');
        const eftKeyButtons = {
            ok: document.getElementById('eftModalKeyOk'),
            yes: document.getElementById('eftModalKeyYes'),
            no: document.getElementById('eftModalKeyNo'),
            authorise: document.getElementById('eftModalKeyAuthorise'),
        };

        const EFT_ATTEMPT_KEY = 'ticketEftAttempt';
        function newClientRef() {
            return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
        }
        function saveEftAttempt(attempt) {
            try { sessionStorage.setItem(EFT_ATTEMPT_KEY, JSON.stringify(attempt)); } catch (e) {}
        }
        function loadEftAttempt() {
            try { return JSON.parse(sessionStorage.getItem(EFT_ATTEMPT_KEY) || 'null'); } catch (e) { return null; }
        }
        function clearEftAttempt() {
            try { sessionStorage.removeItem(EFT_ATTEMPT_KEY); } catch (e) {}
        }

        let eftModalLastSignature = null;
        let eftModalKeysSignature = null;
        function showEftModal(amount) {
            eftModalAmount.textContent = CURRENCY_CODE + ' ' + amount.toFixed(2);
            eftModalLastSignature = null;
            setEftModalStatus(['Starting…'], 'pending');
            updateEftModalKeys(null, null);
            eftModalOverlay.classList.add('active');
        }
        function updateEftModalKeys(controls, sessionId) {
            const signature = controls ? JSON.stringify(controls) : 'none';
            if (signature === eftModalKeysSignature) { return; }
            eftModalKeysSignature = signature;
            let anyVisible = false;
            Object.keys(eftKeyButtons).forEach(function (key) {
                const visible = !!(controls && controls[key]);
                eftKeyButtons[key].hidden = !visible;
                eftKeyButtons[key].onclick = visible ? function () { sendEftModalKey(key, sessionId); } : null;
                if (visible) { anyVisible = true; }
            });
            eftModalKeys.classList.toggle('active', anyVisible);
        }
        function sendEftModalKey(key, sessionId) {
            Object.values(eftKeyButtons).forEach(function (b) { b.disabled = true; });
            fetch(EFT_CHARGE_SENDKEY_URL_BASE + '/' + encodeURIComponent(sessionId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=' + encodeURIComponent(key),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; });
                    if (!data.success) { showToast(data.message || 'The terminal did not accept that.', true); }
                })
                .catch(function () {
                    Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; });
                    showToast('Could not reach the terminal — please try again.', true);
                });
        }
        function setEftModalStatus(lines, state) {
            lines = lines && lines.length ? lines : ['Please wait…'];
            const signature = state + '|' + lines.join('|');
            if (signature === eftModalLastSignature) { return; }
            eftModalLastSignature = signature;
            eftModalStatusBox.classList.remove('pending', 'success', 'error');
            eftModalStatusBox.classList.add(state);
            eftModalStatusLine1.textContent = lines[0] || '';
            eftModalStatusLine2.textContent = lines[1] || '';
        }
        function hideEftModal() {
            eftModalOverlay.classList.remove('active');
            eftCurrentSessionId = null;
            updateEftModalKeys(null, null);
        }

        function openPrintView(orderId) {
            if (orderId) { window.open('/admin/tickets/print/' + orderId, '_blank'); }
        }

        const posSaveBtn = document.getElementById('posSaveBtn');
        if (posSaveBtn) {
            posSaveBtn.addEventListener('click', function () {
                const cartLines = cartAsArray();
                const total = recalcCartTotal();
                if (!cartLines.length || total <= 0) { showToast('Add at least one ticket to the order.', true); return; }

                const name = document.getElementById('ticketCustomerName').value.trim();
                const mobile = document.getElementById('ticketCustomerMobile').value.trim();
                const btn = this;

                if (selectedMethod === 'EFT Terminal') {
                    const selectedTerminal = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                    if (!selectedTerminal || !selectedTerminal.paired) {
                        showToast('This station\'s EFT terminal (' + currentTerminalLabel() + ') is not paired yet — check the terminal picker.', true);
                        return;
                    }
                }
                btn.disabled = true;

                if (selectedMethod === 'EFT Terminal') {
                    startOrResumeEftPurchase(btn, {
                        clientRef: newClientRef(),
                        amount: total,
                        name: name || 'Customer',
                        email: '',
                        mobile: mobile,
                        cart: cartLines,
                    });
                    return;
                }

                const body = new URLSearchParams();
                body.set('customer_name', name);
                body.set('email', '');
                body.set('mobile', mobile);
                body.set('cart_json', JSON.stringify(cartLines));
                body.set('payment_method', selectedMethod);

                fetch(STORE_ORDER_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString(),
                })
                    .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                    .then(function (result) {
                        btn.disabled = false;
                        if (result.status >= 200 && result.status < 300 && result.data.success) {
                            showToast('Sale recorded — printing…');
                            openPrintView(result.data.order_id);
                            resetCart();
                        } else {
                            showToast(result.data.message || 'Failed to save.', true);
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        showToast('Network error — please try again.', true);
                    });
            });
        }

        function startOrResumeEftPurchase(btn, freshAttempt) {
            const attempt = loadEftAttempt() || freshAttempt;
            saveEftAttempt(attempt);

            eftPollCancelled = false;
            eftCurrentSessionId = null;
            eftConsecutiveTransientErrors = 0;
            showEftModal(attempt.amount);
            const startBody = new URLSearchParams();
            startBody.set('record_type', 'ticket_order');
            startBody.set('amount', attempt.amount.toFixed(2));
            startBody.set('client_ref', attempt.clientRef);
            startBody.set('donor_name', attempt.name || '');
            startBody.set('email', attempt.email || '');
            startBody.set('mobile', attempt.mobile || '');
            startBody.set('cart_json', JSON.stringify(attempt.cart || []));
            if (selectedTerminalId) { startBody.set('terminal_id', selectedTerminalId); }

            fetch(EFT_CHARGE_START_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: startBody.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    if (!(result.status >= 200 && result.status < 300 && result.data.success)) {
                        btn.disabled = false;
                        hideEftModal();
                        showToast(result.data.message || 'Could not start the terminal transaction.', true);
                        return;
                    }
                    eftCurrentSessionId = result.data.session_id;
                    pollEftTransaction(result.data.session_id, btn, attempt.amount, Date.now());
                })
                .catch(function () {
                    btn.disabled = false;
                    hideEftModal();
                    showToast('Could not reach the EFT terminal — please try again.', true);
                });
        }

        let eftPollCancelled = false;
        let eftCurrentSessionId = null;
        document.getElementById('eftModalCancelBtn').addEventListener('click', function () {
            const sessionId = eftCurrentSessionId;
            if (!sessionId) {
                eftPollCancelled = true;
                clearEftAttempt();
                hideEftModal();
                if (posSaveBtn) { posSaveBtn.disabled = false; }
                showToast('Sale cancelled.', true);
                return;
            }

            const cancelBtn = this;
            cancelBtn.disabled = true;
            setEftModalStatus(['Cancelling…'], 'pending');

            fetch(EFT_CHARGE_CANCEL_URL_BASE + '/' + encodeURIComponent(sessionId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    cancelBtn.disabled = false;
                    if (data.success) {
                        eftPollCancelled = true;
                        clearEftAttempt();
                        hideEftModal();
                        if (posSaveBtn) { posSaveBtn.disabled = false; }
                        showToast('Cancelled on the terminal.');
                    } else {
                        showToast(data.message || 'Could not cancel — the transaction may still complete. Please wait for the result.', true);
                    }
                })
                .catch(function () {
                    cancelBtn.disabled = false;
                    showToast('Could not reach the terminal to cancel — the transaction may still complete. Please wait for the result.', true);
                });
        });

        let eftConsecutiveTransientErrors = 0;
        function eftNextPollDelay(wasTransientError) {
            if (!wasTransientError) { eftConsecutiveTransientErrors = 0; return 1200; }
            eftConsecutiveTransientErrors++;
            return Math.min(1200 * Math.pow(2, eftConsecutiveTransientErrors), 30000);
        }

        function pollEftTransaction(sessionId, btn, amount, startedAt) {
            if (eftPollCancelled) { return; }

            if (Date.now() - startedAt > 180000) {
                btn.disabled = false;
                setEftModalStatus(['No response from the terminal yet', 'Press the button to keep checking — this will not charge twice'], 'error');
                setTimeout(hideEftModal, 2200);
                showToast('No final result yet — press the button to keep checking (safe, will not double-charge).', true);
                return;
            }

            fetch(EFT_CHARGE_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (eftPollCancelled) { return; }

                    if (data.display && data.display.length) { setEftModalStatus(data.display, 'pending'); }
                    updateEftModalKeys(data.done ? null : data.controls, sessionId);
                    if (!data.done) {
                        setTimeout(function () { pollEftTransaction(sessionId, btn, amount, startedAt); }, eftNextPollDelay(!!data.transient_error));
                        return;
                    }
                    if (data.success) {
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT APPROVED', data.auth_code ? 'Auth ' + data.auth_code : 'Printing…'], 'success');
                        setTimeout(function () {
                            hideEftModal();
                            openPrintView(data.donation_id);
                            resetCart();
                            btn.disabled = false;
                        }, 1200);
                    } else if (data.payment_status === 'unknown') {
                        setEftModalStatus(['RESULT UNKNOWN', 'Check the terminal/bank statement before retrying'], 'error');
                        setTimeout(hideEftModal, 2600);
                        btn.disabled = false;
                        showToast(data.message || 'No final result was received — check before retrying.', true);
                    } else {
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT DECLINED', data.message || ''], 'error');
                        setTimeout(hideEftModal, 1800);
                        btn.disabled = false;
                        showToast(data.message || 'Card declined.', true);
                    }
                })
                .catch(function () {
                    setTimeout(function () { pollEftTransaction(sessionId, btn, amount, startedAt); }, eftNextPollDelay(true));
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            renderCart();

            const banner = document.getElementById('eftResumeBanner');
            if (!banner || !posSaveBtn) { return; }

            if (PENDING_EFT_RECOVERY) {
                const p = PENDING_EFT_RECOVERY;
                document.getElementById('eftResumeBannerText').textContent =
                    'Checking a previous ticket sale (' + CURRENCY_CODE + ' ' + p.amount.toFixed(2) + ') that did not finish…';
                banner.style.display = 'flex';
                document.getElementById('eftResumeBannerDismissBtn').addEventListener('click', function () {
                    banner.style.display = 'none';
                    eftPollCancelled = true;
                    clearEftAttempt();
                });

                posSaveBtn.disabled = true;
                eftPollCancelled = false;
                eftCurrentSessionId = p.sessionId;
                eftConsecutiveTransientErrors = 0;
                saveEftAttempt({ clientRef: p.clientRef, amount: p.amount, name: p.name, email: p.email, mobile: p.mobile, cart: [] });
                showEftModal(p.amount);
                pollEftTransaction(p.sessionId, posSaveBtn, p.amount, Date.now());
            }
        });
    </script>
</body>
</html>
