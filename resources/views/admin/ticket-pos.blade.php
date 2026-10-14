<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Ticket Sales POS</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A; --maroon-dark: #4A0A12; --gold: #C89B3C; --gold-hover: #A67C2B;
            --cream: #F9F3E7; --white: #FFFFFF;
            /* Firmer than the old #F0E5D6 — a POS is read at a glance and worked fast, so
               every border needs to actually register against the white/cream around it. */
            --border: #D9CBB0; --shade: #F2ECE0; --text-primary: #1F2A37;
            --text-secondary: #6B7280; --success: #10B981; --error: #EF4444;
            /* A cooler accent for the Archana/Poojas toggle, deliberately not maroon/gold —
               those are already spoken for by the ticket tiles and payment buttons, and a
               third, blocking maroon panel at the very top of the screen read as too heavy. */
            --accent-blue: #2563EB; --accent-blue-tint: #EFF4FF;
            /* One shared radius scale, deliberately tighter than the old 12-22px range — a
               terminal/POS screen reads as more purposeful with crisp, moderate corners than
               with soft app-style bubbles. */
            --radius-sm: 8px; --radius-md: 10px; --radius-lg: 14px;
            /* Collapsed width of the right-docked side nav (see .pos-side-nav) — a single
               source so .pos-body's own reserved space and the mobile cart sheet's right
               offset can never drift out of sync with the nav's own CSS. */
            --nav-w: 64px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; height: 100%; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); display: flex; flex-direction: column; }
        button, input, select, textarea { font-family: inherit; }
        /* POS hardening: no accidental text selection/callouts from a fast tap-and-hold,
           and no 300ms ghost-click delay on older mobile Safari/Chrome. */
        button, .pos-item-tile, .pos-method-btn { -webkit-user-select: none; user-select: none; touch-action: manipulation; }

        /* Right-docked icon rail — replaces the old horizontal topbar entirely. Fixed, not
           part of flex flow, so it never reflows the item grid/cart as it expands; expanding
           draws it open as an overlay drawer (wider, shadowed) over the content instead of
           pushing that content left, which is what keeps the grid's own column count stable
           regardless of nav state. */
        .pos-side-nav {
            position: fixed; top: 0; right: 0; bottom: 0; width: var(--nav-w); z-index: 50;
            background: linear-gradient(180deg, var(--maroon), var(--maroon-dark));
            display: flex; flex-direction: column; box-shadow: -4px 0 18px rgba(74,10,18,0.25);
            padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .pos-side-nav.expanded { width: 220px; }
        .pos-side-nav-toggle { background: rgba(255,255,255,0.12); border: none; color: white; height: 46px; flex-shrink: 0; font-size: 1.05rem; display: flex; align-items: center; justify-content: center; }
        .pos-side-nav-toggle:hover { background: rgba(255,255,255,0.22); }
        .pos-side-nav.expanded .pos-side-nav-toggle i { transform: rotate(180deg); }
        .pos-side-nav-items { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; padding: 6px 0; }
        .pos-nav-divider { height: 1px; background: rgba(255,255,255,0.15); margin: 6px 14px; flex-shrink: 0; }
        .pos-nav-item {
            position: relative; display: flex; align-items: center; gap: 12px; justify-content: center;
            padding: 13px 0; background: transparent; border: none; color: rgba(255,255,255,0.75);
            width: 100%; text-decoration: none; flex-shrink: 0;
        }
        .pos-side-nav.expanded .pos-nav-item { justify-content: flex-start; padding-left: 20px; padding-right: 14px; }
        .pos-nav-item:hover { color: white; background: rgba(255,255,255,0.08); }
        .pos-nav-item i { font-size: 1.2rem; width: 22px; text-align: center; flex-shrink: 0; }
        .pos-nav-item-icon-img { width: 22px; height: auto; display: block; flex-shrink: 0; }
        .pos-nav-label { display: none; font-weight: 700; font-size: 0.85rem; white-space: nowrap; overflow: hidden; }
        .pos-side-nav.expanded .pos-nav-label { display: inline; }
        .pos-nav-item.active { color: var(--gold); background: rgba(255,255,255,0.1); }
        .pos-nav-item.active::before { content: ''; position: absolute; left: 0; top: 8px; bottom: 8px; width: 3px; background: var(--gold); border-radius: 0 3px 3px 0; }
        /* Flags that EFT setup is incomplete — only ever shown when no terminal is paired yet. */
        .pos-nav-item-setup::after { content: ''; position: absolute; top: 9px; right: 14px; width: 9px; height: 9px; border-radius: 50%; background: var(--gold); border: 1.5px solid var(--maroon-dark); }

        {{-- User account item's flyout — anchored flush to the rail's own left edge via
             right:100% on its relatively-positioned wrapper, so it lands in the same place
             regardless of whether the rail is collapsed or expanded (no width math needed). --}}
        .pos-nav-user-wrap { position: relative; flex-shrink: 0; }
        .pos-user-flyout {
            position: absolute; right: 100%; bottom: 0; margin-right: 8px; z-index: 60;
            background: var(--white); color: var(--text-primary); border-radius: var(--radius-md);
            box-shadow: 0 8px 24px rgba(0,0,0,0.25); min-width: 210px; padding: 6px; display: none;
        }
        .pos-user-flyout.open { display: block; }
        .pos-user-flyout a, .pos-user-flyout button {
            display: flex; align-items: center; gap: 10px; width: 100%; text-align: left;
            padding: 10px 12px; border-radius: 8px; border: none; background: transparent;
            color: var(--text-primary); font-weight: 600; font-size: 0.88rem; text-decoration: none;
        }
        .pos-user-flyout a:hover, .pos-user-flyout button:hover { background: var(--shade); }

        {{-- Reserve the rail's own (collapsed) width so the item grid/cart never render
             underneath it — the rail itself overlays rather than pushes when expanded, so
             this stays fixed at the collapsed width regardless of nav state. Must itself be
             a flex column filling body's own height (not just a plain block) — otherwise
             .pos-body's "flex:1" below has no real height to grow into, and everything
             downstream that depends on actually reaching full viewport height (the cart
             pane's payment controls pinned to the bottom, the EFT Settings iframe filling
             its pane instead of collapsing to a tiny default size) silently breaks. --}}
        .pos-app-shell { display: flex; flex-direction: column; flex: 1; min-height: 0; padding-right: var(--nav-w); }

        {{-- Which view is on screen — one shared heading style for every view (Archana/
             Poojas share the one #posViewHeaderTitle element, text swapped by JS; Till and
             EFT Settings each have their own static one). The grid/table toggle (Poojas
             only) sits on the same row, right next to the title. --}}
        .pos-view-header { margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .pos-view-header h2 { margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; }
        .pos-view-header h2 i { color: var(--gold-hover); }

        {{-- Till — the Settle Till figures as their own full view (no popup), swapped in by
             the side nav exactly like Archana/Poojas. --}}
        .pos-till-pane { display: none; flex: 1; min-height: 0; overflow-y: auto; padding: 18px; }
        .pos-till-pane.active { display: block; }

        {{-- EFT Terminal Settings — the same pairing page as before, now its own full view
             (iframe) instead of a popup over the rest of the page. --}}
        .pos-eft-settings-pane { display: none; flex-direction: column; flex: 1; min-height: 0; padding: 18px; }
        .pos-eft-settings-pane.active { display: flex; }

        {{-- Shared by every iframe-embedded view on this page (Terminal Settings, and the
             order-view receipt's own iframe in cash-settlement-pane.blade.php reuses this
             same pattern) — an iframe shows nothing at all while its own page loads, which
             reads as a broken/frozen panel rather than "still loading" without this. --}}
        .pos-iframe-wrap { position: relative; flex: 1; min-height: 0; }
        .pos-iframe-wrap iframe { width: 100%; height: 100%; border: 1px solid var(--border); border-radius: var(--radius-md); background: #FAFAF8; }
        .pos-iframe-loading {
            position: absolute; inset: 0; display: none; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
            background: #FAFAF8; border: 1px solid var(--border); border-radius: var(--radius-md); z-index: 1;
            color: var(--text-secondary); font-weight: 600; font-size: 0.86rem;
        }
        .pos-iframe-loading.active { display: flex; }
        .pos-iframe-spinner { width: 34px; height: 34px; border-radius: 50%; border: 3px solid var(--border); border-top-color: var(--gold-hover); animation: pos-iframe-spin 0.8s linear infinite; }
        @keyframes pos-iframe-spin { to { transform: rotate(360deg); } }

        /* Which physical terminal the "EFT Terminal" payment method will actually charge —
           styled like an actual dropdown/select (labeled, chevron on the right) since tapping it
           opens a list of terminals to choose from, sharing one line with Complete Sale roughly
           half-and-half, only while that method is selected, rather than a whole extra row above
           it (an earlier design) or a topbar icon indistinguishable from Settings/Account (the
           design before that). The terminal's name is printed right on the control, not hidden
           behind a tooltip — a clerk needs to see which terminal is live at a glance. */
        {{-- flex-end, not stretch — the label sits above the box on its own, so the box and
             Complete Sale button end up exactly the same height (both min-height: 56px) with
             their bottom edges aligned, rather than the sale button being stretched taller to
             match the label's extra space above the box. --}}
        .pos-actions-main-row { display: flex; gap: 10px; align-items: flex-end; }
        .pos-actions-main-row .pos-save-btn { flex: 1 1 0; width: auto; }

        /* ---------- Full-width POS: items grid on the left, cart panel on the right ---------- */
        .pos-body { flex: 1; min-height: 0; display: flex; flex-direction: column; }
        @media (min-width: 900px) { .pos-body { flex-direction: row; } }

        .pos-items-pane { flex: 1; min-height: 0; overflow-y: auto; padding: 18px; }

        {{-- Grid/table view toggle — Poojas-only (the Archana grid has no table equivalent),
             a compact segmented control using the same light-blue accent the old in-page
             Archana/Poojas toggle used to use, now that that toggle itself has moved to the
             side nav. --}}
        .pos-view-toggle { display: inline-flex; gap: 2px; padding: 3px; border-radius: var(--radius-sm); background: var(--shade); border: 1px solid var(--border); flex-shrink: 0; }
        .pos-view-toggle-btn {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            padding: 7px 14px; border-radius: 6px; border: none;
            background: transparent; color: var(--text-secondary); font-weight: 700; font-size: 0.82rem;
        }
        .pos-view-toggle-btn.active { background: var(--white); color: var(--accent-blue); box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
        .pos-view-toggle-btn i { font-size: 0.95rem; }

        {{-- Table view — same per-category sections/filtering as the grid, just a denser
             row layout for a counter that wants to scan names/fees quickly rather than tap
             through visual tiles. --}}
        {{-- Indented and visually lifted (white card, border, shadow) relative to the plain
             category heading above it — without that, rows read as a continuation of the
             heading's own text block instead of a distinct group of items under it. --}}
        .pos-category-table {
            display: none; width: calc(100% - 14px); margin: 0 0 4px 14px; border-collapse: separate; border-spacing: 0;
            background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-md);
            overflow: hidden; box-shadow: 0 2px 8px rgba(31,42,55,0.06);
        }
        .pos-menu-panel[data-menu="poojas"].table-view .pos-category-tiles { display: none; }
        .pos-menu-panel[data-menu="poojas"].table-view .pos-category-table { display: table; }
        .pos-table-row { cursor: pointer; border-bottom: 1px solid var(--border); }
        .pos-table-row:last-child { border-bottom: none; }
        .pos-table-row:hover { background: var(--shade); }
        .pos-table-row.pos-category-hidden { display: none; }
        .pos-table-row td { padding: 11px 14px; vertical-align: middle; }
        .pos-table-row td:first-child { border-left: 3px solid transparent; }
        .pos-table-row:hover td:first-child { border-left-color: var(--gold-hover); }
        .pos-table-name { font-weight: 700; font-size: 0.88rem; color: var(--text-primary); }
        .pos-table-desc { font-size: 0.78rem; color: var(--text-secondary); }
        .pos-table-price { font-weight: 700; font-family: 'Inter', sans-serif; font-size: 0.86rem; text-align: right; white-space: nowrap; }
        .pos-table-action { text-align: right; white-space: nowrap; }
        .pos-table-book-btn { padding: 6px 14px; border-radius: var(--radius-sm); border: 1.5px solid var(--gold-hover); background: color-mix(in srgb, var(--gold-hover) 10%, white); color: var(--gold-hover); font-weight: 700; font-size: 0.78rem; }

        {{-- Category filter chips — a narrower, lighter-weight affordance than the main
             menu above, so the two never compete for attention. Rounded-rect rather than
             a full pill, matching the rest of the page's own corner-radius scale. --}}
        {{-- Shares a row with the search box (see .pos-filter-row) — flex:1 so it takes
             whatever width the search box doesn't need, scrolling horizontally itself if
             there isn't room for every chip rather than wrapping the whole row. --}}
        .pos-filter-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .pos-category-tabs { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 2px; flex: 1 1 auto; min-width: 0; }
        .pos-category-tab {
            flex-shrink: 0; padding: 8px 16px; border-radius: var(--radius-sm); border: 1.5px solid var(--border);
            background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.82rem;
        }
        .pos-category-tab.active { border-color: var(--maroon); background: color-mix(in srgb, var(--maroon) 10%, white); color: var(--maroon); }
        .pos-item-tile.pos-category-hidden { display: none; }

        {{-- Each category gets its own labeled section instead of one undifferentiated
             grid — this is what keeps "All" from looking like a messy wall of tiles: every
             group is clearly headed, and a section disappears entirely (not just its tiles)
             once a filter/search leaves nothing in it. --}}
        .pos-category-section { margin-bottom: 22px; }
        .pos-category-section:last-child { margin-bottom: 0; }
        .pos-category-section.pos-section-hidden { display: none; }
        .pos-category-heading {
            font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--text-secondary); margin: 0 0 10px; padding-bottom: 6px; border-bottom: 1.5px solid var(--border);
        }
        {{-- Wider minimum than before (was 180px) — every tile is now a fixed, uniform
             height (see .pos-item-tile below) and reads left-to-right rather than
             top-to-bottom, so a wider column gives the description line real room instead
             of forcing the grid to grow taller to fit it. --}}
        .pos-category-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
        .tile-description {
            font-size: 0.72rem; line-height: 1.3; color: var(--text-secondary); font-weight: 500;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            text-transform: none; width: 100%;
        }

        /* Every tile is now a fixed, uniform, landscape-oriented card (image left, text
           right) rather than the old auto-height portrait layout — same reasoning the
           Donations/System Users tables already apply: a consistent row height reads far
           faster at a glance than cards that grow/shrink with how much description text
           happens to be on each one, and the extra width gives the description room to
           actually show instead of being squeezed under a tall, narrow name. */
        .pos-item-tile {
            position: relative; border-radius: var(--radius-md); padding: 0; border: none; cursor: pointer;
            background: color-mix(in srgb, var(--tile-accent) 12%, white); height: 112px;
            box-shadow: 0 2px 8px rgba(31,42,55,0.08); transition: transform 0.1s;
        }
        .pos-item-tile:active { transform: scale(0.97); }
        .pos-item-tile.in-cart { box-shadow: 0 0 0 3px var(--tile-accent), 0 4px 14px rgba(31,42,55,0.16); }
        .tile-frame {
            position: relative; height: 100%; box-sizing: border-box;
            border: 1.5px solid color-mix(in srgb, var(--tile-accent) 55%, white);
            border-radius: var(--radius-sm); padding: 10px 14px; background: color-mix(in srgb, var(--tile-accent) 4%, white);
            display: flex; align-items: center; gap: 12px; text-align: left;
        }
        .tile-corner { position: absolute; width: 14px; height: 14px; border: var(--tile-accent) solid; opacity: 0.6; }
        .tile-corner.tl { top: 4px; left: 4px; border-width: 2px 0 0 2px; }
        .tile-corner.tr { top: 4px; right: 4px; border-width: 2px 2px 0 0; }
        .tile-corner.bl { bottom: 4px; left: 4px; border-width: 0 0 2px 2px; }
        .tile-corner.br { bottom: 4px; right: 4px; border-width: 0 2px 2px 0; }
        .tile-image-wrap {
            width: 60px; height: 60px; border-radius: 50%; flex-shrink: 0;
            background: color-mix(in srgb, var(--tile-accent) 16%, white);
            border: 2px solid color-mix(in srgb, var(--tile-accent) 50%, white);
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        .tile-image-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .tile-image-wrap i { font-size: 1.5rem; color: var(--tile-accent); }
        .tile-text { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: 3px; }
        .tile-name { font-family: 'Playfair Display', Georgia, serif; font-weight: 800; font-size: 0.92rem; line-height: 1.2; color: var(--tile-accent); text-transform: uppercase; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tile-price-badge { background: var(--tile-accent); color: #fff; border-radius: 8px; padding: 4px 12px; font-family: 'Inter', sans-serif; font-weight: 700; font-size: 0.84rem; margin-top: auto; }
        .tile-qty-badge { position: absolute; top: -8px; right: -8px; background: var(--tile-accent); color: #fff; font-weight: 800; font-size: 0.85rem; min-width: 28px; height: 28px; border-radius: 50%; display: none; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.3); border: 2px solid #fff; z-index: 1; }
        .pos-item-tile.in-cart .tile-qty-badge { display: flex; }

        {{-- Search bar above the grid, same bordered-field look as the rest of the page. --}}
        .pos-items-search { position: relative; flex: 0 1 260px; }
        .pos-items-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); }
        .pos-items-search input { padding-left: 38px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); }
        .pos-items-empty { display: none; }

        {{-- Pooja booking tiles share the exact tile frame tickets use, with the
             --tile-accent kept gold on the icon circle/price badge (still visibly "a
             pooja, not a ticket") but the name itself in plain black, normal case —
             easier to read at a glance than an all-caps coloured title. --}}
        .pos-item-tile-pooja { --tile-accent: var(--gold-hover); }
        .pos-item-tile-pooja .tile-name { color: #1a1a1a; text-transform: none; }
        .pos-item-tile-custom { border-style: dashed; }
        .pos-item-tile-custom .tile-image-wrap i { color: var(--gold-hover); }

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
        {{-- The cart only ever renders once an item has been added (see renderCart()'s own
             .has-items toggle) — an empty "Order" panel sitting on screen all day was chrome
             the operator had to look past on every sale, and the items grid now reclaims
             its full width/height until there's actually an order in progress. --}}
        .pos-cart-pane:not(.has-items) { display: none; }
        .pos-body:has(.pos-cart-pane:not(.has-items)) .pos-items-pane { padding-bottom: 18px; }
        .pos-cart-header { padding: 16px 18px; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; gap: 10px; }
        .pos-cart-header-summary { display: none; margin-left: auto; align-items: center; gap: 10px; font-weight: 700; font-size: 0.92rem; color: var(--gold-hover); }
        .pos-cart-header-chevron { display: none; transition: transform 0.2s; color: var(--text-secondary); }
        .pos-cart-list { flex: 1; min-height: 80px; overflow-y: auto; padding: 0 18px; }

        @media (max-width: 899px) {
            /* Room for the always-visible collapsed bar so the last row of tickets never
               sits underneath it. */
            .pos-items-pane { padding-bottom: 78px; }
            .pos-cart-pane {
                position: fixed; left: 0; right: var(--nav-w); bottom: 0; z-index: 55;
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
        .cart-line-unit { font-size: 0.76rem; color: var(--text-secondary); font-family: 'Inter', sans-serif; }
        .cart-line-qty-controls { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .cart-qty-btn { width: 30px; height: 30px; border-radius: 8px; border: 1.5px solid var(--border); background: var(--white); font-size: 1rem; font-weight: 800; color: var(--gold-hover); }
        .cart-qty-input { width: 40px; text-align: center; border: 1.5px solid var(--border); border-radius: 8px; padding: 4px 2px; font-weight: 700; font-family: 'Inter', sans-serif; }
        .cart-line-total { width: 66px; text-align: right; font-weight: 700; font-family: 'Inter', sans-serif; font-size: 0.88rem; flex-shrink: 0; }
        .cart-line-remove { width: 30px; height: 30px; border-radius: 8px; border: none; background: var(--error); color: #fff; font-size: 0.85rem; flex-shrink: 0; }

        .pos-cart-footer { flex-shrink: 0; padding: 12px 18px 16px; border-top: 2px solid var(--border); }
        .cart-total-row { display: flex; justify-content: space-between; font-weight: 800; font-size: 1.3rem; color: var(--text-primary); padding: 6px 0 12px; }
        .cart-total-row span:last-child { font-family: 'Inter', sans-serif; }

        .pos-field-label { display: block; font-weight: 700; font-size: 0.72rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .pos-input { width: 100%; padding: 9px 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 0.88rem; font-weight: 600; color: var(--text-primary); background: var(--white); min-height: 38px; }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
        .pos-row.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }

        .pos-method-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .pos-method-btn { flex: 1 1 calc(50% - 8px); min-width: 90px; padding: 10px 8px; min-height: 46px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--shade); font-weight: 700; font-size: 0.82rem; color: var(--text-secondary); }
        {{-- Booking popup's method row always has exactly 3 options (Cash/Bank/Card) — one
             even row instead of the ticket cart's own 2-per-row wrap, which left the third
             button alone on its own row. --}}
        .pos-method-row-equal .pos-method-btn { flex: 1 1 0; }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.3); }
        .pos-method-btn i { display: block; font-size: 1.1rem; margin-bottom: 2px; }
        /* No paired terminal at all — EFT Terminal isn't a usable option right now. */
        .pos-method-btn.disabled { opacity: 0.45; cursor: not-allowed; }
        .pos-method-btn.disabled.active { border-color: var(--border); background: var(--shade); color: var(--text-secondary); box-shadow: none; }

        {{-- Solid maroon, same as event-pos-donation's own PAY button — the two POS pages'
             primary action now reads as one shared brand colour instead of each inventing
             its own (this one was gold, donations' was maroon). --}}
        .pos-save-btn { width: 100%; padding: 16px; border-radius: var(--radius-md); border: none; background: var(--maroon); color: white; font-weight: 800; font-size: 1.1rem; box-shadow: 0 10px 26px rgba(107,15,26,0.3); min-height: 56px; }
        .pos-save-btn:disabled { opacity: 0.55; }
        .pos-save-btn:active { transform: scale(0.98); }
        .pos-save-btn-terminal { font-size: 0.74rem; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; color: rgba(255,255,255,0.72); display: block; }

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
        .qty-modal-price { color: var(--gold-hover); font-weight: 700; font-family: 'Inter', sans-serif; margin-bottom: 18px; }
        .qty-modal-controls { display: flex; align-items: center; justify-content: center; gap: 18px; margin-bottom: 22px; }
        {{-- Circular, icon-driven (not the plain −/+ text characters, which rendered thin
             and off-centre depending on the font) steppers — a filled gold circle for the
             more common "add another" action, a quieter outline for "remove one". --}}
        .qty-modal-btn {
            width: 52px; height: 52px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-size: 1.3rem;
            border: none; transition: transform 0.08s;
        }
        .qty-modal-btn:active { transform: scale(0.92); }
        .qty-modal-btn-minus { background: var(--shade); border: 1.5px solid var(--border); color: var(--text-secondary); }
        .qty-modal-btn-minus:hover { background: var(--border); }
        .qty-modal-btn-plus { background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; box-shadow: 0 6px 16px rgba(200,155,60,0.35); }
        .qty-modal-btn-plus:hover { filter: brightness(1.05); }
        .qty-modal-value { font-size: 2rem; font-weight: 800; font-family: 'Inter', sans-serif; min-width: 60px; }
        .qty-modal-actions { display: flex; gap: 10px; }
        .qty-modal-cancel { flex: 1; padding: 13px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; }
        .qty-modal-confirm { flex: 2; padding: 13px; border-radius: var(--radius-sm); border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; }

        .eft-modal-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal { background: var(--white); border-radius: var(--radius-lg); width: 100%; max-width: 460px; max-height: calc(100vh - 40px); box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; display: flex; flex-direction: column; position: relative; }
        @property --eft-chase-angle { syntax: '<angle>'; inherits: false; initial-value: 0deg; }
        .eft-modal::before {
            content: ''; position: absolute; inset: 0; border-radius: inherit; padding: 3px;
            background: conic-gradient(from var(--eft-chase-angle, 0deg), transparent 0deg, var(--gold) 70deg, transparent 150deg, transparent 360deg);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            opacity: 0; pointer-events: none; z-index: 5;
        }
        .eft-modal:has(.eft-modal-status-box.pending)::before {
            opacity: 1; animation: eftBorderChase 2.4s linear infinite;
        }
        @keyframes eftBorderChase { to { --eft-chase-angle: 360deg; } }
        {{-- Header colour reacts to #eftModalStatusBox's own state class (already toggled by the
             existing, untouched polling JS) via :has() — blue while in progress, green once
             approved, red once declined. Zero JS changes needed. --}}
        .eft-modal-header { background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #a70918 0%, #d95f6b 100%); color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em; font-size: 0.95rem; text-transform: uppercase; flex-shrink: 0; }
        .eft-modal:has(.eft-modal-status-box.success) .eft-modal-header { background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #12b76a 0%, #039855 100%); }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header { background: radial-gradient(ellipse at top right, rgba(255,255,255,0.14), transparent 60%), linear-gradient(135deg, #F04438 0%, #D92D20 55%, #B42318 100%); }
        .eft-modal-header-icon-error, .eft-modal-header-title-error { display: none; }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-icon-default, .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-title-default { display: none; }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-icon-error, .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-title-error { display: inline-block; }
        {{-- min-height:0 is the flexbox gotcha fix — without it a flex child never actually
             shrinks to scroll, it just overflows its parent instead, which is exactly how a
             long Action Framework response (mx51's "13.37" full-element test case among them)
             used to push the whole modal past the viewport instead of scrolling internally. --}}
        .eft-modal-body { padding: 22px 22px 20px; overflow-y: auto; min-height: 0; }
        .eft-modal-amount { font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; font-size: 2.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 14px; }
        .eft-modal-status-box { background: var(--cream); border: 2px solid var(--border); border-radius: var(--radius-md); padding: 16px; min-height: 72px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; margin-bottom: 16px; transition: background-color .15s, border-color .15s; }
        .eft-modal-status-box.success { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.4); }
        .eft-modal-status-box.error { background: #FEF3F2; border-color: #FEE4E2; flex-direction: row; justify-content: flex-start; text-align: left; }
        {{-- A pulsing card icon (like a contactless tap) instead of a plain spinning ring — this
             is specifically a payment being processed, not a generic "loading" moment. --}}
        .eft-modal-payment-anim { display: none; flex-direction: column; align-items: center; gap: 6px; margin: 0 auto 4px; }
        .eft-modal-status-box.pending .eft-modal-payment-anim { display: flex; }
        .eft-modal-payment-anim i { font-size: 1.3rem; color: var(--gold-hover); }
        .eft-modal-payment-anim-dots { display: flex; gap: 5px; }
        .eft-modal-payment-anim-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gold-hover); opacity: 0.3; animation: eftDotPulse 1.2s ease-in-out infinite; }
        .eft-modal-payment-anim-dot:nth-child(2) { animation-delay: 0.2s; }
        .eft-modal-payment-anim-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes eftDotPulse { 0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); } 40% { opacity: 1; transform: scale(1.2); } }
        .eft-modal-status-icon { font-size: 2rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error { display: flex; align-items: center; justify-content: center; margin: 0; flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; background: #F04438; color: #fff; font-size: 1.05rem; }
        .eft-modal-status-divider { display: none; width: 2px; align-self: stretch; background: #FECDCA; margin: 0 12px; flex-shrink: 0; }
        .eft-modal-status-box.error .eft-modal-status-divider { display: block; }
        .eft-modal-status-text { display: contents; }
        .eft-modal-status-box.error .eft-modal-status-text { display: flex; flex-direction: column; gap: 2px; }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { text-align: left; color: #D92D20; }
        .eft-modal-decline-hint { display: none; color: #667085; font-size: 0.88rem; line-height: 1.5; margin: 12px 0 0; }
        .eft-modal:has(.eft-modal-status-box.error):not(:has(.eft-modal-status-box.status-cancelled)) .eft-modal-decline-hint { display: block; }
        .eft-modal-cancel-btn { width: 100%; padding: 14px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem; }
        .eft-modal-cancel-btn:active { background: var(--cream); }
        .eft-modal-cancel-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-cancel-btn { background: linear-gradient(135deg, #1570EF, #0560D8); color: #fff; border: none; }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-cancel-btn:active { filter: brightness(0.92); }
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
        .sci-af-btn { flex: 1 1 auto; min-width: 90px; padding: 10px 10px; border-radius: var(--radius-sm); border: 2px solid #1570EF; font-weight: 700; font-size: 0.88rem; color: #1570EF; background: var(--white); }
        .sci-af-btn:active { background: #EFF6FF; }
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
        #eftModalActionFramework { margin-bottom: 10px; }

        /* Manual recovery override — CBA SCI has no cancel API, so once a transaction has
           actually started, "Cancel" is replaced by an honest "confirm the real outcome"
           prompt instead of pretending the payment can be stopped mid-flight. */
        {{-- Deliberately plain — an amber "unknown" icon, a question, Yes/No — not styled as a
             decline (it isn't one: the whole point is that the real outcome is still unknown). --}}
        .eft-modal-override-icon { width: 48px; height: 48px; border-radius: 50%; background: #F2A900; color: #fff; font-size: 1.35rem; display: flex; align-items: center; justify-content: center; margin: 4px auto 12px; }
        .eft-modal-override-title { font-weight: 800; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 8px; }
        .eft-modal-override-subtitle { font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 16px; }
        .eft-modal-override-actions { display: flex; gap: 10px; margin-bottom: 10px; justify-content: center; }
        .eft-override-btn { flex: 0 1 110px; padding: 11px 10px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--white); font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
        .eft-override-btn:active { background: var(--cream); }
        .eft-override-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .eft-modal-override-saving { font-weight: 700; font-size: 1rem; color: var(--text-secondary); margin: 10px 0; }
        .eft-modal-countdown { font-size: 0.68rem; color: var(--text-secondary); margin: 8px 0 0; text-align: center; }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-countdown { display: none; }
        {{-- The override is a genuinely different moment from a decline (mx51's own prompt
             literally asks whether it went through — the outcome is unknown, not negative), so
             none of the "Payment Declined" branding (header text/icon swap, big X, decline hint)
             applies here even though the status box also uses the 'error' tint for both. These
             rules win over the .error-driven ones above whenever the override is visible. --}}
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header { background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #a70918 0%, #d95f6b 100%); }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-title-error, .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-icon-error { display: none; }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-title-default, .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-icon-default { display: inline-block; }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-status-box, .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-decline-hint { display: none; }
    </style>
</head>
<body>
    @include('partials.test-banner')

    {{-- Right-docked icon nav — replaces the old horizontal topbar. Archana/Poojas/Till/
         EFT Terminal Settings are each their own full view (swapped by setActiveView()
         below); the User flyout (Manage PIN / Logout) keeps its old behaviour, just
         relocated. No Pooja Portal link — a POS operator never needed it from here, it
         was just another way off the page. --}}
    <nav class="pos-side-nav" id="posSideNav">
        <div class="pos-side-nav-items">
            <button type="button" class="pos-nav-item active" data-view="archana"><i class="bi bi-flower1"></i><span class="pos-nav-label">Archana</span></button>
            @if($canCreateBookings)
            <button type="button" class="pos-nav-item" data-view="poojas"><i class="bi bi-journal-bookmark-fill"></i><span class="pos-nav-label">Poojas</span></button>
            @endif
            @if($canSettleCash)
            <button type="button" class="pos-nav-item" data-view="till"><i class="bi bi-cash-stack"></i><span class="pos-nav-label">Till</span></button>
            @endif
            <div class="pos-nav-divider"></div>
            <!-- Always visible, regardless of whether a terminal is currently paired — the
                 Switch control near Pay only ever shows up while "EFT Terminal" is the
                 selected payment method (and that method stays disabled with nothing
                 paired to pick), so it's not a reliable way in. This is the one entry point
                 to terminal settings/pairing a pos-level user can always reach — see
                 EftTerminalAccess's own docblock on why this tier is trusted to pair a
                 terminal from here. The gold dot flags that setup still needs attention
                 (nothing paired yet); it clears once a terminal is paired, but the item
                 itself never disappears. -->
            <button type="button" class="pos-nav-item" id="posEftSettingsTopbarBtn" data-view="eft-settings" title="EFT Terminal Settings"><i class="bi bi-credit-card-2-front-fill"></i><span class="pos-nav-label">EFT Terminal</span></button>
        </div>
        <div class="pos-nav-user-wrap">
            <button type="button" class="pos-nav-item" id="posNavUserBtn" title="Account"><i class="bi bi-person-fill"></i><span class="pos-nav-label">Account</span></button>
            <div class="pos-user-flyout" id="posUserFlyout">
                @if($canManagePosPin)
                <a href="{{ route('pos.pin.edit') }}"><i class="bi bi-grid-3x3-gap-fill"></i>Manage POS PIN</a>
                @endif
                <a href="{{ route('logout', ['from' => 'pos']) }}" id="posLogoutLink"><i class="bi bi-box-arrow-right"></i>Logout</a>
            </div>
        </div>
        {{-- The expand/collapse control sits at the bottom of the rail, out of the way of
             the actual navigation items above it. --}}
        <button type="button" class="pos-side-nav-toggle" id="posSideNavToggle" title="Expand menu"><i class="bi bi-chevron-left"></i></button>
    </nav>

    <div class="pos-app-shell">
    <div class="pos-body">
        <div class="pos-items-pane">
            @php
                $ticketsByCategory = $tickets->groupBy(fn($t) => $t->category ?: 'Archana');
                $poojasByCategory = ($bookingPoojas ?? collect())->groupBy(fn($p) => $p->category ?: 'General');
                // Preserves $recentPoojaIds' own most-recent-first order (a plain ->whereIn()
                // would sort by pooja_id instead).
                $recentPoojas = collect($recentPoojaIds ?? [])
                    ->map(fn($id) => ($bookingPoojas ?? collect())->firstWhere('pooja_id', $id))
                    ->filter();
            @endphp

            {{-- Which view is on screen — text updated by setActiveView() below, same
                 element for both Archana and Poojas since they share this pane. The grid/
                 table toggle lives here too (Poojas only — see setActiveView()'s own
                 visibility toggle for it), right next to the title rather than buried
                 inside the item grid below. --}}
            <div class="pos-view-header">
                <h2 id="posViewHeaderTitle"><i class="bi bi-flower1 me-2"></i>Archana Tickets</h2>
                @if($canCreateBookings)
                <div class="pos-view-toggle d-none" id="posPoojaViewToggle">
                    <button type="button" class="pos-view-toggle-btn active" data-view="grid"><i class="bi bi-grid-3x3-gap-fill"></i> Grid</button>
                    <button type="button" class="pos-view-toggle-btn" data-view="table"><i class="bi bi-table"></i> Table</button>
                </div>
                @endif
            </div>

            {{-- Category filter chips and search share one row — the search box pinned to
                 the right of whichever tab bar is active (or alone, flush right, when
                 neither renders). --}}
            <div class="pos-filter-row">
                <div class="pos-items-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="posItemsSearch" class="form-control" placeholder="Search...">
                </div>

                @if($ticketsByCategory->keys()->count() > 1)
                <div class="pos-category-tabs" data-menu="archana">
                    <button type="button" class="pos-category-tab active" data-category="">All</button>
                    @foreach($ticketsByCategory->keys()->sort() as $cat)
                    <button type="button" class="pos-category-tab" data-category="{{ $cat }}">{{ $cat }}</button>
                    @endforeach
                </div>
                @endif
                @if($canCreateBookings && ($poojasByCategory->keys()->count() > 1 || $recentPoojas->isNotEmpty()))
                <div class="pos-category-tabs d-none" data-menu="poojas">
                    @if($recentPoojas->isNotEmpty())
                    <button type="button" class="pos-category-tab active" data-category="__recent__"><i class="bi bi-clock-history"></i> Recent</button>
                    <button type="button" class="pos-category-tab" data-category="">All</button>
                    @else
                    <button type="button" class="pos-category-tab active" data-category="">All</button>
                    @endif
                    @foreach($poojasByCategory->keys()->sort() as $cat)
                    <button type="button" class="pos-category-tab" data-category="{{ $cat }}">{{ $cat }}</button>
                    @endforeach
                </div>
                @endif
            </div>

            <div id="itemsGrid">
                {{-- ARCHANA MENU --}}
                <div class="pos-menu-panel" data-menu="archana">
                    @forelse($ticketsByCategory as $category => $categoryTickets)
                    <div class="pos-category-section" data-category="{{ $category }}">
                        <h6 class="pos-category-heading">{{ $category }}</h6>
                        <div class="pos-category-tiles">
                            @foreach($categoryTickets as $ticket)
                            @php $accent = $ticket->background_color ?: '#6B0F1A'; @endphp
                            <button type="button" class="pos-item-tile" data-type="ticket" data-id="{{ $ticket->id }}" data-name="{{ $ticket->name }}" data-price="{{ $ticket->price }}"
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
                                    <span class="tile-text">
                                        <span class="tile-name">{{ $ticket->name }}</span>
                                        @if($ticket->description)
                                        <span class="tile-description">{{ $ticket->description }}</span>
                                        @endif
                                        <span class="tile-price-badge">{{ $temple['currency'] ?? '' }} {{ number_format($ticket->price, 2) }}</span>
                                    </span>
                                </span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-4">No active ticket types — add one in the Pooja Portal.</p>
                    @endforelse
                </div>

                {{-- POOJAS MENU --}}
                @if($canCreateBookings)
                <div class="pos-menu-panel d-none" data-menu="poojas">
                    <div class="pos-category-section" data-category="">
                        <div class="pos-category-tiles">
                            <button type="button" class="pos-item-tile pos-item-tile-pooja pos-item-tile-custom" data-type="pooja" data-is-custom="1" data-id="" data-name="Custom Pooja" data-price="">
                                <span class="tile-frame">
                                    <span class="tile-corner tl"></span>
                                    <span class="tile-corner tr"></span>
                                    <span class="tile-corner bl"></span>
                                    <span class="tile-corner br"></span>
                                    <span class="tile-image-wrap"><i class="bi bi-plus-circle"></i></span>
                                    <span class="tile-text">
                                        <span class="tile-name">Custom Pooja</span>
                                        <span class="tile-description">Not in the list — describe it and set your own fee</span>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <table class="pos-category-table">
                            <tbody>
                                <tr class="pos-table-row" data-type="pooja" data-is-custom="1" data-id="" data-name="Custom Pooja" data-price="">
                                    <td class="pos-table-name">Custom Pooja</td>
                                    <td class="pos-table-desc">Not in the list — describe it and set your own fee</td>
                                    <td class="pos-table-price">&nbsp;</td>
                                    <td class="pos-table-action"><button type="button" class="pos-table-book-btn">Book</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if($recentPoojas->isNotEmpty())
                    <div class="pos-category-section" data-category="__recent__">
                        <h6 class="pos-category-heading"><i class="bi bi-clock-history me-1"></i>Recently Booked</h6>
                        <div class="pos-category-tiles">
                            @foreach($recentPoojas as $pooja)
                            <button type="button" class="pos-item-tile pos-item-tile-pooja" data-type="pooja" data-is-custom="0" data-id="{{ $pooja->pooja_id }}" data-name="{{ $pooja->pooja_name }}" data-price="{{ $pooja->pooja_fee }}">
                                <span class="tile-frame">
                                    <span class="tile-corner tl"></span>
                                    <span class="tile-corner tr"></span>
                                    <span class="tile-corner bl"></span>
                                    <span class="tile-corner br"></span>
                                    <span class="tile-image-wrap"><i class="bi bi-journal-bookmark-fill"></i></span>
                                    <span class="tile-text">
                                        <span class="tile-name">{{ $pooja->pooja_name }}</span>
                                        @if($pooja->description)
                                        <span class="tile-description">{{ $pooja->description }}</span>
                                        @endif
                                        <span class="tile-price-badge">{{ $temple['currency'] ?? '' }} {{ number_format($pooja->pooja_fee, 2) }}</span>
                                    </span>
                                </span>
                            </button>
                            @endforeach
                        </div>
                        <table class="pos-category-table">
                            <tbody>
                                @foreach($recentPoojas as $pooja)
                                <tr class="pos-table-row" data-type="pooja" data-is-custom="0" data-id="{{ $pooja->pooja_id }}" data-name="{{ $pooja->pooja_name }}" data-price="{{ $pooja->pooja_fee }}">
                                    <td class="pos-table-name">{{ $pooja->pooja_name }}</td>
                                    <td class="pos-table-desc">{{ $pooja->description }}</td>
                                    <td class="pos-table-price">{{ $temple['currency'] ?? '' }} {{ number_format($pooja->pooja_fee, 2) }}</td>
                                    <td class="pos-table-action"><button type="button" class="pos-table-book-btn">Book</button></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @foreach($poojasByCategory as $category => $categoryPoojas)
                    <div class="pos-category-section" data-category="{{ $category }}">
                        <h6 class="pos-category-heading">{{ $category }}</h6>
                        <div class="pos-category-tiles">
                            @foreach($categoryPoojas as $pooja)
                            <button type="button" class="pos-item-tile pos-item-tile-pooja" data-type="pooja" data-is-custom="0" data-id="{{ $pooja->pooja_id }}" data-name="{{ $pooja->pooja_name }}" data-price="{{ $pooja->pooja_fee }}">
                                <span class="tile-frame">
                                    <span class="tile-corner tl"></span>
                                    <span class="tile-corner tr"></span>
                                    <span class="tile-corner bl"></span>
                                    <span class="tile-corner br"></span>
                                    <span class="tile-image-wrap"><i class="bi bi-journal-bookmark-fill"></i></span>
                                    <span class="tile-text">
                                        <span class="tile-name">{{ $pooja->pooja_name }}</span>
                                        @if($pooja->description)
                                        <span class="tile-description">{{ $pooja->description }}</span>
                                        @endif
                                        <span class="tile-price-badge">{{ $temple['currency'] ?? '' }} {{ number_format($pooja->pooja_fee, 2) }}</span>
                                    </span>
                                </span>
                            </button>
                            @endforeach
                        </div>
                        <table class="pos-category-table">
                            <tbody>
                                @foreach($categoryPoojas as $pooja)
                                <tr class="pos-table-row" data-type="pooja" data-is-custom="0" data-id="{{ $pooja->pooja_id }}" data-name="{{ $pooja->pooja_name }}" data-price="{{ $pooja->pooja_fee }}">
                                    <td class="pos-table-name">{{ $pooja->pooja_name }}</td>
                                    <td class="pos-table-desc">{{ $pooja->description }}</td>
                                    <td class="pos-table-price">{{ $temple['currency'] ?? '' }} {{ number_format($pooja->pooja_fee, 2) }}</td>
                                    <td class="pos-table-action"><button type="button" class="pos-table-book-btn">Book</button></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <p class="pos-items-empty text-muted text-center py-4 d-none" id="posItemsEmpty">Nothing matches your search.</p>
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

                <div class="pos-actions-main-row">
                    {{-- Which terminal a sale charges to is now set once from the EFT Terminal
                         Settings icon in the topbar (or the Pooja Portal's own "This Computer's
                         EFT Terminal" picker) — not re-picked per sale here. The PAY button's
                         own label already names the active terminal (see updatePosSaveBtnLabel()
                         in the script below), so the operator always sees it without a second
                         control to tap. --}}
                    @if($canSell)
                    <button type="button" class="pos-save-btn" id="posSaveBtn"><i class="bi bi-credit-card-2-front-fill me-2"></i>PAY {{ $temple['currency'] ?? '' }} 0.00</button>
                    @else
                    <button type="button" class="pos-save-btn" disabled title="View-only access"><i class="bi bi-eye-fill me-2"></i>View Only</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Till — the old Settle Till modal's figures, now their own full view instead of a
         popup (see the Till side-nav item's click handler below, which also re-fetches
         these figures every time the view is opened, exactly as the old modal did on
         show.bs.modal). --}}
    @if($canSettleCash)
    <div class="pos-till-pane" id="posTillPane">
        <div class="pos-view-header"><h2><i class="bi bi-cash-stack me-2"></i>Transactions</h2></div>
        <div id="posTillPaneBody">
            @include('admin.partials.cash-settlement-pane', ['context' => 'pos', 'scope' => 'tickets', 'eventId' => null, 'cashPreview' => $cashPreview, 'cashBankings' => $cashBankings, 'cashSettlements' => $cashSettlements, 'canViewCashHistory' => $canViewCashHistory, 'temple' => $temple])
        </div>
    </div>
    @endif

    {{-- EFT Terminal Settings — the same pairing/settings page, now its own full view
         (loaded in an iframe, exactly as the old popup did) instead of a popup over the
         rest of the page. Lazy-loaded on first visit (see setActiveView() below) rather
         than on every page load, and the whole page reloads on leaving this view if the
         iframe ever navigated away from blank — the simplest reliable way to reflect
         whatever changed (a new terminal, a fresh pairing) in this page's own terminal
         list/picker, which is otherwise baked into server-rendered HTML or a JS array
         from page load. --}}
    <div class="pos-eft-settings-pane" id="posEftSettingsPane">
        <div class="pos-view-header"><h2><i class="bi bi-credit-card-2-front-fill me-2"></i>Terminal Settings</h2></div>
        <div class="pos-iframe-wrap">
            <div class="pos-iframe-loading active" id="posEftSettingsLoading">
                <div class="pos-iframe-spinner"></div>
                <span>Loading terminal settings…</span>
            </div>
            <iframe id="posEftSettingsFrame" src="about:blank" title="EFT Terminal Settings"></iframe>
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
                    <button type="button" class="qty-modal-btn qty-modal-btn-minus" id="qtyModalMinus"><i class="bi bi-dash-lg"></i></button>
                    <span class="qty-modal-value" id="qtyModalValue">1</span>
                    <button type="button" class="qty-modal-btn qty-modal-btn-plus" id="qtyModalPlus"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div class="qty-modal-actions">
                    <button type="button" class="qty-modal-cancel" id="qtyModalCancel">Cancel</button>
                    <button type="button" class="qty-modal-confirm" id="qtyModalConfirm">Add to Order</button>
                </div>
            </div>
        </div>
    </div>


    <div class="eft-modal-overlay" id="eftModalOverlay">
        <div class="eft-modal" role="dialog" aria-modal="true" aria-labelledby="eftModalHeaderTitle">
            <div class="eft-modal-header" id="eftModalHeaderTitle">
                <i class="bi bi-credit-card-2-front-fill me-2 eft-modal-header-icon-default"></i>
                <i class="bi bi-credit-card-2-front-fill me-2 eft-modal-header-icon-error"></i>
                <span class="eft-modal-header-title-default">Card Payment</span>
                <span class="eft-modal-header-title-error" id="eftModalHeaderTitleError">Payment Declined</span>
            </div>
            <div class="eft-modal-body">
                <div class="eft-modal-amount" id="eftModalAmount">{{ $temple['currency'] ?? '' }} 0.00</div>
                <div class="eft-modal-status-box pending" id="eftModalStatusBox">
                    <div class="eft-modal-payment-anim">
                        <i class="bi bi-credit-card-2-front-fill"></i>
                        <div class="eft-modal-payment-anim-dots">
                            <span class="eft-modal-payment-anim-dot"></span>
                            <span class="eft-modal-payment-anim-dot"></span>
                            <span class="eft-modal-payment-anim-dot"></span>
                        </div>
                    </div>
                    <i class="bi bi-check-circle-fill eft-modal-status-icon icon-success"></i>
                    <i class="bi bi-x-circle-fill eft-modal-status-icon icon-error"></i>
                    <span class="eft-modal-status-divider"></span>
                    <div class="eft-modal-status-text">
                        <span class="eft-modal-status-line" id="eftModalStatusLine1">Starting…</span>
                        <span class="eft-modal-status-line" id="eftModalStatusLine2"></span>
                    </div>
                </div>
                <p class="eft-modal-decline-hint">Please check your card details and try again, or use a different payment method.</p>
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
                    <div id="eftModalOverrideQuestion">
                        <div class="eft-modal-override-icon"><i class="bi bi-exclamation-lg"></i></div>
                        <div class="eft-modal-override-title">Unknown transaction status</div>
                        <p class="eft-modal-override-subtitle">Was the transaction successful on the Eftpos terminal?</p>
                        <div class="eft-modal-override-actions">
                            <button type="button" class="eft-override-btn eft-override-yes" id="eftModalOverrideYes">Yes</button>
                            <button type="button" class="eft-override-btn eft-override-no" id="eftModalOverrideNo">No</button>
                        </div>
                    </div>
                    {{-- Replaces the question above the instant Yes/No is tapped — disabling the
                         buttons alone gave no visible sign the tap had registered. --}}
                    <p class="eft-modal-override-saving" id="eftModalOverrideSaving" hidden>Recording the transaction…</p>
                    {{-- Kept in the DOM (JS still references it) but not part of this simplified
                         prompt — a forced Yes/No decision, not an option to keep deferring it. --}}
                    <button type="button" class="eft-modal-cancel-btn" id="eftModalOverrideKeepWaiting" hidden>Keep Waiting</button>
                </div>
                <button type="button" class="eft-modal-cancel-btn" id="eftModalCancelBtn">Cancel Payment</button>
                {{-- Tiny caption, not a second line inside the status box above — a plain "still
                     watching" indicator, not something that reads as part of the terminal's own
                     message. --}}
                <p class="eft-modal-countdown" id="eftModalCountdown"></p>
            </div>
        </div>
    </div>

    {{-- "Book Pooja" (Pooja Bookings plan Phase E) — same form/fields as the Pooja
         Portal's Create Booking modal (Phase D), posting to the same admin.bookings.store
         route; submitted via AJAX here since that's this page's own convention
         (storeOrder etc.), rather than a plain form post. --}}
    @if($canCreateBookings)
    <div class="modal fade" id="bookPoojaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:20px; border:1px solid rgba(184,134,58,0.15);">
                <form id="bookPoojaForm">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus-fill me-2"></i>Book Pooja</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="bookPoojaError" class="alert alert-danger d-none" role="alert"></div>

                        {{-- The pooja itself is picked by tapping its tile in the main grid
                             (see openBookingPopup()) — this just confirms the choice and,
                             for Custom Pooja, collects the description and fee. --}}
                        <div class="mb-3 p-3" style="background:var(--shade); border-radius:10px;">
                            <div class="small text-muted fw-bold text-uppercase" style="letter-spacing:.04em; font-size:.7rem;">Booking</div>
                            <div class="fw-bold fs-5" id="bp_selected_name" style="color:var(--maroon);"></div>
                            <div class="small text-muted" id="bp_selected_fee"></div>
                        </div>
                        <input type="hidden" name="pooja_id" id="pp_pooja_id" required>
                        <input type="hidden" name="custom_pooja" id="pp_is_custom" value="0">
                        <div id="pp_custom_fields" class="d-none mb-3">
                            <label class="form-label fw-bold">Describe the Pooja</label>
                            <input type="text" name="custom_pooja_label" id="pp_custom_label" class="form-control mb-2" placeholder="Describe the pooja...">
                            <label class="form-label fw-bold">Fee ({{ $temple['currency'] }})</label>
                            <input type="number" name="custom_fee" id="pp_custom_fee" class="form-control" placeholder="0.00" step="0.01" min="0">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Booking Date</label>
                                <input type="date" name="booking_date" class="form-control" min="{{ date('Y-m-d') }}" max="{{ date('Y-m-d', strtotime('+10 days')) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Time Slot</label>
                                <select name="booking_time" class="form-select" required>
                                    <option value="" selected disabled>Select</option>
                                    <option value="06:00:00">06:00 AM</option>
                                    <option value="07:00:00">07:00 AM</option>
                                    <option value="08:00:00">08:00 AM</option>
                                    <option value="09:00:00">09:00 AM</option>
                                    <option value="10:00:00">10:00 AM</option>
                                    <option value="11:00:00">11:00 AM</option>
                                    <option value="12:00:00">12:00 PM</option>
                                    <option value="14:00:00">02:00 PM</option>
                                    <option value="15:00:00">03:00 PM</option>
                                    <option value="16:00:00">04:00 PM</option>
                                    <option value="17:00:00">05:00 PM</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Priest <span class="text-muted fw-normal">(optional)</span></label>
                            <select name="priest_option" id="posbp_priest_option" class="form-select">
                                <option value="auto" selected>Auto-assign (workload balanced)</option>
                                <option value="preferred">Choose a specific priest</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="posbp_preferred_priest_wrapper">
                            <label class="form-label fw-bold">Preferred Priest</label>
                            <select name="preferred_priest_id" class="form-select">
                                <option value="">Select priest</option>
                                @foreach($bookingPriests as $priest)
                                    <option value="{{ $priest->priest_id }}">{{ $priest->name }} ({{ $priest->specialization }})</option>
                                @endforeach
                            </select>
                        </div>
                        <hr>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Devotee Name</label>
                                <input type="text" name="devotee_name" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Mobile</label>
                                <input type="text" name="devotee_mobile" class="form-control" pattern="[0-9]{10}" placeholder="10 digits" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="devotee_email" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Participant Details <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea name="participant_details" class="form-control" rows="2" maxlength="2000" placeholder="Names, gothram, nakshatram for everyone this booking covers, and anything else the priest should know"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Payment Method</label>
                            <div class="pos-method-row" id="bpMethodRow"></div>
                            <input type="hidden" name="payment_method" id="bp_payment_method" value="Cash">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" id="bookPoojaSubmitBtn">Create Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    {{-- ?v= busts the browser's (and any CDN's) 7-day Cache-Control on this static file --
         otherwise a fix shipped here never reaches an already-open POS tab or a browser
         that cached the old copy days ago, since nothing about the <script> tag itself
         changes between deploys. --}}
    <script src="{{ asset('js/sci-action-framework.js') }}?v={{ @filemtime(public_path('js/sci-action-framework.js')) }}"></script>
    <script src="{{ asset('js/print-agent.js') }}?v={{ @filemtime(public_path('js/print-agent.js')) }}"></script>
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
        // Server-rendered once at page load — which terminal to use is resolved from this
        // (loadSelectedTerminalId()) and never re-fetched live on this page anymore; the
        // live pairing check now happens only on the actual EFT Terminal Settings screen.
        const EFT_TERMINALS = @json($eftTerminalsForJs);
        const CBA_SCI_CHARGE_START_URL = @json(route('admin.cba-sci.charge.start'));
        const CBA_SCI_CHARGE_STATUS_URL_BASE = @json(url('/admin/cba-sci/charge/status'));
        const CBA_SCI_CHARGE_ACTION_URL_BASE = @json(url('/admin/cba-sci/charge/action'));
        const CBA_SCI_CHARGE_CANCEL_URL_BASE = @json(url('/admin/cba-sci/charge/cancel'));
        const CBA_SCI_CHARGE_OVERRIDE_URL_BASE = @json(url('/admin/cba-sci/charge/override'));
        const THERMAL_PRINT_RECEIPT_URL = @json(route('admin.thermal-print.receipt'));
        const TICKET_AUTO_PRINT_URL_BASE = @json(url('/admin/tickets/auto-print'));

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
        let selectedTerminalId = loadSelectedTerminalId();
        function currentTerminalLabel() {
            const t = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
            return t ? t.label : 'No terminal';
        }
        // Which terminal is "current" is resolved once from loadSelectedTerminalId() (the
        // Pooja Portal's own "This Computer's EFT Terminal" setting, or the registry default)
        // and never re-picked from this page — this just keeps the Pay button's own label in
        // sync with that, called from updatePosTerminalStatus() whenever the method changes.
        function updateTerminalStatusName() {
            if (typeof updatePosSaveBtnLabel === 'function') { updatePosSaveBtnLabel(); }
        }
        const SCI_LOGO_URL = @json(asset('images/sci-logo.jpg'));

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

            // Scoped to ticket tiles only — pooja tiles share the .pos-item-tile base class
            // but have no .tile-qty-badge (they're not cart/quantity items), so including
            // them here throws on the missing badge and silently aborts the rest of
            // renderCart() (the cart pane never gets marked .has-items, the qty modal never
            // closes — exactly the "popup stays, cart doesn't show" symptom this fixes).
            document.querySelectorAll('.pos-item-tile[data-type="ticket"]').forEach(function (tile) {
                const id = tile.dataset.id;
                const qty = (cart[id] && cart[id].quantity) || 0;
                tile.classList.toggle('in-cart', qty > 0);
                tile.querySelector('.tile-qty-badge').textContent = qty;
            });

            // The cart panel itself only appears once there's something to check out —
            // an empty "Order" sidebar/sheet permanently on screen was dead chrome the
            // operator had to look past on every single sale.
            document.querySelector('.pos-cart-pane').classList.toggle('has-items', lines.length > 0);

            recalcCartTotal();
            updatePosSaveBtnLabel();
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
            const tile = e.target.closest('.pos-item-tile, .pos-table-row');
            if (!tile) { return; }
            if (tile.dataset.type === 'pooja') {
                if (typeof openBookingPopup === 'function') { openBookingPopup(tile); }
            } else {
                openQtyModal(tile);
            }
        });

        // Side nav (Archana / Poojas / Till) + per-menu category tabs + search — plain
        // client-side show/hide, same "no server round-trip for something this cheap"
        // approach as the cart/quantity logic already on this page. Archana and Poojas
        // never share a grid or a category-tab bar; switching the view swaps both the
        // visible panel and which tab bar applies. Within a panel, each category is its
        // own labeled section — a whole section (not just its tiles) hides once nothing
        // in it matches the active category/search, which is what keeps "All" from
        // reading as a messy, undifferentiated wall of tiles. The Custom Pooja tile's
        // own section (data-category="") is exempt from category filtering — it's pinned,
        // visible under every pooja category tab — but still respects search.
        (function () {
            const navItems = document.querySelectorAll('.pos-nav-item[data-view]');
            const searchInput = document.getElementById('posItemsSearch');
            const emptyMsg = document.getElementById('posItemsEmpty');
            const panels = document.querySelectorAll('#itemsGrid .pos-menu-panel');
            const tabBars = document.querySelectorAll('.pos-category-tabs');
            const bodyPane = document.querySelector('.pos-body');
            const itemsPane = document.querySelector('.pos-items-pane');
            const cartPane = document.querySelector('.pos-cart-pane');
            const tillPane = document.getElementById('posTillPane');
            const headerTitle = document.getElementById('posViewHeaderTitle');
            const poojaViewToggle = document.getElementById('posPoojaViewToggle');
            const eftSettingsPane = document.getElementById('posEftSettingsPane');
            const eftSettingsFrame = document.getElementById('posEftSettingsFrame');
            const eftSettingsLoading = document.getElementById('posEftSettingsLoading');
            const EFT_SETTINGS_URL = @json(route('admin.eft-terminals.index') . '?embedded=1');
            const VIEW_TITLES = {
                archana: '<i class="bi bi-flower1 me-2"></i>Archana Tickets',
                poojas: '<i class="bi bi-journal-bookmark-fill me-2"></i>Pooja Bookings',
            };
            // A full page reload once the operator leaves this view is the simplest reliable
            // way to reflect whatever changed in the embedded settings page (a new terminal,
            // a fresh pairing) in this page's own terminal list/picker — same reasoning the
            // old popup version used on close, just triggered by switching away instead.
            let eftSettingsChanged = false;
            let currentView = 'archana';
            if (eftSettingsFrame) {
                eftSettingsFrame.addEventListener('load', function () {
                    // Blank's own initial load fires this too — only the real settings page
                    // loading counts as "done" (and as a change worth reloading for on exit).
                    if (eftSettingsFrame.src !== 'about:blank') {
                        eftSettingsChanged = true;
                        eftSettingsLoading?.classList.remove('active');
                    }
                });
            }
            let activeMenu = 'archana';
            let activeCategory = '';

            function applyFilters() {
                const q = searchInput.value.trim().toLowerCase();
                const panel = document.querySelector('.pos-menu-panel[data-menu="' + activeMenu + '"]');
                let anyVisible = false;
                if (panel) {
                    panel.querySelectorAll('.pos-category-section').forEach(function (section) {
                        const isPinned = section.dataset.category === '';
                        const matchesCategory = isPinned || !activeCategory || section.dataset.category === activeCategory;
                        let sectionHasVisible = false;
                        // Grid tiles and table rows carry the same data-name/-category — one
                        // predicate drives both, since only one of the two is ever displayed
                        // at a time (see the view-toggle below) but both stay in sync.
                        section.querySelectorAll('.pos-item-tile, .pos-table-row').forEach(function (tile) {
                            const matchesSearch = !q || tile.dataset.name.toLowerCase().includes(q);
                            const visible = matchesCategory && matchesSearch;
                            tile.classList.toggle('pos-category-hidden', !visible);
                            if (visible) { sectionHasVisible = true; }
                        });
                        section.classList.toggle('pos-section-hidden', !sectionHasVisible);
                        if (sectionHasVisible) { anyVisible = true; }
                    });
                }
                emptyMsg.classList.toggle('d-none', anyVisible);
            }

            function setActiveView(view) {
                // Leaving the EFT settings view — reload the whole page rather than try to
                // patch this page's own terminal list/picker piecemeal (it's otherwise baked
                // into server-rendered HTML or a JS array from page load).
                if (currentView === 'eft-settings' && view !== 'eft-settings' && eftSettingsChanged) {
                    window.location.reload();
                    return;
                }

                // Switching to Poojas clears and closes any in-progress ticket order — the
                // two are different transaction types entirely (a till sale vs. a booking),
                // so carrying a half-built ticket cart across into booking mode would just
                // be confusing state sitting behind the scenes.
                if (view === 'poojas' && typeof resetCart === 'function' && Object.keys(cart).length) {
                    resetCart();
                }
                cartPane?.classList.remove('expanded');

                navItems.forEach(function (btn) {
                    btn.classList.toggle('active', btn.dataset.view === view);
                });
                currentView = view;
                tillPane?.classList.remove('active');
                eftSettingsPane?.classList.remove('active');

                if (view === 'till') {
                    // Hiding .pos-body itself (not just its two children) matters here — it's
                    // a flex:1 sibling of .pos-till-pane/.pos-eft-settings-pane in the same
                    // column; left visible-but-empty, it would still claim half the available
                    // height and squeeze whichever pane is actually active into the other half.
                    bodyPane?.classList.add('d-none');
                    tillPane?.classList.add('active');
                    if (typeof window.refreshPosTillPane === 'function') { window.refreshPosTillPane(); }
                    return;
                }
                if (view === 'eft-settings') {
                    bodyPane?.classList.add('d-none');
                    eftSettingsPane?.classList.add('active');
                    if (eftSettingsFrame && eftSettingsFrame.src === 'about:blank') {
                        eftSettingsFrame.src = EFT_SETTINGS_URL;
                    }
                    return;
                }
                bodyPane?.classList.remove('d-none');
                itemsPane?.classList.remove('d-none');
                cartPane?.classList.remove('d-none');

                activeMenu = view;
                activeCategory = '';
                if (headerTitle && VIEW_TITLES[view]) { headerTitle.innerHTML = VIEW_TITLES[view]; }
                poojaViewToggle?.classList.toggle('d-none', view !== 'poojas');
                panels.forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.dataset.menu !== view);
                });
                tabBars.forEach(function (bar) {
                    const isActiveBar = bar.dataset.menu === view;
                    bar.classList.toggle('d-none', !isActiveBar);
                    if (isActiveBar) {
                        // Default to "Recent" when it exists (see the recently-booked
                        // section below) rather than "All", so the counter's most commonly
                        // re-booked poojas are the very first thing they see.
                        const recentTab = bar.querySelector('.pos-category-tab[data-category="__recent__"]');
                        bar.querySelectorAll('.pos-category-tab').forEach(function (t) {
                            t.classList.toggle('active', recentTab ? t === recentTab : t === bar.querySelector('.pos-category-tab'));
                        });
                        activeCategory = recentTab ? '__recent__' : '';
                    }
                });
                searchInput.value = '';
                applyFilters();
            }

            navItems.forEach(function (btn) {
                btn.addEventListener('click', function () { setActiveView(btn.dataset.view); });
            });

            tabBars.forEach(function (bar) {
                bar.addEventListener('click', function (e) {
                    const tab = e.target.closest('.pos-category-tab');
                    if (!tab || bar.dataset.menu !== activeMenu) { return; }
                    bar.querySelectorAll('.pos-category-tab').forEach(function (t) { t.classList.remove('active'); });
                    tab.classList.add('active');
                    activeCategory = tab.dataset.category || '';
                    applyFilters();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }

            applyFilters();
        })();

        // Grid/table view toggle — Poojas only. Both layouts share the same underlying
        // data-* attributes and filtering (see applyFilters() above), so this only ever
        // needs to flip a class on the Poojas panel itself.
        (function () {
            const poojaPanel = document.querySelector('.pos-menu-panel[data-menu="poojas"]');
            const toggle = document.querySelector('.pos-view-toggle');
            if (!poojaPanel || !toggle) { return; }
            toggle.addEventListener('click', function (e) {
                const btn = e.target.closest('.pos-view-toggle-btn');
                if (!btn) { return; }
                toggle.querySelectorAll('.pos-view-toggle-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                poojaPanel.classList.toggle('table-view', btn.dataset.view === 'table');
            });
        })();

        // Side nav expand/collapse — an explicit tap, not hover, so it works reliably on a
        // touchscreen till. The rail overlays rather than pushes content when expanded (see
        // .pos-side-nav.expanded in the CSS), so nothing else needs to react to this.
        (function () {
            const nav = document.getElementById('posSideNav');
            const toggleBtn = document.getElementById('posSideNavToggle');
            if (!nav || !toggleBtn) { return; }
            toggleBtn.addEventListener('click', function () { nav.classList.toggle('expanded'); });
        })();

        // User account flyout (Manage PIN / Logout) + a confirmation before logout actually
        // navigates away — the plain link used to log out with no warning at all.
        (function () {
            const userBtn = document.getElementById('posNavUserBtn');
            const flyout = document.getElementById('posUserFlyout');
            const logoutLink = document.getElementById('posLogoutLink');
            if (userBtn && flyout) {
                userBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    flyout.classList.toggle('open');
                });
                document.addEventListener('click', function (e) {
                    if (flyout.classList.contains('open') && !e.target.closest('.pos-nav-user-wrap')) {
                        flyout.classList.remove('open');
                    }
                });
            }
            if (logoutLink) {
                logoutLink.addEventListener('click', function (e) {
                    if (!confirm('Log out of the POS?')) { e.preventDefault(); }
                });
            }
        })();
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
        // Display terms only — the underlying method value ('EFT Terminal', 'Bank
        // Transfer') is unchanged everywhere else on this page and in the backend
        // (payment_method columns, every selectedMethod === '...' check), since renaming
        // those would mean a wider schema/reporting change than what was asked for.
        const methodLabels = { Cash: 'Cash', UPI: 'UPI', 'Bank Transfer': 'Bank', 'EFT Terminal': 'Card' };

        // The Pay button's own label/icon track the live cart total and selected method,
        // same pattern as event-pos-donation's PAY {amount} — called from recalcCartTotal()
        // (quantity changes) and updatePosTerminalStatus() (method/terminal changes), so it
        // never goes stale regardless of which changed first.
        function updatePosSaveBtnLabel() {
            const saveBtn = document.getElementById('posSaveBtn');
            if (!saveBtn) { return; }
            let amt = 0;
            Object.values(cart).forEach(function (line) { amt += line.price * line.quantity; });
            const amtText = CURRENCY_CODE + ' ' + amt.toFixed(2);
            if (selectedMethod === 'EFT Terminal') {
                saveBtn.innerHTML = '<i class="bi bi-credit-card-2-front-fill me-2"></i>PAY ' + amtText + '<span class="pos-save-btn-terminal">on ' + currentTerminalLabel() + ' Terminal</span>';
            } else {
                saveBtn.innerHTML = '<i class="bi bi-credit-card-2-front-fill me-2"></i>PAY ' + amtText;
            }
        }
        const PAYMENT_METHODS = @json($paymentMethods);
        const posMethodList = PAYMENT_METHODS.length ? PAYMENT_METHODS : ['Cash'];
        // EFT Terminal is the fastest, most reconciliation-friendly method when it's on offer
        // at all — default to it rather than whichever method happens to sort first, so a
        // clerk doesn't have to remember to switch off Cash every single sale. But it's only
        // genuinely "on offer" if some terminal is actually paired right now — EFT_TERMINALS is
        // paired-only on first load (see TicketController::pos()) and re-filtered the same way
        // after every live picker refresh (see renderTerminalModalList()), so this one check
        // covers both.
        function eftTerminalAvailable() { return EFT_TERMINALS.some(function (t) { return t.paired; }); }
        const posDefaultMethod = (posMethodList.includes('EFT Terminal') && eftTerminalAvailable())
            ? 'EFT Terminal'
            : posMethodList.find(function (m) { return m !== 'EFT Terminal' || eftTerminalAvailable(); }) || posMethodList[0];
        posMethodList.forEach(function (m) {
            const btn = document.createElement('button');
            btn.type = 'button';
            const disabled = m === 'EFT Terminal' && !eftTerminalAvailable();
            btn.className = 'pos-method-btn' + (m === posDefaultMethod ? ' active' : '') + (disabled ? ' disabled' : '');
            btn.innerHTML = '<i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i>' + (methodLabels[m] || m);
            btn.dataset.method = m;
            if (disabled) { btn.title = 'No EFT terminal is currently paired.'; }
            btn.addEventListener('click', function () {
                if (btn.classList.contains('disabled')) { return; }
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
                updatePosTerminalStatus();
            });
            methodRow.appendChild(btn);
            if (m === posDefaultMethod) { selectedMethod = m; }
        });

        // Keeps the Pay button's own label in sync with the selected method/terminal —
        // called on every method switch (and once here for the initial default method).
        function updatePosTerminalStatus() {
            updateTerminalStatusName();
        }
        updatePosTerminalStatus();

        // Always-visible entry point to terminal pairing/settings — unlike "Switch" above the
        // Pay button, which only ever appears while "EFT Terminal" is selected (and that method
        // stays disabled with nothing paired to pick), this one works with zero terminals paired.
        const posEftSettingsTopbarBtn = document.getElementById('posEftSettingsTopbarBtn');
        function updateEftSettingsTopbarBtn() {
            if (!posEftSettingsTopbarBtn) { return; }
            posEftSettingsTopbarBtn.classList.toggle('pos-nav-item-setup', !eftTerminalAvailable());
        }
        updateEftSettingsTopbarBtn();
        // Opening/closing the settings view itself is handled generically by setActiveView()
        // below (this button carries data-view="eft-settings" like the other nav items) —
        // nothing extra to wire up here beyond the dot indicator above.

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
        // What the current/last EFT Terminal charge is actually for — a ticket order (the
        // default) or a pooja booking — so pollEftTransaction's single shared success
        // handler knows whether to open a print view + clear the cart, or close the booking
        // popup. Set right before starting each charge (ticket PAY button / startBookingEftPurchase).
        let eftPurchaseKind = 'ticket_order';
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
            thermalPrintUrl: THERMAL_PRINT_RECEIPT_URL,
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
                overrideQuestion: document.getElementById('eftModalOverrideQuestion'),
                overrideSaving: document.getElementById('eftModalOverrideSaving'),
                countdown: document.getElementById('eftModalCountdown'),
                headerTitleError: document.getElementById('eftModalHeaderTitleError'),
            },
            buildStartBody: function (attempt) {
                return { record_type: 'ticket_order', cart_json: JSON.stringify(attempt.cart || []) };
            },
            onToast: function (message, isError) { showToast(message, isError); },
            onApproved: function (donationId) {
                activeEftProvider = null;
                showToast('Sale recorded — printing…');
                openPrintView(donationId);
                resetCart();
            },
            // No extra warning popup here — the modal's own status box already shows this exact
            // message (setStatus() sets it right before calling onDeclined/onUnresolved), so a
            // second popup on top of it was pure duplication, not new information. The cart
            // still resets here exactly as it does on approval — by the time either of these
            // fires the transaction is genuinely finished (unlike start()'s own "never even
            // reached the terminal" failure path, which deliberately leaves the cart alone so
            // the operator can just retry the same sale), and the modal stays on screen with
            // its own Done/Retry buttons until the operator is ready to move on anyway, so
            // clearing the cart underneath it now is invisible until that moment.
            onDeclined: function () {
                activeEftProvider = null;
                resetCart();
            },
            onUnresolved: function () {
                activeEftProvider = null;
                resetCart();
            },
            onLocalCancel: function () {
                activeEftProvider = null;
                showToast('Sale cancelled.', true);
            },
        });

        // Three-tier fallback, same reasoning as sci-action-framework.js's autoPrintReceipt():
        //  1. The backend tries a direct server-side print (only reachable when the backend
        //     itself is on the same network as the printer — e.g. local testing).
        //  2. Failing that, it hands back the raw stub data, which this computer's local
        //     Print Agent (public/js/print-agent.js) prints instead — the path that actually
        //     works once deployed, since the printer is on the temple's own LAN, not reachable
        //     from the backend's remote hosting.
        //  3. Failing THAT too (agent not configured/not running), fall back to the old
        //     browser-print tab — never leave an order with nothing printed at all.
        function openPrintView(orderId) {
            if (!orderId) { return; }
            function openBrowserView() { window.open('/admin/tickets/print/' + orderId, '_blank'); }
            fetch(TICKET_AUTO_PRINT_URL_BASE + '/' + encodeURIComponent(orderId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) { return; }
                    if (data.stubs && data.stubs.length && window.PrintAgent && PrintAgent.isConfigured()) {
                        Promise.all(data.stubs.map(function (stub) { return PrintAgent.printTicketStub(stub); }))
                            .then(function (results) { if (results.some(function (r) { return !r.success; })) { openBrowserView(); } });
                        return;
                    }
                    openBrowserView();
                })
                .catch(openBrowserView);
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
                eftPurchaseKind = 'ticket_order';

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
                        if (eftPurchaseKind === 'pooja_booking') {
                            setEftModalStatus(['PAYMENT APPROVED', data.auth_code ? 'Auth ' + data.auth_code : 'Booking confirmed…'], 'success');
                            setTimeout(function () {
                                hideEftModal();
                                if (typeof window.finishBookingPopup === 'function') {
                                    window.finishBookingPopup('Booking #' + data.donation_id + ' created, confirmed and paid on the terminal.');
                                }
                                eftPurchaseKind = 'ticket_order';
                            }, 1200);
                            return;
                        }
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
                        if (eftPurchaseKind === 'pooja_booking' && btn.dataset.originalText) { btn.innerHTML = btn.dataset.originalText; }
                        showToast(data.message || 'No final result was received — check before retrying.', true);
                    } else {
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT DECLINED', data.message || ''], 'error');
                        setTimeout(hideEftModal, 1800);
                        btn.disabled = false;
                        if (eftPurchaseKind === 'pooja_booking' && btn.dataset.originalText) { btn.innerHTML = btn.dataset.originalText; }
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

    @if($canSettleCash)
    @php
        // Built as a plain variable — see event-pos-donation.blade.php's identical comment
        // on why an inline array literal inside @json()'s own argument isn't safe here.
        $settleTillPaneUrl = route('admin.cash-settlement.pane', ['scope' => 'tickets', 'context' => 'pos']);
    @endphp
    <script>
        // See event-pos-donation.blade.php's identical script for why — the pane's own
        // markup is stale page-load-time HTML otherwise, missing any Cash sale recorded
        // since (from another station, or the public donation page). Previously refetched
        // on the old Settle Till modal's own show.bs.modal event; the Till nav item's click
        // handler (setActiveView()) now calls this directly every time that view opens.
        window.refreshPosTillPane = function () {
            const bodyEl = document.getElementById('posTillPaneBody');
            if (!bodyEl) { return; }
            const paneUrl = @json($settleTillPaneUrl);

            bodyEl.style.opacity = '0.5';
            fetch(paneUrl, { headers: { 'Accept': 'text/html' } })
                .then(function (res) { return res.ok ? res.text() : Promise.reject(); })
                .then(function (html) {
                    bodyEl.innerHTML = html;
                    bodyEl.style.opacity = '1';
                    bodyEl.querySelectorAll('script').forEach(function (oldScript) {
                        const newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(function (attr) { newScript.setAttribute(attr.name, attr.value); });
                        newScript.textContent = oldScript.textContent;
                        oldScript.replaceWith(newScript);
                    });
                })
                .catch(function () {
                    bodyEl.style.opacity = '1';
                    showToast('Could not refresh — showing the last loaded figures.', true);
                });
        };
    </script>
    @endif

    {{-- This page is a classic form POST + redirect for Settle Till (unlike the AJAX-driven
         ticket sale/EFT flows above), so its result only ever reaches the browser as a
         session flash — reused through the same corner toast() every other confirmation on
         this page already uses, rather than a different-looking banner just for this form. --}}
    @if(session('success') || session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            showToast(@json(session('success', session('error'))), {{ session('error') ? 'true' : 'false' }});
        });
    </script>
    @endif

    @if($canCreateBookings)
    <script>
        (function () {
            const form = document.getElementById('bookPoojaForm');
            if (!form) { return; }
            const errorBox = document.getElementById('bookPoojaError');
            const submitBtn = document.getElementById('bookPoojaSubmitBtn');
            const priestOption = document.getElementById('posbp_priest_option');
            const preferredWrapper = document.getElementById('posbp_preferred_priest_wrapper');
            const modalEl = document.getElementById('bookPoojaModal');
            const STORE_BOOKING_URL = @json(route('admin.bookings.store'));

            priestOption.addEventListener('change', function () {
                preferredWrapper.classList.toggle('d-none', this.value !== 'preferred');
            });

            // The pooja itself is picked by tapping its tile in the main item grid (see
            // #itemsGrid's click handler, which calls window.openBookingPopup for any
            // data-type="pooja" tile) — this just confirms the choice and, for Custom
            // Pooja, collects the description/fee. See BookingService::create()'s
            // custom_pooja_label/custom_fee support.
            const poojaIdField = document.getElementById('pp_pooja_id');
            const isCustomField = document.getElementById('pp_is_custom');
            const customFields = document.getElementById('pp_custom_fields');
            const customLabel = document.getElementById('pp_custom_label');
            const customFee = document.getElementById('pp_custom_fee');
            const selectedName = document.getElementById('bp_selected_name');
            const selectedFee = document.getElementById('bp_selected_fee');
            let selectedTileFee = 0;
            let selectedTileName = '';

            window.openBookingPopup = function (tile) {
                // No CAN_SELL gate here deliberately — that flag is about selling tickets,
                // a separate permission from 'bookings'->'add', which is what gated this
                // tile even existing in the DOM (see the canCreateBookings check around it).
                const isCustom = tile.dataset.isCustom === '1';

                if (isCustom) {
                    isCustomField.value = '1';
                    poojaIdField.value = '';
                    poojaIdField.required = false;
                    customFields.classList.remove('d-none');
                    customLabel.required = true;
                    customFee.required = true;
                    customLabel.value = '';
                    customFee.value = '';
                    selectedName.textContent = 'Custom Pooja';
                    selectedFee.textContent = 'Describe it and set a fee below';
                    selectedTileFee = 0;
                    selectedTileName = 'Custom Pooja';
                } else {
                    isCustomField.value = '0';
                    poojaIdField.value = tile.dataset.id;
                    poojaIdField.required = true;
                    customFields.classList.add('d-none');
                    customLabel.required = false;
                    customFee.required = false;
                    selectedName.textContent = tile.dataset.name;
                    selectedTileFee = parseFloat(tile.dataset.price) || 0;
                    selectedTileName = tile.dataset.name;
                    selectedFee.textContent = CURRENCY_CODE + ' ' + selectedTileFee.toFixed(2);
                }

                errorBox.classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };

            function resetPoojaPicker() {
                poojaIdField.value = '';
                poojaIdField.required = true;
                isCustomField.value = '0';
                customFields.classList.add('d-none');
                customLabel.required = false;
                customFee.required = false;
                selectedName.textContent = '';
                selectedFee.textContent = '';
                selectedTileFee = 0;
                selectedTileName = '';
            }

            // Payment method row — Cash / Bank / Card (Card = EFT Terminal under the hood;
            // "pos-method-row-equal" forces exactly 3 equal-width buttons in one row
            // instead of the ticket cart's own 2-per-row wrap, since this row always has
            // exactly these three). "Office Counter" isn't offered here — just the three
            // real-world ways a booking actually gets paid at this counter.
            const bpMethodRow = document.getElementById('bpMethodRow');
            bpMethodRow.classList.add('pos-method-row-equal');
            const bpPaymentMethodField = document.getElementById('bp_payment_method');
            const bpMethods = [
                { value: 'Cash', label: 'Cash', icon: 'bi-cash-coin' },
                { value: 'Bank', label: 'Bank', icon: 'bi-bank2' },
                { value: 'EFT Terminal', label: 'Card', icon: 'bi-credit-card-2-front-fill' },
            ];
            // The submit button's own label follows the selected method — "Pay on
            // Terminal" for Card, "Create Booking" otherwise — set here (not just once at
            // load) so it's never stale, and read back out by startBookingEftPurchase()
            // via btn.dataset.originalText for pollEftTransaction's own restore-on-
            // decline logic (that function lives outside this IIFE's scope).
            let submitBtnOriginalText = submitBtn.innerHTML;
            function setMethodAndButtonLabel(value) {
                bpPaymentMethodField.value = value;
                submitBtnOriginalText = value === 'EFT Terminal' ? 'Pay on Terminal' : 'Create Booking';
                submitBtn.innerHTML = submitBtnOriginalText;
            }
            bpMethods.forEach(function (m) {
                const btn = document.createElement('button');
                btn.type = 'button';
                const disabled = m.value === 'EFT Terminal' && !eftTerminalAvailable();
                btn.className = 'pos-method-btn' + (m.value === 'Cash' ? ' active' : '') + (disabled ? ' disabled' : '');
                btn.innerHTML = '<i class="bi ' + m.icon + '"></i>' + m.label;
                btn.dataset.method = m.value;
                if (disabled) { btn.title = 'No EFT terminal is currently paired.'; }
                btn.addEventListener('click', function () {
                    if (btn.classList.contains('disabled')) { return; }
                    bpMethodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    setMethodAndButtonLabel(m.value);
                });
                bpMethodRow.appendChild(btn);
            });

            // Called once the booking popup's own form-reset path runs (Cash/Bank success,
            // or an EFT Terminal booking's approval — see pollEftTransaction's
            // 'pooja_booking' branch below) — the one place that closes the modal and clears
            // its state, so both payment paths end up in the same place.
            window.finishBookingPopup = function (message) {
                showToast(message || 'Booking created.', false);
                bootstrap.Modal.getInstance(modalEl)?.hide();
                form.reset();
                preferredWrapper.classList.add('d-none');
                resetPoojaPicker();
                bpMethodRow.querySelectorAll('.pos-method-btn').forEach(function (b, i) { b.classList.toggle('active', i === 0); });
                setMethodAndButtonLabel('Cash');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnOriginalText;
            };

            const submitBtnOriginalText = submitBtn.innerHTML;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                errorBox.classList.add('d-none');

                if (!poojaIdField.value && isCustomField.value !== '1') {
                    errorBox.textContent = 'Please select a pooja, or choose Custom Pooja.';
                    errorBox.classList.remove('d-none');
                    return;
                }
                if (isCustomField.value === '1' && (!customLabel.value.trim() || customFee.value === '' || Number(customFee.value) < 0)) {
                    errorBox.textContent = 'Please describe the custom pooja and enter a fee.';
                    errorBox.classList.remove('d-none');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating…';

                if (bpPaymentMethodField.value === 'EFT Terminal') {
                    startBookingEftPurchase(submitBtn);
                    return;
                }

                fetch(STORE_BOOKING_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: new FormData(form),
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.finishBookingPopup(data.message);
                        } else {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = submitBtnOriginalText;
                            errorBox.textContent = data.message || 'Could not create the booking.';
                            errorBox.classList.remove('d-none');
                        }
                    })
                    .catch(function () {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtnOriginalText;
                        errorBox.textContent = 'Could not reach the server — please try again.';
                        errorBox.classList.remove('d-none');
                    });
            });

            // ---------- Pay the booking on the EFT terminal ----------
            // Reuses the exact same charge-start/poll/modal machinery the ticket cart's own
            // EFT Terminal option already drives (startEftCharge, pollEftTransaction, the
            // #eftModalOverlay) — see pollEftTransaction's eftPurchaseKind branch for how the
            // shared success handler tells a booking apart from a ticket order. Linkly only
            // for now (same as the ticket cart's own CBA SCI branch, not yet mirrored here).
            function startBookingEftPurchase(btn) {
                // pollEftTransaction lives outside this IIFE's scope, so it can't see
                // submitBtnOriginalText directly — stash it on the button itself instead.
                btn.dataset.originalText = submitBtnOriginalText;
                if (!eftTerminalAvailable()) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnOriginalText;
                    errorBox.textContent = 'No EFT terminal is currently paired.';
                    errorBox.classList.remove('d-none');
                    return;
                }
                const selectedTerminal = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                if (selectedTerminal && selectedTerminal.provider === 'cba_sci') {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnOriginalText;
                    errorBox.textContent = 'This terminal (CBA Smart Terminal) isn\'t yet supported for pooja bookings — please use Cash or Bank Transfer, or switch to a Linkly terminal.';
                    errorBox.classList.remove('d-none');
                    return;
                }

                const amount = isCustomField.value === '1' ? (parseFloat(customFee.value) || 0) : selectedTileFee;
                if (amount <= 0) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnOriginalText;
                    errorBox.textContent = 'Enter a fee greater than zero before charging the terminal.';
                    errorBox.classList.remove('d-none');
                    return;
                }

                eftPurchaseKind = 'pooja_booking';
                activeEftProvider = 'linkly';
                eftPollCancelled = false;
                eftCurrentSessionId = null;
                eftConsecutiveTransientErrors = 0;
                showEftModal(amount);

                const startBody = new URLSearchParams();
                startBody.set('record_type', 'pooja_booking');
                startBody.set('amount', amount.toFixed(2));
                startBody.set('client_ref', newClientRef());
                startBody.set('pooja_id', poojaIdField.value || '');
                startBody.set('custom_pooja', isCustomField.value);
                startBody.set('custom_pooja_label', customLabel.value || '');
                startBody.set('custom_fee', customFee.value || '');
                startBody.set('booking_date', form.querySelector('[name="booking_date"]').value);
                startBody.set('booking_time', form.querySelector('[name="booking_time"]').value);
                startBody.set('priest_option', priestOption.value);
                startBody.set('preferred_priest_id', form.querySelector('[name="preferred_priest_id"]')?.value || '');
                startBody.set('devotee_name', form.querySelector('[name="devotee_name"]').value);
                startBody.set('devotee_mobile', form.querySelector('[name="devotee_mobile"]').value);
                startBody.set('devotee_email', form.querySelector('[name="devotee_email"]').value);
                startBody.set('participant_details', form.querySelector('[name="participant_details"]')?.value || '');
                if (selectedTerminalId) { startBody.set('terminal_id', selectedTerminalId); }

                fetch(EFT_CHARGE_START_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: startBody.toString(),
                })
                    .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                    .then(function (result) {
                        if (!(result.status >= 200 && result.status < 300 && result.data.success)) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = submitBtnOriginalText;
                            hideEftModal();
                            errorBox.textContent = result.data.message || 'Could not start the terminal transaction.';
                            errorBox.classList.remove('d-none');
                            return;
                        }
                        eftCurrentSessionId = result.data.session_id;
                        pollEftTransaction(result.data.session_id, btn, amount, Date.now());
                    })
                    .catch(function () {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtnOriginalText;
                        hideEftModal();
                        errorBox.textContent = 'Could not reach the EFT terminal — please try again.';
                        errorBox.classList.remove('d-none');
                    });
            }
        })();
    </script>
    @endif
</body>
</html>
