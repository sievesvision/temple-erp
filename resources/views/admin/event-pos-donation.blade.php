<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>POS · {{ $event->event_name }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A;
            --maroon-dark: #741521;
            --gold: #C9952E;
            --gold-hover: #D3A333;
            --cream: #FFF9EE;
            --white: #FFFFFF;
            --border: #E6E9ED;
            --shade: #F1F4F7;
            --shade-border: #E3E8ED;
            --text-primary: #102A43;
            --text-secondary: #52667A;
            --success: #10B981;
            --error: #EF4444;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        /* One single, native scroll region (the document itself) rather than an inner
           overflow-y:auto container — that nested-scroll trick depends on a perfect height
           chain (html/body/flex-child all reporting real heights) that iOS Safari does not
           reliably honour once its own chrome (address bar collapse, safe-area insets) gets
           involved, which is what silently clipped content like the Details card on iPad.
           body is still a flex column so the footer is pushed to the true bottom of the
           screen when the page's own content is shorter than the viewport (instead of
           floating right under the cards with a gap of empty page below it) — min-height,
           not a fixed height, is what lets the page grow taller and scroll normally once
           the content needs more room than that. */
        html, body { overflow-x: hidden; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); }
        h1, h2 { font-family: var(--serif); }
        button, input, select, textarea { font-family: inherit; }

        /* ---------- Minimal topbar — no dashboard chrome, just identity + exits ---------- */
        /* Three zones: identity on the left, temple brand centred, every action button
           grouped on the right — the left and right zones share equal flex so the centred
           brand actually sits in the visual middle of the bar rather than just wherever
           space happens to be left over. */
        .pos-topbar {
            background: #6B0F1A; position: sticky; top: 0; flex-shrink: 0;
            color: white; padding: 14px 24px; display: flex; align-items: center; gap: 14px;
            box-shadow: 0 2px 10px rgba(15,23,42,0.18); z-index: 20; min-height: 76px;
        }
        /* Matches the Event Console's own topbar pattern: temple logo+name fixed on the
           left, the event title centred (with a flourish line either side) and taking all
           the leftover space, action buttons fixed on the right. */
        .pos-topbar-brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .pos-topbar-logo { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #fff; padding: 2px; flex-shrink: 0; }
        .pos-topbar-temple-name { font-weight: 800; font-size: 1rem; line-height: 1.2; font-family: var(--serif); color: #fff; }
        .pos-topbar-temple-sub { font-size: 0.72rem; color: rgba(255,255,255,0.6); line-height: 1.2; }

        .pos-topbar-event-title { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 14px; text-align: center; overflow: hidden; }
        .pos-flourish-line { flex: 1; max-width: 90px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold), transparent); display: none; flex-shrink: 0; }
        @media (min-width: 900px) { .pos-flourish-line { display: block; } }
        .pos-topbar-event-title-text { min-width: 0; max-width: 100%; }
        .pos-topbar-event-title-text h1 { font-family: var(--serif); font-size: clamp(1.15rem, 2.6vw, 1.6rem); font-weight: 800; color: var(--gold); margin: 0; letter-spacing: 0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-event-motto { font-size: 0.74rem; color: rgba(255,255,255,0.75); letter-spacing: 0.03em; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 900px) { .pos-event-motto { display: none; } }

        .pos-topbar-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .pos-topbar-btn { background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.35); color: white; width: 46px; height: 46px; border-radius: 12px; font-size: 1.1rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .pos-topbar-btn:hover { background: rgba(255,255,255,0.18); }
        .pos-terminal-btn { background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.35); color: white; height: 46px; padding: 0 16px; border-radius: 12px; font-size: 0.88rem; font-weight: 700; flex-shrink: 0; display: flex; align-items: center; gap: 8px; max-width: 180px; }
        .pos-terminal-btn:hover { background: rgba(255,255,255,0.18); }
        .pos-terminal-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        @media (max-width: 700px) { .pos-topbar-temple-sub { display: none; } }

        /* ---------- Main entry area ---------- */
        /* Full-width POS workspace, not a narrow centred web form — the container just gets
           a comfortable max-width so it doesn't stretch absurdly on a huge monitor, but on
           every tablet/laptop size it fills essentially the whole browser width. */
        .pos-main { padding: 20px 24px 8px; flex: 1 0 auto; }

        /* Two-column layout, primary target = landscape tablet/desktop — left ~64% for the
           entry fields, right ~36% for the running total/payment/actions, always visible
           without scrolling. Collapses to one column (right panel falls below) on portrait
           tablets and phones. */
        .pos-grid { max-width: 1600px; width: 100%; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1fr); gap: 20px; align-items: start; }
        @media (min-width: 900px) { .pos-grid { grid-template-columns: minmax(0, 1.8fr) minmax(340px, 1fr); } }

        .pos-col-left { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
        .pos-card {
            background: var(--white); border-radius: 14px; border: 1px solid var(--border);
            box-shadow: 0 2px 10px rgba(15,23,42,0.06); padding: 20px 22px;
        }
        .pos-card-title { display: flex; align-items: center; gap: 8px; font-family: var(--serif); font-weight: 700; font-size: 1.1rem; color: var(--text-primary); margin: 0; }
        .pos-card-title i { font-size: 1.05rem; color: var(--gold-hover); }
        .pos-card-subtitle { margin: 4px 0 16px; font-size: 0.85rem; color: var(--text-secondary); font-weight: 500; }

        .pos-side { display: flex; flex-direction: column; gap: 18px; min-width: 0; }
        @media (min-width: 900px) { .pos-side { position: sticky; top: 96px; } }

        /* Donation Summary — deliberately NOT another plain white card, so the running total
           reads at a glance as the "money" panel rather than just more form. */
        .pos-summary-card {
            background: linear-gradient(135deg, #FFF9ED 0%, #FFF2D0 100%);
            border: 1px solid #E7C36A; border-radius: 14px; padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(15,23,42,0.06);
        }
        .pos-summary-top-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .pos-summary-label { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: #8A6A1E; font-weight: 800; margin-bottom: 0; }
        .pos-summary-orders-link { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.55); border: 1px solid #E7C36A; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; color: #8A6A1E; }
        .pos-summary-orders-link:active { background: rgba(255,255,255,0.85); }
        .pos-summary-row { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
        .pos-summary-row .pos-summary-row-label { font-size: 0.95rem; color: var(--text-secondary); font-weight: 600; }
        .pos-summary-row .pos-summary-row-value { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: 1.15rem; font-weight: 700; color: var(--text-primary); }
        .pos-summary-divider { height: 1px; background: rgba(165,107,19,0.25); margin: 14px 0; }
        .pos-summary-total-row .pos-summary-row-label { font-size: 1.75rem; font-weight: 800; color: var(--text-primary); }
        .pos-summary-total-row .pos-summary-row-value { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: clamp(2.1rem, 5.5vw, 2.8rem); font-weight: 800; color: #A56B13; }
        .pos-summary-name { margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(165,107,19,0.2); font-size: 0.92rem; font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pos-summary-method { margin-top: 4px; font-size: 0.8rem; color: var(--text-secondary); }

        .pos-actions-row { display: flex; flex-direction: column; gap: 10px; }
        .pos-clear-btn { width: 100%; padding: 0 20px; min-height: 54px; border-radius: 12px; border: 1px solid var(--border); background: var(--white); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .pos-clear-btn:active { background: var(--cream); }

        /* A proper full-width bar (like the header) rather than plain text sitting on the
           page background — bottom of the page reads as a distinct navigation-style strip. */
        .pos-footer-bar { flex-shrink: 0; background: var(--white); border-top: 1px solid var(--border); box-shadow: 0 -2px 10px rgba(15,23,42,0.04); }
        .pos-footer { max-width: 1600px; margin: 0 auto; display: flex; align-items: center; gap: 16px; padding: 12px 24px; color: var(--text-secondary); }
        .pos-footer-logo { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); background: #fff; flex-shrink: 0; }
        .pos-footer-text { min-width: 0; display: flex; flex-direction: column; line-height: 1.35; }
        .pos-footer-text strong { color: var(--text-primary); font-size: 0.85rem; }
        .pos-footer-text span { font-size: 0.76rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pos-footer-tagline { flex: 1; display: flex; align-items: center; justify-content: center; gap: 12px; min-width: 0; font-family: var(--serif); font-style: italic; color: var(--gold-hover); font-size: 0.85rem; white-space: nowrap; overflow: hidden; }
        .pos-footer-tagline .line { flex: 1 1 40px; max-width: 60px; height: 1px; background: rgba(201,149,46,0.4); }
        .pos-footer-right { text-align: right; flex-shrink: 0; line-height: 1.35; }
        .pos-footer-date { font-weight: 700; font-size: 0.82rem; color: var(--text-primary); }
        .pos-footer-time { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-weight: 600; font-size: 0.78rem; color: var(--text-secondary); }
        @media (max-width: 700px) { .pos-footer-tagline, .pos-footer-text span { display: none; } }

        .pos-field-label { display: block; font-weight: 700; font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .pos-input {
            width: 100%; padding: 14px 16px; border: 2px solid var(--border); border-radius: 10px; font-size: 1.1rem; font-weight: 600;
            color: var(--text-primary); background: var(--white); min-height: 56px;
        }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,149,46,0.15); }
        .pos-input::placeholder, .pos-amount-input-wrap input::placeholder, .pos-field-inline-input::placeholder { color: #9AA7B4; font-weight: 400; opacity: 1; }
        .pos-textarea { min-height: 78px; font-weight: 500; font-size: 1rem; resize: vertical; }
        .pos-row { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 14px; }
        .pos-row:last-child { margin-bottom: 0; }
        .pos-row.two-col { grid-template-columns: 1fr; }
        @media (min-width: 560px) { .pos-row.two-col { grid-template-columns: 1fr 1fr; } }

        /* Donor fields — a leading icon plus a stacked label/placeholder inside one bordered
           box, all touch targets at least 60px tall. */
        .pos-field-box { display: flex; align-items: center; gap: 12px; border: 2px solid var(--border); border-radius: 10px; padding: 8px 16px; min-height: 62px; background: var(--white); }
        .pos-field-box:focus-within { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,149,46,0.15); }
        .pos-field-icon { font-size: 1.2rem; color: var(--text-secondary); flex-shrink: 0; }
        .pos-field-stack { display: flex; flex-direction: column; flex: 1; min-width: 0; }
        .pos-field-inline-label { font-size: 0.76rem; color: var(--text-secondary); font-weight: 700; }
        .pos-field-inline-input { border: none; outline: none; background: transparent; font-size: 1.08rem; font-weight: 600; color: var(--text-primary); padding: 0; width: 100%; }

        /* Shared "currency-prefixed" amount field — used for both the plain free-amount
           input and any per-tier free-amount input, so a donor/operator always sees the
           currency right next to what they're typing. */
        .pos-amount-input-wrap { display: flex; align-items: stretch; border: 2px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--white); }
        .pos-amount-input-wrap:focus-within { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,149,46,0.15); }
        .pos-amount-prefix { display: flex; align-items: center; justify-content: center; padding: 0 16px; background: var(--cream); color: var(--text-secondary); font-weight: 800; font-size: 1.1rem; border-right: 2px solid var(--border); flex-shrink: 0; }
        .pos-amount-input-wrap input { border: none; flex: 1; min-width: 0; min-height: 64px; padding: 14px 16px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); background: transparent; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; }
        .pos-amount-input-wrap input:focus { outline: none; box-shadow: none; }
        .pos-amount-input-stack { display: flex; flex-direction: column; justify-content: center; flex: 1; min-width: 0; }
        .pos-amount-input-stack input { padding: 12px 16px 0; min-height: auto; }
        .pos-amount-hint { padding: 0 16px 10px; font-size: 0.8rem; color: var(--text-secondary); }

        .pos-section-title { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin: 20px 0 10px; }
        .pos-section-title:first-child { margin-top: 0; }
        .pos-section-title i { font-size: 1rem; color: var(--gold-hover); }

        .pos-card-header-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .pos-card-header-row .pos-card-subtitle { margin: 4px 0 0; }

        .pos-quick-amounts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 14px; }
        .pos-quick-amount-btn { background: var(--shade); border: 1px solid var(--shade-border); color: var(--text-primary); font-weight: 800; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; padding: 10px 8px; min-height: 72px; border-radius: 12px; font-size: 1.3rem; }
        .pos-quick-amount-btn:active { background: #E7ECF1; }
        .pos-quick-amount-btn.active { background: var(--gold); border-color: var(--gold); color: white; box-shadow: 0 6px 16px rgba(201,149,46,0.32); }
        .pos-quick-amount-btn.custom-amount-btn, .pos-tier-quick-btn.custom-amount-btn {
            background: linear-gradient(135deg, #FFF9ED 0%, #FFF2D0 100%); border-color: var(--gold);
            color: var(--text-primary); font-size: 1rem; font-weight: 700; line-height: 1.5;
        }

        /* Donation-type pills — sit in the card's header row (top-right), each toggling its
           own detail block below. A single-option event (the common case today) shows one
           pill that's always active; multiple options behave as an additive multi-select
           "cart", each with its own detail area revealed only while its pill is active. */
        .pos-tier-pills-row { display: flex; flex-wrap: wrap; gap: 10px; }
        .pos-tier-pill { display: inline-flex; align-items: center; gap: 8px; padding: 0 22px; min-height: 54px; border-radius: 10px; border: 2px solid var(--border); background: var(--white); font-weight: 700; font-size: 1.02rem; color: var(--text-primary); cursor: pointer; user-select: none; }
        .pos-tier-pill input[type="checkbox"] { display: none; }
        .pos-tier-pill.active { background: var(--gold); border-color: var(--gold); color: #fff; }

        .pos-tier-detail { display: none; }
        .pos-tier-detail.active { display: block; }
        .pos-tier-fixed-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 4px 0; }
        .pos-tier-fixed-amount { font-weight: 700; font-size: 1.1rem; color: var(--text-primary); }
        .pos-tier-qty { width: 84px; min-height: 52px; padding: 10px; font-size: 1.1rem; font-weight: 700; border: 2px solid var(--border); border-radius: 10px; text-align: center; }
        .pos-tier-free-quick-amounts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; width: 100%; margin-bottom: 12px; }
        .pos-tier-free-quick-amounts .pos-tier-quick-btn { background: var(--shade); border: 1px solid var(--shade-border); color: var(--text-primary); font-weight: 800; min-height: 72px; border-radius: 12px; font-size: 1.25rem; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; }
        .pos-tier-free-quick-amounts .pos-tier-quick-btn:active { background: var(--gold); border-color: var(--gold); color: white; }
        .pos-tier-total-row { display: none; }

        .pos-method-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .pos-method-btn { flex: 1 1 calc(33.33% - 8px); min-width: 100px; padding: 14px 10px; min-height: 116px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); font-weight: 700; font-size: 1rem; color: var(--text-primary); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 6px 16px rgba(201,149,46,0.3); }
        .pos-method-icon-badge { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--cream); margin-bottom: 8px; }
        .pos-method-btn.active .pos-method-icon-badge { background: rgba(255,255,255,0.25); }
        .pos-method-icon-badge i { font-size: 1.3rem; color: var(--gold-hover); }
        .pos-method-btn.active .pos-method-icon-badge i { color: #fff; }

        .pos-save-btn {
            width: 100%; padding: 18px; border-radius: 12px; border: none;
            background: #6B0F1A; color: white; font-weight: 700; font-size: 1.15rem;
            box-shadow: 0 4px 14px rgba(107,15,26,0.35); min-height: 68px;
        }
        .pos-save-btn:disabled { opacity: 0.55; }
        .pos-save-btn:active { transform: scale(0.98); }

        /* ---------- Recent Orders popup (opened from the Donation Summary link) ---------- */
        .pos-orders-modal-total-row { display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.05rem; color: var(--text-primary); padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid var(--border); }
        .pos-orders-modal-total-row span:last-child { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; color: #A56B13; }
        .pos-orders-list { max-height: 320px; overflow-y: auto; margin-bottom: 16px; }
        .pos-order-item { display: flex; justify-content: space-between; gap: 10px; padding: 10px 2px; border-bottom: 1px solid var(--cream); font-size: 0.92rem; }
        .pos-order-item:last-child { border-bottom: none; }
        .pos-order-name { font-weight: 700; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
        .pos-order-amount { font-weight: 700; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; color: var(--text-primary); flex-shrink: 0; }
        .pos-order-time { color: var(--text-secondary); flex-shrink: 0; width: 70px; text-align: right; }
        .pos-orders-empty { color: var(--text-secondary); font-size: 0.9rem; text-align: center; padding: 20px 0; }

        .pos-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 18px 26px; border-radius: 14px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 14px 34px rgba(0,0,0,0.2); z-index: 999; display: none; }
        .pos-toast.error { background: var(--error); }

        /* ---------- EFT terminal status popup — center-screen, mirrors what's on the
           physical/virtual PIN pad while a card payment is in progress ---------- */
        .eft-modal-overlay {
            position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000;
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal {
            background: var(--white); border-radius: 22px; width: 100%; max-width: 380px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center;
        }
        .eft-modal-header {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em;
            font-size: 0.95rem; text-transform: uppercase;
        }
        .eft-modal-body { padding: 30px 26px 26px; }
        .eft-modal-amount { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: 2.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 18px; }
        .eft-modal-status-box {
            background: var(--cream); border: 2px solid var(--border); border-radius: 14px;
            padding: 18px 16px; min-height: 90px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 6px; margin-bottom: 22px;
        }
        .eft-modal-spinner {
            width: 26px; height: 26px; border-radius: 50%;
            border: 3px solid rgba(200,155,60,0.25); border-top-color: var(--gold);
            animation: eftSpin 0.8s linear infinite; margin-bottom: 4px; display: none;
        }
        .eft-modal-status-box.pending .eft-modal-spinner { display: block; }
        .eft-modal-status-icon { font-size: 1.6rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error { display: block; color: var(--error); }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { color: var(--error); }
        @keyframes eftSpin { to { transform: rotate(360deg); } }
        .eft-modal-cancel-btn {
            width: 100%; padding: 14px; border-radius: 12px; border: 2px solid var(--border);
            background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem;
        }
        .eft-modal-cancel-btn:active { background: var(--cream); }

        /* This station's EFT terminal picker — same modal box styling as the EFT status
           popup, since it's the same visual family, just listing selectable terminal rows. */
        .terminal-picker-row { width: 100%; text-align: left; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border); background: var(--white); display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 0.95rem; font-weight: 600; color: var(--text-primary); margin-bottom: 10px; }
        .terminal-picker-row.selected { border-color: var(--gold); background: var(--cream); }
        .terminal-picker-row .paired-badge-group { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .terminal-picker-row .paired-badge { font-size: 0.75rem; font-weight: 700; }
        .terminal-picker-row .paired-badge.yes { color: var(--success); }
        .terminal-picker-row .paired-badge.no { color: var(--error); }
        .terminal-picker-row .paired-badge.unknown { color: #B7791F; }

        /* Small at-a-glance online/offline dot on the header's terminal picker button —
           so the operator can tell a terminal has gone offline without opening the picker. */
        .terminal-status-dot { width: 9px; height: 9px; border-radius: 50%; background: #9AA7B4; flex-shrink: 0; }
        .terminal-status-dot.online { background: #34D399; box-shadow: 0 0 0 3px rgba(52,211,153,0.3); }
        .terminal-status-dot.offline { background: #F87171; box-shadow: 0 0 0 3px rgba(248,113,113,0.3); }
        .terminal-status-dot.unknown { background: #FBBF24; }

        /* Terminal soft-key buttons (OK/Yes/No/Authorise) — only ever shown when Linkly's own
           display notification currently flags that key as available (data.controls), never
           guessed at or shown speculatively. */
        .eft-modal-keys { display: none; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .eft-modal-keys.active { display: flex; }
        .eft-modal-key-btn {
            flex: 1 1 auto; min-width: 90px; padding: 13px 10px; border-radius: 12px; border: 2px solid transparent;
            font-weight: 700; font-size: 0.95rem; color: #fff;
        }
        .eft-modal-key-btn:active { filter: brightness(0.92); }
        .eft-modal-key-btn.key-ok, .eft-modal-key-btn.key-yes, .eft-modal-key-btn.key-authorise { background: var(--success); }
        .eft-modal-key-btn.key-no { background: var(--error); }

        /* CBA Smart Terminal (mx51 SCI) — dynamic Action Framework elements, rendered from
           whatever pos_instructions the terminal sends for this step (text/button/input/
           image), never a fixed set like the Linkly soft-keys above. */
        .sci-af-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .sci-af-row:last-child { margin-bottom: 0; }
        .sci-af-text { width: 100%; font-size: 0.92rem; color: var(--text-secondary); text-align: left; }
        .sci-af-btn { flex: 1 1 auto; min-width: 100px; padding: 13px 10px; border-radius: 12px; border: 2px solid transparent; font-weight: 700; font-size: 0.95rem; color: #fff; background: var(--maroon); }
        .sci-af-btn:active { filter: brightness(0.92); }
        .sci-af-input { flex: 1 1 auto; min-width: 140px; padding: 12px 14px; border-radius: 10px; border: 2px solid var(--border); font-size: 0.95rem; }
        .sci-af-image { max-width: 100%; border-radius: 10px; }
        .sci-af-details { text-align: left; font-size: 0.82rem; color: var(--text-secondary); }
        #eftModalActionFramework { margin-bottom: 12px; }

        /* Manual recovery override — CBA SCI has no cancel API, so once a transaction has
           actually started, "Cancel" is replaced by an honest "confirm the real outcome"
           prompt instead of pretending the payment can be stopped mid-flight. */
        .eft-modal-override p { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 14px; }
        .eft-modal-override-actions { display: flex; gap: 10px; margin-bottom: 10px; }
        .eft-override-btn { flex: 1 1 auto; padding: 13px 10px; border-radius: 12px; border: 2px solid transparent; font-weight: 700; font-size: 0.9rem; color: #fff; }
        .eft-override-btn.eft-override-yes { background: var(--success); }
        .eft-override-btn.eft-override-no { background: var(--error); }

        @media (max-width: 600px) {
            .pos-quick-amount-btn { flex: 1 1 calc(50% - 10px); }
            .pos-method-btn { flex: 1 1 calc(50% - 10px); }
        }
    </style>
</head>
<body>
    <header class="pos-topbar">
        <div class="pos-topbar-brand">
            <img src="{{ $temple['admin_logo_icon'] ?? $temple['logo'] ?? '' }}" alt="" class="pos-topbar-logo">
            <div>
                <div class="pos-topbar-temple-name">{{ $temple['name'] ?? 'Temple' }}</div>
                @if(!empty($temple['subtitle']))
                <div class="pos-topbar-temple-sub">{{ $temple['subtitle'] }}</div>
                @endif
            </div>
        </div>

        <div class="pos-topbar-event-title">
            <span class="pos-flourish-line"></span>
            <div class="pos-topbar-event-title-text">
                <h1>{{ $event->event_name }}</h1>
                @if(!empty($temple['eyebrow']))
                <div class="pos-event-motto">{{ $temple['eyebrow'] }}</div>
                @endif
            </div>
            <span class="pos-flourish-line"></span>
        </div>

        <div class="pos-topbar-actions">
            @if($switchableEvents->count())
            <div class="dropdown">
                <button class="pos-topbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Switch Event">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Switch Event</h6></li>
                    @foreach($switchableEvents as $switchEvent)
                    <li><a class="dropdown-item" href="{{ route('admin.events.pos', $switchEvent->event_id) }}">{{ $switchEvent->event_name }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endif
            <button type="button" class="pos-terminal-btn" id="terminalPickerBtn" title="This station's EFT terminal">
                <i class="bi bi-pc-display"></i><span class="terminal-status-dot" id="terminalStatusDot" title="Terminal status"></span><span id="terminalPickerLabel">Terminal</span>
            </button>
            <button type="button" class="pos-topbar-btn" id="posFullscreenBtn" title="Toggle fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
            @if($canReturnToConsole)
            <a href="{{ route('admin.events.console', $event->event_id) }}" class="pos-topbar-btn" title="Back to console"><i class="bi bi-gear-fill"></i></a>
            @endif
            @if($canManageKioskPin)
            <a href="{{ route('kiosk.pin.edit') }}" class="pos-topbar-btn" title="Manage kiosk PIN"><i class="bi bi-grid-3x3-gap-fill"></i></a>
            @endif
            <a href="{{ route('logout', ['from' => 'kiosk']) }}" class="pos-topbar-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>

    <div class="pos-main">
        <div class="pos-grid">
            <div class="pos-col-left">
                <div class="pos-card">
                    <div class="pos-card-title"><i class="bi bi-person-fill"></i>Donor Details</div>
                    <div class="pos-card-subtitle">Who this donation is being recorded for</div>
                    <div class="pos-row">
                        <div class="pos-field-box">
                            <i class="bi bi-person pos-field-icon"></i>
                            <div class="pos-field-stack">
                                <span class="pos-field-inline-label">Full Name *</span>
                                <input type="text" id="posGuestName" placeholder="Enter full name" autocomplete="off" class="pos-field-inline-input">
                            </div>
                        </div>
                    </div>
                    <div class="pos-row two-col">
                        <div class="pos-field-box">
                            <i class="bi bi-telephone pos-field-icon"></i>
                            <div class="pos-field-stack">
                                <span class="pos-field-inline-label">Mobile{{ $event->require_donor_mobile ? '' : ' (optional)' }}</span>
                                <input type="text" id="posGuestMobile" placeholder="04XX XXX XXX" autocomplete="off" class="pos-field-inline-input">
                            </div>
                        </div>
                        <div class="pos-field-box">
                            <i class="bi bi-envelope pos-field-icon"></i>
                            <div class="pos-field-stack">
                                <span class="pos-field-inline-label">Email{{ $event->require_donor_email ? '' : ' (optional)' }}</span>
                                <input type="email" id="posGuestEmail" placeholder="example@email.com" autocomplete="off" class="pos-field-inline-input">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pos-card">
                    <div class="pos-card-header-row">
                        <div>
                            <div class="pos-card-title"><i class="bi bi-heart-fill"></i>Donation Amount</div>
                            <div class="pos-card-subtitle">Select an amount or enter a custom amount</div>
                        </div>
                        <div class="pos-tier-pills-row" id="posTierPills"></div>
                    </div>
                    <div id="posTiersWrap" style="display:none;">
                        <div id="posTiers"></div>
                        <div class="pos-tier-total-row"><span>Total</span><span id="posTierTotal">{{ $temple['currency'] ?? '' }} 0.00</span></div>
                    </div>
                    <div id="posSimpleAmountWrap">
                        <div class="pos-quick-amounts" id="posQuickAmounts"></div>
                        <div class="pos-amount-input-wrap">
                            <span class="pos-amount-prefix">{{ $temple['currency'] ?? '$' }}</span>
                            <div class="pos-amount-input-stack">
                                <input type="text" inputmode="decimal" id="posAmount" placeholder="Enter amount">
                                <span class="pos-amount-hint">Any amount</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pos-card">
                    <div class="pos-card-title"><i class="bi bi-card-text"></i>Details (optional)</div>
                    <textarea class="pos-input pos-textarea" id="posDetails" rows="2" placeholder="e.g. In memory of..., family name, special request..."></textarea>
                </div>
            </div>

            <div class="pos-side">
                <div class="pos-summary-card">
                    <div class="pos-summary-top-row">
                        <div class="pos-summary-label"><i class="bi bi-receipt"></i>Donation Summary</div>
                        <button type="button" class="pos-summary-orders-link" id="posOrdersLink">
                            <i class="bi bi-clock-history"></i>Recent Orders (<span id="posOrdersCount">0</span>)
                        </button>
                    </div>
                    <div class="pos-summary-row">
                        <span class="pos-summary-row-label">Amount</span>
                        <span class="pos-summary-row-value" id="posSummaryAmount">{{ $temple['currency'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-summary-divider"></div>
                    <div class="pos-summary-row pos-summary-total-row">
                        <span class="pos-summary-row-label">Total</span>
                        <span class="pos-summary-row-value" id="posSummaryTotal">{{ $temple['currency'] ?? '' }} 0.00</span>
                    </div>
                    <div class="pos-summary-name" id="posSummaryName">Donor not entered yet</div>
                    <div class="pos-summary-method" id="posSummaryMethod"></div>
                </div>

                <div class="pos-card">
                    <div class="pos-card-title"><i class="bi bi-credit-card"></i>Payment Method</div>
                    <div class="pos-card-subtitle">Select how the donor would like to pay</div>
                    <div class="pos-method-row" id="posMethodRow"></div>
                </div>

                <div class="pos-actions-row">
                    <button type="button" class="pos-save-btn" id="posSaveBtn"><i class="bi bi-check-circle-fill me-2"></i>Save Donation</button>
                    <button type="button" class="pos-clear-btn" id="posClearBtn" title="Clear form"><i class="bi bi-arrow-counterclockwise"></i>Clear Form</button>
                </div>
            </div>
        </div>

    </div>

    <footer class="pos-footer-bar">
        <div class="pos-footer">
            <img src="{{ $temple['admin_logo_icon'] ?? $temple['logo'] ?? '' }}" alt="" class="pos-footer-logo">
            <div class="pos-footer-text">
                <strong>{{ $temple['legal_name'] ?? $temple['name'] ?? '' }}</strong>
                <span>{{ $temple['name'] ?? '' }}{{ !empty($temple['subtitle']) ? ', ' . $temple['subtitle'] : '' }}</span>
            </div>
            @if(!empty($temple['eyebrow']))
            <div class="pos-footer-tagline"><span class="line"></span><span>{{ $temple['eyebrow'] }}</span><span class="line"></span></div>
            @endif
            <div class="pos-footer-right">
                <div class="pos-footer-date" id="posFooterDate"></div>
                <div class="pos-footer-time" id="posFooterTime"></div>
            </div>
        </div>
    </footer>

    <!-- Recent Orders — opened from the link at the top of the Donation Summary card,
         rather than a permanent bottom bar, so the bottom of the page stays the plain
         temple-branded footer. -->
    <div class="eft-modal-overlay" id="ordersModalOverlay">
        <div class="eft-modal" style="max-width: 440px; text-align: left;">
            <div class="eft-modal-header" style="text-align:center;"><i class="bi bi-clock-history me-2"></i>Orders This Session</div>
            <div class="eft-modal-body" style="text-align:left; padding: 20px 22px;">
                <div class="pos-orders-modal-total-row">
                    <span>Total this session</span>
                    <span id="posOrdersTotal">{{ $temple['currency'] ?? '' }} 0.00</span>
                </div>
                <div class="pos-orders-list" id="posOrdersList"></div>
                <button type="button" class="eft-modal-cancel-btn" id="ordersModalCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <div class="pos-toast" id="posToast"></div>

    <!-- Shown only if a previous EFT Terminal attempt was left unresolved by a refresh/
         crash — see the DOMContentLoaded handler and startOrResumeEftPurchase() below.
         Hidden by default; never auto-triggers a new charge on its own. -->
    <div id="eftResumeBanner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:2000; background:#7a1f1f; color:#fff; padding:12px 18px; align-items:center; gap:14px; flex-wrap:wrap; justify-content:center;">
        <span id="eftResumeBannerText"></span>
        <button type="button" id="eftResumeBannerBtn" style="background:#fff; color:#7a1f1f; border:none; border-radius:8px; padding:6px 16px; font-weight:700;">Resume Checking</button>
        <button type="button" id="eftResumeBannerDismissBtn" style="background:transparent; color:#fff; border:1px solid #fff; border-radius:8px; padding:6px 16px;">Dismiss</button>
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
                    <button type="button" class="eft-modal-key-btn key-yes" data-key="yes" id="eftModalKeyYes">Yes</button>
                    <button type="button" class="eft-modal-key-btn key-ok" data-key="ok" id="eftModalKeyOk">OK</button>
                    <button type="button" class="eft-modal-key-btn key-no" data-key="no" id="eftModalKeyNo">No</button>
                    <button type="button" class="eft-modal-key-btn key-authorise" data-key="authorise" id="eftModalKeyAuthorise">Authorise</button>
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

    <!-- This station's EFT terminal picker — which registered terminal is plugged in HERE,
         saved per-browser so two stations can each run their own concurrent POS. -->
    <div class="eft-modal-overlay" id="terminalModalOverlay">
        <div class="eft-modal">
            <div class="eft-modal-header"><i class="bi bi-credit-card-2-front-fill me-2"></i>This Station's EFT Terminal</div>
            <div class="eft-modal-body">
                <div id="terminalModalList"></div>
                <button type="button" class="eft-modal-cancel-btn" id="terminalModalCloseBtn">Close</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/sci-action-framework.js') }}"></script>
    @php
        // Built as a plain variable rather than inline inside @json() below — a multi-line
        // array literal with nested ['key'] array-access syntax inside @json(...)'s argument
        // tripped up Blade's own bracket-balance check ("Unclosed '[' ... does not match
        // ')'"), the same way every other @json() call on this page only ever takes a
        // simple variable or function call, never an inline expression like this.
        $pendingEftRecoveryForJs = $pendingEftRecovery ? [
            'sessionId' => $pendingEftRecovery->linkly_session_id,
            'clientRef' => $pendingEftRecovery->client_ref,
            'amount' => (float) $pendingEftRecovery->amount,
            'name' => $pendingEftRecovery->meta['donor_name'] ?? 'Guest',
            'email' => $pendingEftRecovery->meta['email'] ?? '',
            'mobile' => $pendingEftRecovery->meta['mobile'] ?? '',
            'purpose' => $pendingEftRecovery->meta['purpose'] ?? 'General Donation',
            'purposeDetails' => $pendingEftRecovery->meta['purpose_details'] ?? '',
        ] : null;
    @endphp
    <script>
        // A session that expires while this kiosk is left open only ever surfaces to a
        // background fetch() (the various polling/save calls below) as a plain 401 JSON body
        // — Laravel's default unauthenticated() handler never redirects a request that
        // expects JSON. Reloading the page turns that into a normal full-page navigation,
        // which (now unauthenticated) is what actually triggers the server-side redirect to
        // the kiosk login screen — see Authenticate::redirectUsing() in AppServiceProvider.
        (function () {
            const nativeFetch = window.fetch;
            window.fetch = function () {
                return nativeFetch.apply(this, arguments).then(function (res) {
                    if (res.status === 401) { window.location.reload(); }
                    return res;
                });
            };
        })();

        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($effectivePaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EFT_CHARGE_START_URL = @json(route('admin.eft.charge.start'));
        const EFT_CHARGE_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const EFT_CHARGE_CANCEL_URL_BASE = @json(url('/admin/eft/charge/cancel'));
        const EFT_CHARGE_SENDKEY_URL_BASE = @json(url('/admin/eft/charge/sendkey'));
        const EVENT_ID = {{ $event->event_id }};
        const QUICK_AMOUNTS = [51, 101, 201, 501, 1001];
        const REQUIRE_EMAIL = @json((bool) $event->require_donor_email);
        const REQUIRE_MOBILE = @json((bool) $event->require_donor_mobile);
        const CURRENCY_CODE = @json($temple['currency'] ?? '');
        // Server-authoritative Power Fail recovery data (see PosDonationController::show())
        // — survives the browser tab itself being gone, unlike sessionStorage below.
        const PENDING_EFT_RECOVERY = @json($pendingEftRecoveryForJs);
        const EFT_TERMINALS = @json($eftTerminalsForJs);
        const CBA_SCI_CHARGE_START_URL = @json(route('admin.cba-sci.charge.start'));
        const CBA_SCI_CHARGE_STATUS_URL_BASE = @json(url('/admin/cba-sci/charge/status'));
        const CBA_SCI_CHARGE_ACTION_URL_BASE = @json(url('/admin/cba-sci/charge/action'));
        const CBA_SCI_CHARGE_OVERRIDE_URL_BASE = @json(url('/admin/cba-sci/charge/override'));

        // ---------- This station's EFT terminal ----------
        // Two storage layers, deliberately: sessionStorage is scoped per TAB and always wins
        // once this tab has explicitly picked a terminal — this is what keeps two POS tabs
        // on ONE computer genuinely independent (e.g. two virtual PIN pads for testing).
        // localStorage is shared across every tab of this browser and only ever supplies the
        // *suggested default* for a brand-new tab that hasn't picked yet. Without this split,
        // reloading a tab after a different tab picked a different terminal would silently
        // move THIS tab onto that other terminal too — which is what previously caused two
        // concurrent sessions to land on the same physical/virtual terminal and get rejected
        // by Linkly as offline/auto-cancelled.
        const TERMINAL_LOCAL_KEY = 'eventPosEftTerminalId';
        const TERMINAL_SESSION_KEY = 'eventPosEftTerminalId_tab';
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
        function terminalStatusBadge(t) {
            if (t.status === 'online') { return { cls: 'yes', text: 'Online' }; }
            if (t.status === 'offline') { return { cls: 'no', text: 'Offline' }; }
            return { cls: 'unknown', text: 'Not checked' };
        }
        function renderTerminalPickerButton() {
            const el = document.getElementById('terminalPickerLabel');
            if (el) { el.textContent = currentTerminalLabel(); }
            const dot = document.getElementById('terminalStatusDot');
            if (dot) {
                const t = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                const badge = t ? terminalStatusBadge(t) : { cls: 'unknown', text: 'No terminal selected' };
                dot.className = 'terminal-status-dot ' + (badge.cls === 'yes' ? 'online' : (badge.cls === 'no' ? 'offline' : 'unknown'));
                dot.title = badge.text + (t && t.status_at ? ' (' + t.status_at + ')' : '');
            }
        }
        function renderTerminalModalList() {
            const list = document.getElementById('terminalModalList');
            if (!list) { return; }
            list.innerHTML = '';
            if (!EFT_TERMINALS.length) {
                list.innerHTML = '<p class="text-muted small mb-0">No terminals registered yet — add one from Settings.</p>';
                return;
            }
            EFT_TERMINALS.forEach(function (t) {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'terminal-picker-row' + (String(t.id) === String(selectedTerminalId) ? ' selected' : '');
                const statusBadge = terminalStatusBadge(t);
                row.innerHTML = '<span>' + escapeHtmlPos(t.label) + (t.is_default ? ' <span class="text-muted small">(default)</span>' : '') + '</span>' +
                    '<span class="paired-badge-group">' +
                    '<span class="paired-badge ' + (t.paired ? 'yes' : 'no') + '">' + (t.paired ? 'Paired' : 'Not paired') + '</span>' +
                    '<span class="paired-badge ' + statusBadge.cls + '">' + statusBadge.text + '</span>' +
                    '</span>';
                row.addEventListener('click', function () {
                    selectedTerminalId = String(t.id);
                    saveSelectedTerminalId(selectedTerminalId);
                    renderTerminalPickerButton();
                    document.getElementById('terminalModalOverlay').classList.remove('active');
                });
                list.appendChild(row);
            });
        }
        const terminalPickerBtn = document.getElementById('terminalPickerBtn');
        if (terminalPickerBtn) {
            terminalPickerBtn.addEventListener('click', function () {
                renderTerminalModalList();
                document.getElementById('terminalModalOverlay').classList.add('active');
            });
            document.getElementById('terminalModalCloseBtn').addEventListener('click', function () {
                document.getElementById('terminalModalOverlay').classList.remove('active');
            });
            renderTerminalPickerButton();
        }

        function escapeHtmlPos(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Amount fields use type="text" + inputmode="decimal" rather than type="number" —
        // a plain type="number" input silently reports an empty .value (not the text the
        // clerk can see on screen) whenever the browser's own numeric grammar rejects what
        // was typed, which is exactly what produced "Enter a valid amount" even though a
        // number was clearly entered. Sanitizing on input (digits + at most one decimal
        // point) keeps the same numeric-only behaviour without that failure mode.
        function sanitizeDecimalInput(el) {
            let v = el.value.replace(/[^0-9.]/g, '');
            const firstDot = v.indexOf('.');
            if (firstDot !== -1) {
                v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
            }
            el.value = v;
        }
        function bindDecimalSanitizer(el) {
            el.addEventListener('input', function () { sanitizeDecimalInput(el); });
        }

        // Purely a touch affordance alongside the preset amount tiles — focuses the actual
        // free-amount field rather than setting a value, so it never affects the real
        // amount/total calculation (that logic lives entirely in the input's own listeners).
        function appendCustomAmountButton(container, inputEl) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = container.id === 'posQuickAmounts' ? 'pos-quick-amount-btn custom-amount-btn' : 'pos-tier-quick-btn custom-amount-btn';
            b.innerHTML = '<i class="bi bi-pencil-fill"></i><br>Custom Amount';
            b.addEventListener('click', function () {
                inputEl.focus();
                inputEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
            container.appendChild(b);
        }

        // Fullscreen — explicit button only.
        document.getElementById('posFullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { document.documentElement.requestFullscreen().catch(function () {}); }
            else { document.exitFullscreen(); }
        });

        // Payment method — big buttons instead of a dropdown.
        const methodRow = document.getElementById('posMethodRow');
        let selectedMethod = null;
        const methodIcons = { Cash: 'bi-cash-coin', UPI: 'bi-phone-fill', 'Bank Transfer': 'bi-bank2', Cheque: 'bi-postcard-fill', 'EFT Terminal': 'bi-credit-card-2-front-fill', Stripe: 'bi-credit-card-fill' };
        (ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash']).forEach(function (m, idx) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-method-btn' + (idx === 0 ? ' active' : '');
            btn.innerHTML = '<span class="pos-method-icon-badge"><i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i></span>' + m;
            btn.dataset.method = m;
            btn.addEventListener('click', function () {
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
                updatePosSummary();
            });
            methodRow.appendChild(btn);
            if (idx === 0) { selectedMethod = m; }
        });

        // Payment methods that confirm money on the spot ("PAY now") versus ones that only
        // record a claim to be verified later ("pledge") — drives both the summary card's
        // method line and the main action button's label/icon.
        function methodIsImmediate(m) {
            return m && m !== 'Bank Transfer' && m !== 'Cheque';
        }

        // Right-hand Donation Summary card + the main action button's label both track the
        // amount/name/method live, so the operator (and the donor watching the screen) always
        // see exactly what's about to be charged/recorded before pressing anything.
        function updatePosSummary() {
            const amt = parseFloat((amountInput.value || '').trim()) || 0;
            const amtText = CURRENCY_CODE + ' ' + amt.toFixed(2);
            const name = document.getElementById('posGuestName').value.trim();

            const summaryAmount = document.getElementById('posSummaryAmount');
            const summaryTotal = document.getElementById('posSummaryTotal');
            const summaryName = document.getElementById('posSummaryName');
            const summaryMethod = document.getElementById('posSummaryMethod');
            if (summaryAmount) { summaryAmount.textContent = amtText; }
            if (summaryTotal) { summaryTotal.textContent = amtText; }
            if (summaryName) { summaryName.textContent = name || 'Donor not entered yet'; }
            if (summaryMethod) { summaryMethod.textContent = selectedMethod ? ('via ' + selectedMethod) : ''; }

            const saveBtn = document.getElementById('posSaveBtn');
            if (!saveBtn) { return; }
            if (methodIsImmediate(selectedMethod)) {
                saveBtn.innerHTML = '<i class="bi bi-credit-card-2-front-fill me-2"></i>PAY' + (amt > 0 ? ' ' + amtText : '');
            } else {
                saveBtn.innerHTML = '<i class="bi bi-bookmark-check-fill me-2"></i>Record Pledge' + (amt > 0 ? ' — ' + amtText : '');
            }
        }
        document.getElementById('posGuestName').addEventListener('input', updatePosSummary);

        // Donation amount — single-option auto-select / multi-option tiers / plain amount,
        // same logic as the full console's Quick Entry, restyled for touch.
        const amountInput = document.getElementById('posAmount');
        const quickAmountsRow = document.getElementById('posQuickAmounts');
        const tiersWrap = document.getElementById('posTiersWrap');
        const simpleAmountWrap = document.getElementById('posSimpleAmountWrap');
        const tierPillsContainer = document.getElementById('posTierPills');
        const tiersContainer = document.getElementById('posTiers');
        const tierTotalDisplay = document.getElementById('posTierTotal');
        let selections = [];
        let purposeValue = 'Event Donation';

        if (EVENT_OPTIONS.length) {
            const singleOption = EVENT_OPTIONS.length === 1;
            tiersWrap.style.display = 'block';
            simpleAmountWrap.style.display = 'none';

            // Donation types render as a row of pills (top-right of the card) each toggling
            // its own detail block below — a fixed-amount option's detail is just its price
            // (+ a quantity stepper if allowed), a free-amount option's detail is the same
            // preset-tiles + amount-input pattern as the plain amount branch below. Checkbox
            // + detail are matched purely by data-idx (not DOM nesting) so they can live in
            // two visually separate containers (pills row vs. detail area) while still being
            // driven by the exact same underlying checkbox/qty/free-amount elements.
            let pillsHtml = '';
            let detailsHtml = '';
            EVENT_OPTIONS.forEach(function (opt, idx) {
                const hasAmount = opt.amount !== null;
                pillsHtml += '<label class="pos-tier-pill' + (singleOption ? ' single-option active' : '') + '" data-idx="' + idx + '">'
                    + '<input type="checkbox" class="pos-tier-cb" data-idx="' + idx + '"' + (singleOption ? ' checked' : '') + '>'
                    + '<span>' + escapeHtmlPos(opt.label) + '</span></label>';

                detailsHtml += '<div class="pos-tier-detail' + (singleOption ? ' active' : '') + '" data-idx="' + idx + '">'
                    + (hasAmount
                        ? '<div class="pos-tier-fixed-row"><span class="pos-tier-fixed-amount">' + CURRENCY_CODE + ' ' + opt.amount.toFixed(2) + (opt.allow_quantity ? ' each' : '') + '</span>'
                            + (opt.allow_quantity ? '<input type="number" min="1" value="1" class="pos-tier-qty">' : '') + '</div>'
                        : '<div class="pos-tier-free-block">'
                            + '<div class="pos-tier-free-quick-amounts"></div>'
                            + '<div class="pos-amount-input-wrap"><span class="pos-amount-prefix">' + CURRENCY_CODE + '</span>'
                            + '<div class="pos-amount-input-stack"><input type="text" inputmode="decimal" placeholder="Enter amount" class="pos-tier-free"><span class="pos-amount-hint">Any amount</span></div></div></div>')
                    + '</div>';
            });
            tierPillsContainer.innerHTML = pillsHtml;
            tiersContainer.innerHTML = detailsHtml;
            tiersContainer.querySelectorAll('.pos-tier-free').forEach(bindDecimalSanitizer);

            // Quick-amount tiles for each free-amount tier's detail block — the only place
            // preset amount buttons appear once an event has any donation options configured,
            // since the top-level presets (posQuickAmounts) only render when it has none.
            tiersContainer.querySelectorAll('.pos-tier-detail').forEach(function (detail) {
                const quickWrap = detail.querySelector('.pos-tier-free-quick-amounts');
                if (!quickWrap) { return; }
                const idx = detail.dataset.idx;
                const freeInput = detail.querySelector('.pos-tier-free');
                const cb = tierPillsContainer.querySelector('.pos-tier-cb[data-idx="' + idx + '"]');
                QUICK_AMOUNTS.forEach(function (amt) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'pos-tier-quick-btn';
                    b.textContent = '$' + amt.toLocaleString();
                    b.addEventListener('click', function () {
                        cb.checked = true;
                        freeInput.value = amt.toFixed(2);
                        freeInput.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    quickWrap.appendChild(b);
                });
                appendCustomAmountButton(quickWrap, freeInput);
            });

            function recalcTiers() {
                let total = 0;
                const labels = [];
                selections = [];
                EVENT_OPTIONS.forEach(function (opt, idx) {
                    const cb = tierPillsContainer.querySelector('.pos-tier-cb[data-idx="' + idx + '"]');
                    const pill = tierPillsContainer.querySelector('.pos-tier-pill[data-idx="' + idx + '"]');
                    const detail = tiersContainer.querySelector('.pos-tier-detail[data-idx="' + idx + '"]');
                    const qtyInput = detail ? detail.querySelector('.pos-tier-qty') : null;
                    const freeInput = detail ? detail.querySelector('.pos-tier-free') : null;
                    if (pill) { pill.classList.toggle('active', cb.checked); }
                    if (detail) { detail.classList.toggle('active', cb.checked); }
                    if (!cb.checked) { return; }
                    let label = opt.label;
                    let qty = null;
                    let amount = 0;
                    if (opt.amount !== null) {
                        qty = (qtyInput && opt.allow_quantity) ? (parseInt(qtyInput.value, 10) || 1) : 1;
                        amount = opt.amount * qty;
                        if (opt.allow_quantity && qty > 1) { label += ' (x' + qty + ')'; }
                    } else {
                        amount = freeInput ? (parseFloat(freeInput.value) || 0) : 0;
                    }
                    if (amount > 0) {
                        total += amount;
                        labels.push(label);
                        selections.push({ option_id: opt.id, label: label, quantity: qty, amount: amount });
                    }
                });
                amountInput.value = total > 0 ? total.toFixed(2) : '';
                tierTotalDisplay.textContent = CURRENCY_CODE + ' ' + total.toFixed(2);
                purposeValue = labels.length ? labels.join(', ').substring(0, 250) : 'Event Donation';
                updatePosSummary();
            }

            tierPillsContainer.addEventListener('change', recalcTiers);
            tiersContainer.addEventListener('change', recalcTiers);
            tiersContainer.addEventListener('input', recalcTiers);
            if (singleOption) { recalcTiers(); }

            window.posResetTiers = function () {
                tierPillsContainer.querySelectorAll('.pos-tier-cb').forEach(function (cb) {
                    if (!cb.closest('.pos-tier-pill').classList.contains('single-option')) { cb.checked = false; }
                });
                tiersContainer.querySelectorAll('.pos-tier-free').forEach(function (i) { i.value = ''; });
                recalcTiers();
            };
        } else {
            tiersWrap.style.display = 'none';
            simpleAmountWrap.style.display = 'block';
            QUICK_AMOUNTS.forEach(function (amt) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pos-quick-amount-btn';
                btn.textContent = '$' + amt.toLocaleString();
                btn.addEventListener('click', function () {
                    amountInput.value = amt.toFixed(2);
                    quickAmountsRow.querySelectorAll('.pos-quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    updatePosSummary();
                });
                quickAmountsRow.appendChild(btn);
            });
            appendCustomAmountButton(quickAmountsRow, amountInput);
            bindDecimalSanitizer(amountInput);
            amountInput.addEventListener('input', function () {
                quickAmountsRow.querySelectorAll('.pos-quick-amount-btn').forEach(function (b) {
                    b.classList.toggle('active', parseFloat(b.textContent.replace(/[^0-9.]/g, '')) === parseFloat(amountInput.value));
                });
                updatePosSummary();
            });
            purposeValue = 'Event Donation';
            window.posResetTiers = function () {
                quickAmountsRow.querySelectorAll('.pos-quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                purposeValue = 'Event Donation';
            };
        }

        let toastHideTimer = null;
        function showToast(message, isError) {
            if (toastHideTimer) { clearTimeout(toastHideTimer); toastHideTimer = null; }
            const toast = document.getElementById('posToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            toastHideTimer = setTimeout(function () { toast.style.display = 'none'; }, 2200);
        }
        // Center-screen popup mirroring the PIN pad's own display while a card payment is
        // in progress — a corner toast isn't prominent enough for something the operator
        // and donor both need to watch together.
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

        // Recovery/idempotency: one in-flight EFT attempt at a time is remembered here (not
        // just in a JS variable, so it survives a browser refresh) — a client-generated
        // client_ref the backend uses to resume the SAME Linkly session instead of starting a
        // second one, for both a re-clicked Pay button and a reload mid-payment. Cleared once
        // Linkly gives a final, terminal answer (approved/declined/cancelled/failed); left in
        // place on a timeout/unknown result so a resume is still possible, per "an UNKNOWN
        // transaction must not be treated as declined, and must not risk a double charge".
        const EFT_ATTEMPT_KEY = 'eftAttempt_' + EVENT_ID;
        function newClientRef() {
            return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
        }
        function saveEftAttempt(attempt) {
            try { sessionStorage.setItem(EFT_ATTEMPT_KEY, JSON.stringify(attempt)); } catch (e) { /* private browsing etc. — recovery just won't survive a refresh */ }
        }
        function loadEftAttempt() {
            try { return JSON.parse(sessionStorage.getItem(EFT_ATTEMPT_KEY) || 'null'); } catch (e) { return null; }
        }
        function clearEftAttempt() {
            try { sessionStorage.removeItem(EFT_ATTEMPT_KEY); } catch (e) {}
        }

        // CBA Smart Terminal (mx51 SCI) payment flow — shares the same modal DOM as the
        // Linkly flow above (see activeEftProvider guards on both sides) but is driven by
        // sci-action-framework.js's generic Action Framework renderer/poller instead of
        // Linkly's fixed 4-key layout, since SCI sends whatever dynamic UI the terminal's
        // current step calls for.
        let sciLastAttempt = null;
        const sciPaymentFlow = SciActionFramework.createFlow({
            startUrl: CBA_SCI_CHARGE_START_URL,
            statusUrlBase: CBA_SCI_CHARGE_STATUS_URL_BASE,
            actionUrlBase: CBA_SCI_CHARGE_ACTION_URL_BASE,
            overrideUrlBase: CBA_SCI_CHARGE_OVERRIDE_URL_BASE,
            csrfToken: CSRF_TOKEN,
            eventId: EVENT_ID,
            currencyCode: CURRENCY_CODE,
            attemptStorageKey: 'sciAttempt_' + EVENT_ID,
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
                return { purpose: attempt.purpose || '', purpose_details: attempt.purposeDetails || '' };
            },
            onToast: function (message) { showToast(message, true); },
            onApproved: function (donationId, resultAmounts, attempt) {
                activeEftProvider = null;
                const a = attempt || sciLastAttempt;
                showToast('Saved — ' + CURRENCY_CODE + ' ' + (a ? Number(a.amount).toFixed(2) : ''));
                if (a) { addSessionOrder({ name: a.name, amount: Number(a.amount), time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }); }
                resetPosForm();
                document.getElementById('posGuestName').focus();
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
                showToast('Payment cancelled.', true);
            },
        });

        // Which payment flow currently owns the shared eft-modal-overlay DOM — Linkly's own
        // handlers below and the SCI module (sci-action-framework.js, wired up further down)
        // both attach listeners to the SAME cancel/status elements, so each must no-op on a
        // click that isn't theirs. Set the instant either flow's own "start" begins, cleared
        // the instant either flow's own "hide" runs.
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
        // Shows only the soft-key buttons Linkly's latest display notification currently
        // flags as available (data.controls from the poll response) — never guessed at, and
        // hidden again the instant a flag drops or the transaction resolves. Lets an operator
        // respond to a signature-required prompt (or any other terminal soft-key request)
        // from the POS screen instead of only on the terminal itself.
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
            fetch(EFT_CHARGE_SENDKEY_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'key=' + encodeURIComponent(key),
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; });
                    if (!data.success) {
                        showToast(data.message || 'The terminal did not accept that.', true);
                    }
                })
                .catch(function () {
                    Object.values(eftKeyButtons).forEach(function (b) { b.disabled = false; });
                    showToast('Could not reach the terminal — please try again.', true);
                });
        }
        // Only touches the DOM when the status/lines actually differ from what's already
        // shown — polling every ~1.2s would otherwise re-write (and visually flicker) the
        // same unchanged text on every single tick, most of which return nothing new.
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

        // "Orders this session" — sessionStorage only, so it survives a reload of this same
        // browser tab (a kiosk left open all day) but never persists beyond it and never
        // touches the server — the console's own donations table is the real record.
        const SESSION_KEY = 'posOrders_' + EVENT_ID;
        function loadSessionOrders() {
            try { return JSON.parse(sessionStorage.getItem(SESSION_KEY) || '[]'); } catch (e) { return []; }
        }
        function addSessionOrder(order) {
            const orders = loadSessionOrders();
            orders.unshift(order);
            try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(orders)); } catch (e) {}
            renderSessionOrders();
        }
        function renderSessionOrders() {
            const orders = loadSessionOrders();
            const list = document.getElementById('posOrdersList');
            document.getElementById('posOrdersCount').textContent = orders.length;
            document.getElementById('posOrdersTotal').textContent = CURRENCY_CODE + ' ' + orders.reduce(function (s, o) { return s + o.amount; }, 0).toFixed(2);
            list.innerHTML = orders.length
                ? orders.map(function (o) {
                    return '<div class="pos-order-item"><span class="pos-order-name">' + escapeHtmlPos(o.name) + '</span><span class="pos-order-amount">' + CURRENCY_CODE + ' ' + o.amount.toFixed(2) + '</span><span class="pos-order-time">' + o.time + '</span></div>';
                }).join('')
                : '<div class="pos-orders-empty">No donations recorded yet this session.</div>';
        }
        renderSessionOrders();
        updatePosSummary();

        document.getElementById('posOrdersLink').addEventListener('click', function () {
            document.getElementById('ordersModalOverlay').classList.add('active');
        });
        document.getElementById('ordersModalCloseBtn').addEventListener('click', function () {
            document.getElementById('ordersModalOverlay').classList.remove('active');
        });

        function resetPosForm() {
            document.getElementById('posGuestName').value = '';
            document.getElementById('posGuestMobile').value = '';
            document.getElementById('posGuestEmail').value = '';
            document.getElementById('posDetails').value = '';
            amountInput.value = '';
            if (window.posResetTiers) { window.posResetTiers(); }
            updatePosSummary();
        }
        document.getElementById('posClearBtn').addEventListener('click', function () {
            resetPosForm();
            document.getElementById('posGuestName').focus();
        });

        // A plain wall clock in the footer — purely cosmetic, no server round-trip.
        function tickPosFooterClock() {
            const dateEl = document.getElementById('posFooterDate');
            const timeEl = document.getElementById('posFooterTime');
            if (!dateEl || !timeEl) { return; }
            const now = new Date();
            dateEl.textContent = now.toLocaleDateString([], { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
            timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        tickPosFooterClock();
        setInterval(tickPosFooterClock, 15000);

        function submitGuestDonation(btn, amount, name, emailValue, mobileValue, transactionId, linklySessionId) {
            const today = new Date().toISOString().slice(0, 10);
            const body = new URLSearchParams();
            body.set('event_id', EVENT_ID);
            body.set('amount', amount.toFixed(2));
            body.set('selections_json', JSON.stringify(selections));
            // Cash, UPI and an EFT terminal all confirm the money on the spot, so those record
            // as Paid immediately. A Bank Transfer claim can't be verified at the counter — it
            // sits Pending until an admin checks the account and approves it, same as the
            // public donation form.
            body.set('payment_status', selectedMethod === 'Bank Transfer' ? 'Pending' : 'Paid');
            body.set('transaction_id', transactionId || '');
            // Links this donation back to its Linkly accreditation ledger row — set only for
            // an EFT Terminal payment (see DonationController::linkLedgerToDonation()).
            if (linklySessionId) { body.set('linkly_session_id', linklySessionId); }
            body.set('donor_name', name);
            body.set('donation_date', today);
            body.set('email', emailValue);
            body.set('mobile', mobileValue);
            body.set('payment_method', selectedMethod === 'Bank Transfer' ? 'Bank' : selectedMethod);
            body.set('purpose', purposeValue);
            body.set('purpose_details', document.getElementById('posDetails').value);

            fetch(STORE_GUEST_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        const pendingNote = selectedMethod === 'Bank Transfer' ? ' (Pending)' : '';
                        showToast('Saved — ' + CURRENCY_CODE + ' ' + amount.toFixed(2) + pendingNote);
                        addSessionOrder({ name: name, amount: amount, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
                        resetPosForm();
                        document.getElementById('posGuestName').focus();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        }

        document.getElementById('posSaveBtn').addEventListener('click', function () {
            const amount = parseFloat((amountInput.value || '').trim());
            if (!amount || amount <= 0) { showToast('Enter a valid amount.', true); return; }

            const name = document.getElementById('posGuestName').value.trim();
            if (!name) { showToast('Enter the donor name.', true); return; }

            const emailValue = document.getElementById('posGuestEmail').value.trim();
            if (REQUIRE_EMAIL && !emailValue) { showToast('Enter the donor email.', true); return; }

            const mobileValue = document.getElementById('posGuestMobile').value.trim();
            if (REQUIRE_MOBILE && !mobileValue) { showToast('Enter the donor mobile number.', true); return; }

            const btn = this;

            // EFT Terminal charges the physical/virtual PIN pad and waits for the donor to
            // tap/insert their card before recording anything — a declined or failed
            // transaction never reaches storeGuestDonation() at all. Runs as start-then-poll
            // (not one blocking call) so the terminal's live prompts ("ENTER PIN", etc.,
            // fed by Linkly's webhook postbacks) can actually reach the screen.
            if (selectedMethod === 'EFT Terminal') {
                const selectedTerminal = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                if (!selectedTerminal || !selectedTerminal.paired) {
                    showToast('This station\'s EFT terminal (' + currentTerminalLabel() + ') is not paired yet — check the terminal picker.', true);
                    return;
                }
                btn.disabled = true;

                if (selectedTerminal.provider === 'cba_sci') {
                    activeEftProvider = 'cba_sci';
                    sciPaymentFlow.start(btn, {
                        clientRef: newClientRef(),
                        amount: amount,
                        name: name,
                        email: emailValue,
                        mobile: mobileValue,
                        purpose: purposeValue,
                        purposeDetails: document.getElementById('posDetails').value,
                        terminalId: selectedTerminalId,
                    });
                    return;
                }

                startOrResumeEftPurchase(btn, {
                    clientRef: newClientRef(),
                    amount: amount,
                    name: name,
                    email: emailValue,
                    mobile: mobileValue,
                    // Sent on to startEftCharge() too (not just kept for submitGuestDonation
                    // later) so the server can save the donation itself if the browser never
                    // gets the chance to — see createDonationIfApprovedPurchaseUnrecorded().
                    purpose: purposeValue,
                    purposeDetails: document.getElementById('posDetails').value,
                });
                return;
            }

            btn.disabled = true;
            submitGuestDonation(btn, amount, name, emailValue, mobileValue, '', '');
        });

        // Shared by a fresh Pay click and the "Resume" recovery banner — an existing,
        // not-yet-finished attempt (passed in by resumeEftAttempt()) always wins over
        // starting a brand new one, so a re-clicked Pay button or a page reload mid-payment
        // never starts a second Linkly session for the same checkout.
        function startOrResumeEftPurchase(btn, freshAttempt) {
            const attempt = loadEftAttempt() || freshAttempt;
            saveEftAttempt(attempt);

            eftPollCancelled = false;
            eftCurrentSessionId = null;
            eftConsecutiveTransientErrors = 0;
            showEftModal(attempt.amount);
            const startBody = new URLSearchParams();
            startBody.set('event_id', EVENT_ID);
            startBody.set('amount', attempt.amount.toFixed(2));
            startBody.set('client_ref', attempt.clientRef);
            startBody.set('donor_name', attempt.name || '');
            startBody.set('email', attempt.email || '');
            startBody.set('mobile', attempt.mobile || '');
            startBody.set('purpose', attempt.purpose || '');
            startBody.set('purpose_details', attempt.purposeDetails || '');
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
                    pollEftTransaction(result.data.session_id, btn, attempt.amount, attempt.name, attempt.email, attempt.mobile, Date.now());
                })
                .catch(function () {
                    btn.disabled = false;
                    hideEftModal();
                    showToast('Could not reach the EFT terminal — please try again.', true);
                });
        }

        // Set true by the modal's Cancel button — checked at the top of every poll tick so
        // an abandoned wait actually stops instead of continuing in the background.
        let eftPollCancelled = false;
        // The in-flight transaction's session id, so the Cancel button can tell Linkly to
        // actually cancel it (via cancelEftCharge -> LinklyEftService::cancel(), a sendkey
        // "0" to the terminal) rather than only dismissing the modal locally.
        let eftCurrentSessionId = null;
        document.getElementById('eftModalCancelBtn').addEventListener('click', function () {
            if (activeEftProvider !== 'linkly') { return; }
            const sessionId = eftCurrentSessionId;

            // Nothing has actually started on the terminal yet — safe to just abandon
            // locally, nothing to reconcile.
            if (!sessionId) {
                eftPollCancelled = true;
                clearEftAttempt();
                hideEftModal();
                document.getElementById('posSaveBtn').disabled = false;
                showToast('Payment cancelled.', true);
                return;
            }

            // Deliberately does NOT set eftPollCancelled / hide the modal / clear the
            // attempt yet. A previous version did this unconditionally, which caused a real
            // incident: the terminal rejected the cancel (it had already moved past that
            // step) but the browser had already stopped watching, so the transaction went on
            // to be approved with no donation ever recorded for the charge. Only a CONFIRMED
            // cancel is allowed to stop the poll loop below; on failure the already-scheduled
            // poll keeps running and will still catch the real approved/declined outcome.
            const cancelBtn = this;
            cancelBtn.disabled = true;
            setEftModalStatus(['Cancelling…'], 'pending');

            fetch(EFT_CHARGE_CANCEL_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID), {
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
                        document.getElementById('posSaveBtn').disabled = false;
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

        // Polls every ~1.2s for up to ~3 minutes (matching Linkly's own pairing/transaction
        // window guidance) — each response carries the PIN pad's current display text (if
        // any arrived via webhook since the last poll) and, once the terminal finishes,
        // the final approved/declined result.
        // Error Recovery — Exponential Back Off (Core Payments accreditation requirement
        // 1.2.1/1.2.2): a 408 or 500-599 response (or a network-level failure reaching
        // Linkly at all) backs off exponentially — 1.2s, 2.4s, 4.8s, ... capped at 30s —
        // instead of hammering Linkly at the normal ~1.2s cadence, to avoid undue load on
        // their backend while an outage clears. Resets to the normal cadence the moment a
        // non-transient response comes back (in-progress, approved, declined, whatever).
        let eftConsecutiveTransientErrors = 0;
        function eftNextPollDelay(wasTransientError) {
            if (!wasTransientError) {
                eftConsecutiveTransientErrors = 0;
                return 1200;
            }
            eftConsecutiveTransientErrors++;
            return Math.min(1200 * Math.pow(2, eftConsecutiveTransientErrors), 30000);
        }

        function pollEftTransaction(sessionId, btn, amount, name, emailValue, mobileValue, startedAt) {
            if (eftPollCancelled) { return; }

            // This local ~3-minute guard just stops the browser polling forever — it does
            // NOT clear the saved attempt, and startOrResumeEftPurchase() always resumes an
            // existing attempt rather than starting a new one, so clicking Pay again here is
            // safe (it re-attaches to this same Linkly session instead of double-charging).
            // The server independently reaches the same "unknown" conclusion around 200s in
            // pollEftCharge() if this client-side guard is somehow bypassed.
            if (Date.now() - startedAt > 180000) {
                btn.disabled = false;
                setEftModalStatus(['No response from the terminal yet', 'Press Pay to keep checking — this will not charge twice'], 'error');
                setTimeout(hideEftModal, 2200);
                showToast('No final result yet — press Pay to keep checking (safe, will not double-charge).', true);
                return;
            }

            const statusUrl = EFT_CHARGE_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID);
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (eftPollCancelled) { return; }

                    if (data.display && data.display.length) {
                        setEftModalStatus(data.display, 'pending');
                    }
                    updateEftModalKeys(data.done ? null : data.controls, sessionId);
                    if (!data.done) {
                        setTimeout(function () {
                            pollEftTransaction(sessionId, btn, amount, name, emailValue, mobileValue, startedAt);
                        }, eftNextPollDelay(!!data.transient_error));
                        return;
                    }
                    if (data.success) {
                        // A genuine final answer — this checkout attempt is over either way,
                        // so the next Pay click must start a fresh one, not resume this.
                        clearEftAttempt();
                        setEftModalStatus(['PAYMENT APPROVED', data.auth_code ? 'Auth ' + data.auth_code : 'Saving donation…'], 'success');
                        setTimeout(hideEftModal, 1200);
                        submitGuestDonation(btn, amount, name, emailValue, mobileValue, data.rrn || data.auth_code || '', sessionId);
                    } else if (data.payment_status === 'unknown') {
                        // Per Linkly's Core Payments recovery requirement: never treat this as
                        // a decline, and never let it look "safe to just try again" without
                        // the resume mechanism — so the attempt stays saved for a real resume.
                        setEftModalStatus(['RESULT UNKNOWN', 'Check the terminal/bank statement, then press Pay to resume checking'], 'error');
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
                    // A single failed poll isn't fatal — try again rather than abandoning a
                    // transaction that may still complete on the terminal, backing off
                    // exponentially the same as a 408/500-599 response from the server.
                    setTimeout(function () {
                        pollEftTransaction(sessionId, btn, amount, name, emailValue, mobileValue, startedAt);
                    }, eftNextPollDelay(true));
                });
        }

        // Power Fail Recovery (Core Payments accreditation requirement 4.1.2): the status GET
        // must be performed automatically at startup — not wait for the operator to notice
        // and click something. PENDING_EFT_RECOVERY (from the server, see
        // PosDonationController::show()) is the authoritative source: a real power failure
        // can take the browser tab itself with it, and sessionStorage dies with that tab —
        // only the server's own ledger is guaranteed to still know about an unfinished
        // transaction, from any device that reopens this page. sessionStorage's own
        // loadEftAttempt() is kept only as a fallback for the lighter "same tab, page
        // refreshed" case if the server-side row has somehow already gone terminal.
        document.addEventListener('DOMContentLoaded', function () {
            const banner = document.getElementById('eftResumeBanner');
            if (!banner) { return; }

            if (PENDING_EFT_RECOVERY) {
                const p = PENDING_EFT_RECOVERY;
                document.getElementById('eftResumeBannerText').textContent =
                    'Checking a previous EFT Terminal payment (' + CURRENCY_CODE + ' ' + p.amount.toFixed(2) + ' for ' + p.name + ') that did not finish…';
                banner.style.display = 'flex';
                document.getElementById('eftResumeBannerBtn').style.display = 'none';
                document.getElementById('eftResumeBannerDismissBtn').addEventListener('click', function () {
                    banner.style.display = 'none';
                    eftPollCancelled = true;
                    clearEftAttempt();
                });

                const btn = document.getElementById('posSaveBtn');
                btn.disabled = true;
                // The server already knows the real Linkly session id — no need to call
                // startEftCharge() again at all, just resume polling it directly.
                eftPollCancelled = false;
                eftCurrentSessionId = p.sessionId;
                eftConsecutiveTransientErrors = 0;
                saveEftAttempt({ clientRef: p.clientRef, amount: p.amount, name: p.name, email: p.email, mobile: p.mobile, purpose: p.purpose, purposeDetails: p.purposeDetails });
                showEftModal(p.amount);
                pollEftTransaction(p.sessionId, btn, p.amount, p.name, p.email, p.mobile, Date.now());
                return;
            }

            const attempt = loadEftAttempt();
            if (attempt) {
                document.getElementById('eftResumeBannerText').textContent =
                    'Checking a previous EFT Terminal payment (' + CURRENCY_CODE + ' ' + Number(attempt.amount).toFixed(2) + ' for ' + attempt.name + ') that did not finish…';
                banner.style.display = 'flex';
                document.getElementById('eftResumeBannerBtn').style.display = 'none';
                document.getElementById('eftResumeBannerDismissBtn').addEventListener('click', function () {
                    banner.style.display = 'none';
                    eftPollCancelled = true;
                    clearEftAttempt();
                });

                const btn = document.getElementById('posSaveBtn');
                btn.disabled = true;
                startOrResumeEftPurchase(btn, attempt);
                return;
            }

            // CBA Smart Terminal has no server-authoritative recovery query yet (unlike
            // PENDING_EFT_RECOVERY above) — this same-tab-refresh fallback is all that's
            // wired up for it so far.
            const sciBtn = document.getElementById('posSaveBtn');
            activeEftProvider = 'cba_sci';
            if (!sciPaymentFlow.resumeFromStorage(sciBtn)) {
                activeEftProvider = null;
            }
        });
    </script>
</body>
</html>
