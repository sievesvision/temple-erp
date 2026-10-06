<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Ticket Sales POS</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF;
            /* Firmer than the old #F0E5D6 — a POS is read at a glance and worked fast, so
               every border needs to actually register against the white/cream around it. */
            --border: #D9CBB0; --shade: #F2ECE0; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
            /* One shared radius scale, deliberately tighter than the old 12-22px range — a
               terminal/POS screen reads as more purposeful with crisp, moderate corners than
               with soft app-style bubbles. */
            --radius-sm: 8px; --radius-md: 10px; --radius-lg: 14px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; height: 100%; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); display: flex; flex-direction: column; }
        button, input, select, textarea { font-family: inherit; }
        /* POS hardening: no accidental text selection/callouts from a fast tap-and-hold,
           and no 300ms ghost-click delay on older mobile Safari/Chrome. */
        button, .pos-item-tile, .pos-method-btn { -webkit-user-select: none; user-select: none; touch-action: manipulation; }

        .pos-topbar { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 12px 16px; display: flex; align-items: center; gap: 10px; flex-shrink: 0; box-shadow: 0 4px 18px rgba(74,10,18,0.25); z-index: 20; }
        .pos-topbar-title { flex: 1; min-width: 0; }
        .pos-topbar-title h1 { font-size: clamp(1.05rem, 2.6vw, 1.35rem); font-weight: 800; color: var(--gold); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-topbar-title .pos-subtitle { font-size: 0.7rem; color: rgba(255,255,255,0.65); text-transform: uppercase; letter-spacing: 0.06em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-topbar-btn { background: rgba(255,255,255,0.12); border: none; color: white; width: 42px; height: 42px; border-radius: var(--radius-sm); font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .pos-topbar-btn:hover { background: rgba(255,255,255,0.22); }
        .pos-terminal-btn { background: rgba(255,255,255,0.12); border: none; color: white; height: 42px; padding: 0 14px; border-radius: var(--radius-sm); font-size: 0.82rem; font-weight: 700; flex-shrink: 0; display: flex; align-items: center; gap: 8px; max-width: 160px; }
        .pos-terminal-btn:hover { background: rgba(255,255,255,0.22); }
        .pos-terminal-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* The terminal name is the first thing to give up its label on a cramped phone header
           — it collapses to just the icon, well before anything else has to; the full name is
           always one tap away in the picker itself. */
        @media (max-width: 480px) {
            .pos-terminal-btn { max-width: none; padding: 0; width: 42px; justify-content: center; }
            .pos-terminal-btn span { display: none; }
        }

        /* ---------- Full-width POS: items grid on the left, cart panel on the right ---------- */
        .pos-body { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        @media (min-width: 900px) { .pos-body { flex-direction: row; } }

        .pos-items-pane { flex: 1; min-height: 0; overflow-y: auto; padding: 18px; }
        .pos-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }

        /* Each tile is a miniature version of the temple's own printed ticket design — an
           ornate bordered card themed by the ticket's own background_color (see App\Models\
           Ticket), with a circular image frame, the ticket name, and a price badge — rather
           than a plain photo-background button, so the POS page itself looks like the physical
           tickets it's selling. */
        .pos-item-tile {
            position: relative; border-radius: var(--radius-md); padding: 8px; border: none; cursor: pointer;
            text-align: center; background: color-mix(in srgb, var(--tile-accent) 12%, white);
            box-shadow: 0 2px 8px rgba(31,42,55,0.08); transition: transform 0.1s;
        }
        .pos-item-tile:active { transform: scale(0.97); }
        .pos-item-tile.in-cart { box-shadow: 0 0 0 3px var(--tile-accent), 0 4px 14px rgba(31,42,55,0.16); }
        .tile-frame {
            position: relative; border: 1.5px solid color-mix(in srgb, var(--tile-accent) 55%, white);
            border-radius: var(--radius-sm); padding: 14px 10px 12px; background: color-mix(in srgb, var(--tile-accent) 4%, white);
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

        /* Desktop/landscape-tablet: a normal full-height sidebar, exactly as before. Below
           900px this same element becomes a collapsible bottom sheet instead (see the mobile
           block further down) — collapsed to just its header bar by default so the item grid
           stays visible and usable, expanding only when the operator taps it to check out.
           Previously this panel had no height cap on mobile at all: flex-shrink:0 combined
           with flex-direction:column on .pos-body meant it always claimed however much height
           its full content needed (header+list+total+fields+payment+buttons), which was often
           the entire viewport — leaving the items grid squeezed into whatever sliver was left,
           sometimes nothing at all. That's the exact "order button hides the items" bug. */
        .pos-cart-pane { width: 100%; flex-shrink: 0; background: var(--white); border-left: 1px solid var(--border); display: flex; flex-direction: column; min-height: 0; }
        @media (min-width: 900px) { .pos-cart-pane { width: 400px; } }
        .pos-cart-header { padding: 16px 18px; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; gap: 10px; }
        .pos-cart-header-summary { display: none; margin-left: auto; align-items: center; gap: 10px; font-weight: 700; font-size: 0.92rem; color: var(--gold-hover); }
        .pos-cart-header-chevron { display: none; transition: transform 0.2s; color: var(--text-secondary); }
        .pos-cart-list { flex: 1; min-height: 80px; overflow-y: auto; padding: 0 18px; }

        @media (max-width: 899px) {
            /* Room for the always-visible collapsed bar so the last row of tickets never
               sits underneath it. */
            .pos-items-pane { padding-bottom: 78px; }
            .pos-cart-pane {
                position: fixed; left: 0; right: 0; bottom: 0; z-index: 60;
                max-height: 82vh; border-radius: var(--radius-lg) var(--radius-lg) 0 0;
                border-left: none; border-top: 1.5px solid var(--border);
                box-shadow: 0 -8px 28px rgba(31,42,55,0.22); overflow-y: auto;
            }
            .pos-cart-header { cursor: pointer; padding: 16px 18px; }
            .pos-cart-header-summary { display: flex; }
            .pos-cart-header-chevron { display: block; }
            .pos-cart-pane.expanded .pos-cart-header-chevron { transform: rotate(180deg); }
            /* Collapsed = just the header bar (title + live count/total + chevron); everything
               below only renders once expanded. */
            .pos-cart-list, .pos-cart-footer { display: none; }
            .pos-cart-pane.expanded .pos-cart-list, .pos-cart-pane.expanded .pos-cart-footer { display: block; }
        }
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
        .pos-input { width: 100%; padding: 9px 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 0.88rem; font-weight: 600; color: var(--text-primary); background: var(--white); min-height: 38px; }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
        .pos-row.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }

        .pos-method-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .pos-method-btn { flex: 1 1 calc(50% - 8px); min-width: 90px; padding: 10px 8px; min-height: 46px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--shade); font-weight: 700; font-size: 0.82rem; color: var(--text-secondary); }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.3); }
        .pos-method-btn i { display: block; font-size: 1.1rem; margin-bottom: 2px; }

        .pos-save-btn { width: 100%; padding: 16px; border-radius: var(--radius-md); border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 1.1rem; box-shadow: 0 10px 26px rgba(200,155,60,0.35); min-height: 56px; }
        .pos-save-btn:disabled { opacity: 0.55; }
        .pos-save-btn:active { transform: scale(0.98); }

        .pos-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 18px 26px; border-radius: var(--radius-md); font-weight: 700; font-size: 1.1rem; box-shadow: 0 14px 34px rgba(0,0,0,0.2); z-index: 999; display: none; }
        .pos-toast.error { background: var(--error); }

        /* A bottom-corner toast is easy to miss mid-transaction, with both the operator and
           customer's attention on the center of the screen — warnings/errors now interrupt
           with a real popup instead; a plain confirmation still just uses the quieter corner
           toast above. */
        .pos-warning-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1100; display: none; align-items: center; justify-content: center; padding: 20px; }
        .pos-warning-overlay.active { display: flex; }
        .pos-warning-popup { background: var(--white); border-radius: var(--radius-lg); width: 100%; max-width: 380px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; }
        .pos-warning-icon { background: var(--error); color: #fff; font-size: 1.8rem; padding: 20px; }
        .pos-warning-body { padding: 22px 24px 26px; }
        .pos-warning-message { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); margin-bottom: 18px; }
        .pos-warning-ok-btn { width: 100%; padding: 14px; border-radius: var(--radius-sm); border: none; background: var(--maroon); color: #fff; font-weight: 700; font-size: 0.98rem; }
        .pos-warning-ok-btn:active { filter: brightness(0.92); }

        /* ---------- Quantity picker modal (opened by tapping an item tile) ---------- */
        .qty-modal-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 900; display: none; align-items: center; justify-content: center; padding: 20px; }
        .qty-modal-overlay.active { display: flex; }
        .qty-modal { background: var(--white); border-radius: var(--radius-lg); width: 100%; max-width: 340px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; }
        .qty-modal-header { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 16px 20px; font-weight: 800; font-size: 1.05rem; }
        .qty-modal-body { padding: 24px 22px; }
        .qty-modal-price { color: var(--gold-hover); font-weight: 700; font-family: 'IBM Plex Mono', monospace; margin-bottom: 18px; }
        .qty-modal-controls { display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 22px; }
        .qty-modal-btn { width: 54px; height: 54px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--cream); font-size: 1.6rem; font-weight: 800; color: var(--gold-hover); }
        .qty-modal-value { font-size: 2rem; font-weight: 800; font-family: 'IBM Plex Mono', monospace; min-width: 60px; }
        .qty-modal-actions { display: flex; gap: 10px; }
        .qty-modal-cancel { flex: 1; padding: 13px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; }
        .qty-modal-confirm { flex: 2; padding: 13px; border-radius: var(--radius-sm); border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; }

        .eft-modal-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal { background: var(--white); border-radius: var(--radius-lg); width: 100%; max-width: 460px; max-height: calc(100vh - 40px); box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; display: flex; flex-direction: column; }
        .eft-modal-header { background: linear-gradient(135deg, var(--maroon), var(--maroon-dark)); color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em; font-size: 0.95rem; text-transform: uppercase; flex-shrink: 0; }
        {{-- min-height:0 is the flexbox gotcha fix — without it a flex child never actually
             shrinks to scroll, it just overflows its parent instead, which is exactly how a
             long Action Framework response (mx51's "13.37" full-element test case among them)
             used to push the whole modal past the viewport instead of scrolling internally. --}}
        .eft-modal-body { padding: 22px 22px 20px; overflow-y: auto; min-height: 0; }
        .eft-modal-amount { font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; font-size: 2.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 14px; }
        .eft-modal-status-box { background: var(--cream); border: 2px solid var(--border); border-radius: var(--radius-md); padding: 14px 16px; min-height: 72px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; margin-bottom: 16px; }
        .eft-modal-spinner { width: 26px; height: 26px; border-radius: 50%; border: 3px solid rgba(200,155,60,0.25); border-top-color: var(--gold); animation: eftSpin 0.8s linear infinite; margin-bottom: 4px; display: none; }
        .eft-modal-status-box.pending .eft-modal-spinner { display: block; }
        .eft-modal-status-icon { font-size: 1.6rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error { display: block; color: var(--error); }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { color: var(--error); }
        @keyframes eftSpin { to { transform: rotate(360deg); } }
        .eft-modal-cancel-btn { width: 100%; padding: 14px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem; }
        .eft-modal-cancel-btn:active { background: var(--cream); }
        .eft-modal-keys { display: none; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .eft-modal-keys.active { display: flex; }
        .eft-modal-key-btn { flex: 1 1 auto; min-width: 90px; padding: 13px 10px; border-radius: var(--radius-sm); border: 2px solid transparent; font-weight: 700; font-size: 0.95rem; color: #fff; }
        .eft-modal-key-btn:active { filter: brightness(0.92); }
        .eft-modal-key-btn.key-ok, .eft-modal-key-btn.key-yes, .eft-modal-key-btn.key-authorise { background: var(--success); }
        .eft-modal-key-btn.key-no { background: var(--error); }

        /* CBA Smart Terminal (mx51 SCI) — dynamic Action Framework elements, rendered from
           whatever pos_instructions the terminal sends for this step (text/button/input/
           image), never a fixed set like the Linkly soft-keys above. */
        .sci-af-row { display: flex; gap: 8px 12px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
        .sci-af-row:last-child { margin-bottom: 0; }
        .sci-af-text { font-size: 0.88rem; color: var(--text-secondary); text-align: left; }
        .sci-af-btn { flex: 1 1 auto; min-width: 90px; padding: 10px 10px; border-radius: var(--radius-sm); border: 2px solid transparent; font-weight: 700; font-size: 0.88rem; color: #fff; background: var(--maroon); }
        .sci-af-btn:active { filter: brightness(0.92); }
        {{-- flex-wrap lets a long label (mx51's own test fields can be verbose, e.g.
             "Input 1 (input_name_1_...)") drop to its own line above the input instead of
             forcing the row wider than the modal. --}}
        .sci-af-input-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; flex: 1 1 100%; }
        .sci-af-input-label { font-size: 0.8rem; color: var(--text-secondary); }
        .sci-af-input { flex: 1 1 160px; min-width: 120px; padding: 9px 12px; border-radius: var(--radius-sm); border: 2px solid var(--border); font-size: 0.88rem; }
        {{-- Capped height — mx51's own supplied branding image is a real `type: 'image'`
             element rendered like any other, not a locally bundled logo; without a height cap
             it could render at an arbitrarily large natural size. --}}
        .sci-af-image { max-width: 100%; max-height: 64px; display: block; margin: 6px auto; border-radius: var(--radius-sm); }
        .sci-af-details { text-align: left; font-size: 0.78rem; color: var(--text-secondary); line-height: 1.45; }
        .sci-af-test-payload { text-align: left; font-family: 'Courier New', ui-monospace, monospace; font-size: 0.74rem; line-height: 1.4; white-space: pre-wrap; word-break: break-word; background: var(--cream); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px; margin-top: 8px; }
        #eftModalActionFramework { margin-bottom: 10px; }

        /* Manual recovery override — CBA SCI has no cancel API, so once a transaction has
           actually started, "Cancel" is replaced by an honest "confirm the real outcome"
           prompt instead of pretending the payment can be stopped mid-flight. */
        .eft-modal-override p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 14px; }
        .eft-modal-override-actions { display: flex; gap: 10px; margin-bottom: 10px; }
        .eft-override-btn { flex: 1 1 auto; padding: 13px 10px; border-radius: var(--radius-sm); border: 2px solid transparent; font-weight: 700; font-size: 0.9rem; color: #fff; }
        .eft-override-btn.eft-override-yes { background: var(--success); }
        .eft-override-btn.eft-override-no { background: var(--error); }
    </style>
</head>
<body>
    @include('partials.test-banner')
    <header class="pos-topbar">
        <div class="pos-topbar-title">
            <h1>Ticket Sales POS</h1>
            <div class="pos-subtitle">Sell &amp; Print Tickets</div>
        </div>
        <button type="button" class="pos-terminal-btn" id="terminalPickerBtn" title="This station's EFT terminal">
            <i class="bi bi-credit-card-2-front-fill"></i><span id="terminalPickerLabel">Terminal</span>
        </button>
        @if($canManageConsole)
        <a href="{{ route('admin.tickets.index') }}" class="pos-topbar-btn" title="Ticket Console"><i class="bi bi-grid-1x2-fill"></i></a>
        @endif
        <div class="dropdown">
            <button class="pos-topbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
                <i class="bi bi-person-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if($canManagePosPin)
                <li><a class="dropdown-item" href="{{ route('pos.pin.edit') }}"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Manage POS PIN</a></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('logout', ['from' => 'pos']) }}"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
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
            <div class="pos-cart-header" id="cartHeaderToggle">
                <i class="bi bi-cart-fill me-2"></i>Order
                <span class="pos-cart-header-summary" id="cartHeaderSummary"></span>
                <i class="bi bi-chevron-up pos-cart-header-chevron"></i>
            </div>
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

    <div class="pos-warning-overlay" id="posWarningOverlay">
        <div class="pos-warning-popup">
            <div class="pos-warning-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="pos-warning-body">
                <div class="pos-warning-message" id="posWarningMessage"></div>
                <button type="button" class="pos-warning-ok-btn" id="posWarningOkBtn">OK</button>
            </div>
        </div>
    </div>

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
                <a href="#" onclick="event.preventDefault(); openEftTerminalSettingsModal();" class="d-block small mb-3"><i class="bi bi-gear me-1"></i>Open EFT Terminal Settings (pair or add a terminal)</a>
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
                <!-- CBA Smart Terminal (mx51 SCI) — dynamic Action Framework elements for
                     whichever step the terminal is currently on (text/button/input/image). -->
                <div id="eftModalActionFramework" hidden></div>
                <div class="eft-modal-override" id="eftModalOverride" hidden>
                    <p>We couldn't get a final answer from the terminal. Did the payment go through?</p>
                    <div class="eft-modal-override-actions">
                        <button type="button" class="eft-override-btn eft-override-yes" id="eftModalOverrideYes">Yes, it went through</button>
                        <button type="button" class="eft-override-btn eft-override-no" id="eftModalOverrideNo">No / not sure</button>
                    </div>
                    <button type="button" class="eft-modal-cancel-btn" id="eftModalOverrideKeepWaiting">Keep Waiting</button>
                </div>
                <button type="button" class="eft-modal-cancel-btn" id="eftModalCancelBtn">Cancel Payment</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    {{-- ?v= busts the browser's (and any CDN's) 7-day Cache-Control on this static file --
         otherwise a fix shipped here never reaches an already-open POS tab or a browser
         that cached the old copy days ago, since nothing about the <script> tag itself
         changes between deploys. --}}
    <script src="{{ asset('js/sci-action-framework.js') }}?v={{ @filemtime(public_path('js/sci-action-framework.js')) }}"></script>
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
        // A session that expires while this POS page is left open only ever surfaces to a
        // background fetch() (the various polling/save calls below) as a plain 401 JSON body
        // — Laravel's default unauthenticated() handler never redirects a request that
        // expects JSON. Reloading the page turns that into a normal full-page navigation,
        // which (now unauthenticated) is what actually triggers the server-side redirect to
        // the POS login screen — see Authenticate::redirectUsing() in AppServiceProvider.
        (function () {
            const nativeFetch = window.fetch;
            window.fetch = function () {
                return nativeFetch.apply(this, arguments).then(function (res) {
                    if (res.status === 401) { window.location.reload(); }
                    return res;
                });
            };
        })();

        const CSRF_TOKEN = @json(csrf_token());
        const STORE_ORDER_URL = @json(route('admin.tickets.storeOrder'));
        const EFT_CHARGE_START_URL = @json(route('admin.eft.charge.start'));
        const EFT_CHARGE_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const EFT_CHARGE_CANCEL_URL_BASE = @json(url('/admin/eft/charge/cancel'));
        const EFT_CHARGE_SENDKEY_URL_BASE = @json(url('/admin/eft/charge/sendkey'));
        const CURRENCY_CODE = @json($temple['currency'] ?? '');
        const CAN_SELL = @json($canSell);
        const PENDING_EFT_RECOVERY = @json($pendingEftRecoveryForJs);
        // Mutable (not const) — the terminal picker replaces this wholesale with a freshly
        // live-checked list every time it's opened (see CBA_SCI_PICKER_REFRESH_URL below);
        // this initial server-rendered value only ever matters for the very first paint,
        // before the picker has ever been opened.
        let EFT_TERMINALS = @json($eftTerminalsForJs);
        const CBA_SCI_PICKER_REFRESH_URL = @json(route('admin.cba-sci.terminal-picker.refresh'));
        const CBA_SCI_CHARGE_START_URL = @json(route('admin.cba-sci.charge.start'));
        const CBA_SCI_CHARGE_STATUS_URL_BASE = @json(url('/admin/cba-sci/charge/status'));
        const CBA_SCI_CHARGE_ACTION_URL_BASE = @json(url('/admin/cba-sci/charge/action'));
        const CBA_SCI_CHARGE_CANCEL_URL_BASE = @json(url('/admin/cba-sci/charge/cancel'));
        const CBA_SCI_CHARGE_OVERRIDE_URL_BASE = @json(url('/admin/cba-sci/charge/override'));

        // ---------- This station's EFT terminal ----------
        // Two storage layers, deliberately, and deliberately asymmetric between read and
        // write: sessionStorage is scoped per TAB and is both read AND written by the picker
        // below — picking a terminal there changes this tab's own choice for as long as this
        // browser session lasts, and a genuinely new session (new tab, new browser, or this one
        // closed and reopened) has nothing saved, falling straight back to whatever this
        // computer is configured for. localStorage is only ever READ here, never written by the
        // picker — it holds this COMPUTER's configured default, set via the Ticket Console's
        // "This Computer's EFT Terminal" control (see ticket-console.blade.php), a separate,
        // deliberate admin action, not something an operator's in-the-moment pick should ever
        // silently overwrite. Without the sessionStorage split, reloading a tab after a
        // different tab picked a different terminal would silently move THIS tab onto that
        // other terminal too — which is what previously caused two concurrent sessions to land
        // on the same physical/virtual terminal and get rejected by Linkly as offline/
        // auto-cancelled.
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
        }
        let selectedTerminalId = loadSelectedTerminalId();
        function currentTerminalLabel() {
            const t = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
            return t ? t.label : 'No terminal';
        }
        // Paired/not-paired is the one thing actually worth showing here now — an inferred
        // online/offline guess from past transaction results used to sit alongside it, but
        // that's a stale, misleading thing to call "status" on a screen whose whole point is
        // mx51's own required live check (see the fetch below): it never reflected whether a
        // pairing was ACTUALLY still active, only whether a reading was cached at all.
        // The topbar button is just a label now — no colour-coded status indicator. A live
        // paired/unpaired reading only exists right after the picker's own refresh fetch
        // below runs, so showing a dot on this button all the time was either stale (page-load
        // only) or meaningless between openings; the picker itself is where that status
        // actually lives now.
        function renderTerminalPickerButton() {
            document.getElementById('terminalPickerLabel').textContent = currentTerminalLabel();
        }
        const SCI_LOGO_URL = @json(asset('images/sci-logo.jpg'));

        // Only ever lists PAIRED terminals — an unpaired one can't take a payment, and pairing/
        // repairing one is now exclusively done from the EFT Terminal Settings page, not from
        // this picker.
        function renderTerminalModalList() {
            const list = document.getElementById('terminalModalList');
            list.innerHTML = '';
            const pairedTerminals = EFT_TERMINALS.filter(function (t) { return t.paired; });
            if (!pairedTerminals.length) {
                list.innerHTML = '<p class="text-muted small mb-0">No paired terminals yet — pair one from Settings.</p>';
                return;
            }
            pairedTerminals.forEach(function (t) {
                const card = document.createElement('div');
                card.style.cssText = 'width:100%; padding:12px 16px; border-radius:8px; border:2px solid var(--border); margin-bottom:10px;';

                const isSelected = String(t.id) === String(selectedTerminalId);
                const label = document.createElement('label');
                label.style.cssText = 'display:flex; align-items:center; gap:10px; font-size:0.95rem; font-weight:700; cursor:pointer;';
                const providerMark = t.provider === 'cba_sci'
                    ? '<img src="' + SCI_LOGO_URL + '" alt="SCI" style="width:18px; height:18px; border-radius:4px; object-fit:cover; flex-shrink:0;">'
                    : '<span class="badge-pill badge-provider">LINKLY CLOUD</span>';
                label.innerHTML = '<input type="checkbox" style="width:18px; height:18px;"' + (isSelected ? ' checked' : '') + '>' +
                    providerMark +
                    '<span>' + t.label + (t.is_default ? ' <span style="font-size:0.7rem; color:var(--text-secondary);">· default</span>' : '') + '</span>';
                card.appendChild(label);

                if (t.provider === 'cba_sci' && t.sci_pairing_id) {
                    const details = document.createElement('div');
                    details.style.cssText = 'margin-top:4px; padding-left:28px; font-size:0.78rem; color:var(--text-secondary);';
                    details.textContent = 'Pairing ID: ' + t.sci_pairing_id;
                    card.appendChild(details);
                }

                label.querySelector('input').addEventListener('change', function () {
                    selectedTerminalId = String(t.id);
                    saveSelectedTerminalId(selectedTerminalId);
                    renderTerminalPickerButton();
                    document.getElementById('terminalModalOverlay').classList.remove('active');
                });

                list.appendChild(card);
            });
        }
        // mx51's certification checklist requires a live GET /pairing-info check at the moment
        // the pairing/terminal screen is opened, not a flag cached from page load that only
        // ever changes once someone presses Unpair — EftTerminalController::index() already
        // does this for the admin registry page; this is the same check for the POS's own
        // terminal picker, which previously never ran it at all.
        function refreshTerminalPicker() {
            const list = document.getElementById('terminalModalList');
            if (list) { list.innerHTML = '<p class="text-muted small mb-0"><span class="spinner-border spinner-border-sm me-2"></span>Checking terminal status…</p>'; }
            fetch(CBA_SCI_PICKER_REFRESH_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success && Array.isArray(data.terminals)) {
                        EFT_TERMINALS = data.terminals;
                    }
                    renderTerminalModalList();
                    renderTerminalPickerButton();
                })
                .catch(function () {
                    // Still show whatever was last known rather than leaving the modal stuck
                    // on the loading message if the live check itself can't be reached.
                    renderTerminalModalList();
                });
        }
        // Shown immediately on open (own loading state) rather than blocking the click.
        document.getElementById('terminalPickerBtn').addEventListener('click', function () {
            document.getElementById('terminalModalOverlay').classList.add('active');
            refreshTerminalPicker();
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
            let total = 0, itemCount = 0;
            Object.values(cart).forEach(function (line) { total += line.price * line.quantity; itemCount += line.quantity; });
            document.getElementById('cartTotal').textContent = CURRENCY_CODE + ' ' + total.toFixed(2);
            // Kept live even while the mobile cart sheet is collapsed, so the operator can see
            // there's something in progress without needing to open it first.
            const summary = document.getElementById('cartHeaderSummary');
            summary.textContent = itemCount ? (itemCount + (itemCount === 1 ? ' item · ' : ' items · ') + CURRENCY_CODE + ' ' + total.toFixed(2)) : '';
            return total;
        }

        document.getElementById('cartHeaderToggle').addEventListener('click', function () {
            document.querySelector('.pos-cart-pane').classList.toggle('expanded');
        });

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
        const posMethodList = PAYMENT_METHODS.length ? PAYMENT_METHODS : ['Cash'];
        // EFT Terminal is the fastest, most reconciliation-friendly method when it's on offer
        // at all — default to it rather than whichever method happens to sort first, so a
        // clerk doesn't have to remember to switch off Cash every single sale.
        const posDefaultMethod = posMethodList.includes('EFT Terminal') ? 'EFT Terminal' : posMethodList[0];
        posMethodList.forEach(function (m) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-method-btn' + (m === posDefaultMethod ? ' active' : '');
            btn.innerHTML = '<i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i>' + m;
            btn.addEventListener('click', function () {
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
            });
            methodRow.appendChild(btn);
            if (m === posDefaultMethod) { selectedMethod = m; }
        });

        let toastHideTimer = null;
        function showToast(message, isError) {
            // A warning/error interrupts with a real popup — easy to miss as a bottom-corner
            // toast when attention is on the center of the screen during a sale. A plain
            // success confirmation stays as the quieter corner toast.
            if (isError) {
                document.getElementById('posWarningMessage').textContent = message;
                document.getElementById('posWarningOverlay').classList.add('active');
                return;
            }
            if (toastHideTimer) { clearTimeout(toastHideTimer); toastHideTimer = null; }
            const toast = document.getElementById('posToast');
            toast.textContent = message;
            toast.classList.remove('error');
            toast.style.display = 'block';
            toastHideTimer = setTimeout(function () { toast.style.display = 'none'; }, 2200);
        }
        document.getElementById('posWarningOkBtn').addEventListener('click', function () {
            document.getElementById('posWarningOverlay').classList.remove('active');
        });

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

        // Which payment flow currently owns the shared eft-modal-overlay DOM — Linkly's own
        // handlers below and the SCI module (sci-action-framework.js) both attach listeners
        // to the SAME cancel/status elements, so each must no-op on a click that isn't theirs.
        let activeEftProvider = null;
        let eftModalLastSignature = null;
        let eftModalKeysSignature = null;
        function showEftModal(amount) {
            activeEftProvider = 'linkly';
            eftModalAmount.textContent = CURRENCY_CODE + ' ' + amount.toFixed(2);
            eftModalLastSignature = null;
            setEftModalStatus(['Starting…'], 'pending');
            updateEftModalKeys(null, null);
            document.getElementById('eftModalActionFramework').hidden = true;
            document.getElementById('eftModalActionFramework').innerHTML = '';
            document.getElementById('eftModalOverride').hidden = true;
            document.getElementById('eftModalCancelBtn').hidden = false;
            document.getElementById('eftModalCancelBtn').textContent = 'Cancel Payment';
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
            activeEftProvider = null;
            eftModalOverlay.classList.remove('active');
            eftCurrentSessionId = null;
            updateEftModalKeys(null, null);
        }

        // CBA Smart Terminal (mx51 SCI) payment flow — shares the same modal DOM as the
        // Linkly flow above (see activeEftProvider guards) but is driven by
        // sci-action-framework.js's generic Action Framework renderer/poller instead of
        // Linkly's fixed 4-key layout.
        const sciPaymentFlow = SciActionFramework.createFlow({
            startUrl: CBA_SCI_CHARGE_START_URL,
            statusUrlBase: CBA_SCI_CHARGE_STATUS_URL_BASE,
            actionUrlBase: CBA_SCI_CHARGE_ACTION_URL_BASE,
            cancelUrlBase: CBA_SCI_CHARGE_CANCEL_URL_BASE,
            overrideUrlBase: CBA_SCI_CHARGE_OVERRIDE_URL_BASE,
            csrfToken: CSRF_TOKEN,
            currencyCode: CURRENCY_CODE,
            attemptStorageKey: 'sciTicketAttempt',
            el: {
                overlay: eftModalOverlay,
                amount: eftModalAmount,
                statusBox: eftModalStatusBox,
                statusLine1: eftModalStatusLine1,
                statusLine2: eftModalStatusLine2,
                actionContainer: document.getElementById('eftModalActionFramework'),
                cancelBtn: document.getElementById('eftModalCancelBtn'),
                overrideBox: document.getElementById('eftModalOverride'),
                overrideYesBtn: document.getElementById('eftModalOverrideYes'),
                overrideNoBtn: document.getElementById('eftModalOverrideNo'),
                overrideKeepWaitingBtn: document.getElementById('eftModalOverrideKeepWaiting'),
            },
            buildStartBody: function (attempt) {
                return { record_type: 'ticket_order', cart_json: JSON.stringify(attempt.cart || []) };
            },
            onToast: function (message) { showToast(message, true); },
            onApproved: function (donationId) {
                activeEftProvider = null;
                showToast('Sale recorded — printing…');
                openPrintView(donationId);
                resetCart();
            },
            onDeclined: function (message) {
                activeEftProvider = null;
                showToast(message || 'Card declined.', true);
            },
            onUnresolved: function (message) {
                activeEftProvider = null;
                showToast(message || 'No final result was received — check before retrying.', true);
            },
            onLocalCancel: function () {
                activeEftProvider = null;
                showToast('Sale cancelled.', true);
            },
        });

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

                let selectedTerminal = null;
                if (selectedMethod === 'EFT Terminal') {
                    selectedTerminal = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                    if (!selectedTerminal || !selectedTerminal.paired) {
                        // Deliberately not embedding currentTerminalLabel() here — a terminal's
                        // own label can carry a provider's brand name (as "mx51 Certification
                        // Terminal" did), which has no place leaking into a customer-facing
                        // error. Matches the server's own certification-required wording
                        // (SCITX01).
                        showToast('No active pairings found — check the terminal picker.', true);
                        return;
                    }
                }
                btn.disabled = true;

                if (selectedMethod === 'EFT Terminal') {
                    if (selectedTerminal.provider === 'cba_sci') {
                        activeEftProvider = 'cba_sci';
                        sciPaymentFlow.start(btn, {
                            clientRef: newClientRef(),
                            amount: total,
                            name: name || 'Customer',
                            email: '',
                            mobile: mobile,
                            cart: cartLines,
                            terminalId: selectedTerminalId,
                        });
                        return;
                    }

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
            if (activeEftProvider !== 'linkly') { return; }
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

            // Matches mx51's own 60-second recommendation (SCIREC03) for how long a POS should
            // wait before surfacing a recovery prompt, applied here too for consistency across
            // both providers — see event-pos-donation.blade.php's own pollEftTransaction() for
            // the full reasoning (this guard only stops local polling, it's always safe to
            // resume).
            if (Date.now() - startedAt > 60000) {
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
                return;
            }

            // CBA Smart Terminal has no server-authoritative recovery query yet (unlike
            // PENDING_EFT_RECOVERY above) — this same-tab-refresh fallback is all that's
            // wired up for it so far.
            activeEftProvider = 'cba_sci';
            if (!sciPaymentFlow.resumeFromStorage(posSaveBtn)) {
                activeEftProvider = null;
            }
        });
    </script>

    @include('admin.partials.eft-terminal-settings-modal')
</body>
</html>
