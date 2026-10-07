<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>POS · {{ $event->event_name }} · SievesPOS v{{ config('sievespos.version') }}</title>
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
            /* Firmer than the old #E6E9ED — a POS is read at a glance and worked fast, so
               every card/input/button needs a border that actually registers rather than
               nearly matching the white it sits on. */
            --border: #C7D0DA;
            --shade: #E7ECF1;
            --shade-border: #C3CEDA;
            --text-primary: #102A43;
            --text-secondary: #52667A;
            --success: #10B981;
            --error: #EF4444;
            --serif: 'Playfair Display', Georgia, serif;
            /* One shared radius scale, deliberately tighter than the old 12-22px range — a
               terminal/POS screen reads as more purposeful with crisp, moderate corners than
               with soft app-style bubbles. */
            --radius-sm: 8px;
            --radius-md: 10px;
            --radius-lg: 14px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        /* POS hardening: no accidental text selection/callouts from a fast tap-and-hold,
           and no 300ms ghost-click delay on older mobile Safari/Chrome — both matter more
           here than on an ordinary page since this runs as an unattended counter device. */
        button, .pos-tier-pill, .pos-method-btn, .terminal-picker-row { -webkit-user-select: none; user-select: none; touch-action: manipulation; }
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
        /* True 3-column grid rather than flex-with-competing-shrink-priorities — the old
           approach ("brand shrinks 3x eagerly, actions never shrink") meant the actions side
           could simply out-muscle the event title for space on a narrow phone, squeezing it
           to nothing rather than truncating gracefully. A grid's center track is genuinely
           centered on the row regardless of how wide the two side tracks are, as long as it
           fits — no shrink-priority tug-of-war involved. */
        .pos-topbar {
            background: #6B0F1A; position: sticky; top: 0; flex-shrink: 0;
            color: white; padding: 12px 20px; display: grid;
            grid-template-columns: minmax(0,1fr) auto minmax(0,1fr);
            grid-template-areas: "brand title actions";
            align-items: center; column-gap: 14px;
            box-shadow: 0 2px 10px rgba(15,23,42,0.18); z-index: 20; min-height: 72px;
        }
        /* Below the point a single row gets cramped, the event name — the one thing an
           operator actually needs to confirm at a glance — gets its own full-width row
           instead of fighting brand/actions for leftover space. This is what actually
           guarantees it stays dead-center and fully visible, rather than shrinking away. */
        @media (max-width: 899px) {
            .pos-topbar {
                grid-template-columns: minmax(0,1fr) auto;
                grid-template-areas: "brand actions" "title title";
                row-gap: 10px; padding: 12px 16px 14px;
            }
        }
        .pos-topbar-brand { grid-area: brand; display: flex; align-items: center; gap: 10px; min-width: 0; }
        .pos-topbar-logo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #fff; padding: 2px; flex-shrink: 0; }
        .pos-topbar-brand-text { min-width: 0; overflow: hidden; }
        .pos-topbar-temple-name { font-weight: 800; font-size: clamp(0.8rem, 2.4vw, 1rem); line-height: 1.2; font-family: var(--serif); color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-topbar-temple-sub { font-size: 0.72rem; color: rgba(255,255,255,0.6); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .pos-topbar-event-title { grid-area: title; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 14px; text-align: center; overflow: hidden; }
        .pos-flourish-line { flex: 1; max-width: 90px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold), transparent); display: none; flex-shrink: 0; }
        @media (min-width: 900px) { .pos-flourish-line { display: block; } }
        .pos-topbar-event-title-text { min-width: 0; max-width: 100%; }
        .pos-topbar-event-title-text h1 { font-family: var(--serif); font-size: clamp(1.15rem, 2.6vw, 1.6rem); font-weight: 800; color: var(--gold); margin: 0; letter-spacing: 0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-event-motto { font-size: 0.74rem; color: rgba(255,255,255,0.75); letter-spacing: 0.03em; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 900px) { .pos-event-motto { display: none; } }

        .pos-topbar-actions { grid-area: actions; display: flex; align-items: center; gap: 8px; min-width: 0; justify-content: flex-end; }
        .pos-topbar-btn { position: relative; background: rgba(255,255,255,0.08); border: 1.5px solid rgba(255,255,255,0.35); color: white; width: 44px; height: 44px; border-radius: var(--radius-sm); font-size: 1.05rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .pos-topbar-btn:hover { background: rgba(255,255,255,0.18); }
        /* Flags that setup is incomplete — only ever shown when no terminal is paired yet. */
        .pos-topbar-btn-setup::after { content: ''; position: absolute; top: 2px; right: 2px; width: 9px; height: 9px; border-radius: 50%; background: var(--gold); border: 1.5px solid var(--maroon-dark); }
        {{-- The real terminal product photo, not a generic monitor glyph — sized a touch larger
             than a plain icon font would need, since a photo this detailed reads as a blurry
             smudge at the ~17px an icon glyph normally renders at. --}}
        .pos-topbar-btn-icon-img { width: 28px; height: auto; display: block; }
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

        .pos-col-left { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .pos-card {
            background: var(--white); border-radius: var(--radius-md); border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(15,23,42,0.06); padding: 18px 20px;
        }
        /* Inter, not the serif display face — a section header you scan past a dozen times a
           shift (Donor Details, Donation Amount...) reads faster in the same grotesque the
           form fields themselves use than in a decorative face, which is better spent on the
           temple branding up in the topbar instead. */
        .pos-card-title { display: flex; align-items: center; gap: 10px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-weight: 700; font-size: 1.08rem; color: var(--text-primary); margin: 0; letter-spacing: -0.01em; }
        .pos-card-title i { font-size: 1.05rem; color: var(--gold-hover); }
        {{-- Only for a title with no .pos-card-subtitle under it (card 3's "Details" step) —
             without either one, its content sat flush against the title with zero gap. --}}
        .pos-card-title-spaced { margin-bottom: 12px; }
        .pos-card-subtitle { margin: 4px 0 16px; font-size: 0.85rem; color: var(--text-secondary); font-weight: 500; }
        {{-- Numbered steps instead of generic icons for the three cards that make up the
             actual donor->amount->details sequence — a plain icon doesn't communicate "do
             this first", a number does, and it's the single biggest thing that makes a page
             read as a guided flow rather than a form with sections. --}}
        .pos-step-badge {
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            width: 32px; height: 32px; border-radius: 50%; background: var(--maroon); color: #fff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-weight: 800;
            font-size: 1rem;
        }

        .pos-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        @media (min-width: 900px) { .pos-side { position: sticky; top: 96px; } }

        /* Donation Summary — deliberately NOT another plain white card, so the running total
           reads at a glance as the "money" panel rather than just more form. */
        .pos-summary-card {
            background: linear-gradient(135deg, #FFF9ED 0%, #FFF2D0 100%);
            border: 1.5px solid #D9AC4E; border-radius: var(--radius-md); padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(15,23,42,0.07);
        }
        .pos-summary-top-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .pos-summary-label { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: #8A6A1E; font-weight: 800; margin-bottom: 0; }
        .pos-summary-orders-link { display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.55); border: 1px solid #E7C36A; border-radius: 8px; padding: 6px 12px; font-size: 0.76rem; font-weight: 700; color: #8A6A1E; }
        .pos-summary-orders-link:active { background: rgba(255,255,255,0.85); }
        .pos-summary-row { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
        .pos-summary-row .pos-summary-row-label { font-size: 0.95rem; color: var(--text-secondary); font-weight: 600; }
        .pos-summary-row .pos-summary-row-value { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
        .pos-summary-divider { height: 1px; background: rgba(165,107,19,0.25); margin: 14px 0; }
        .pos-summary-total-row .pos-summary-row-label { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); }
        .pos-summary-total-row .pos-summary-row-value { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: clamp(1.9rem, 5vw, 2.5rem); font-weight: 700; color: #A56B13; }
        .pos-summary-name { margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(165,107,19,0.2); font-size: 0.92rem; font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pos-summary-method { margin-top: 4px; font-size: 0.8rem; color: var(--text-secondary); }

        /* Which physical terminal the "EFT Terminal" payment method will actually charge —
           styled like an actual dropdown/select (labeled, chevron on the right) since tapping it
           opens a list of terminals to choose from, sharing one line with Save roughly
           half-and-half, only while that method is selected, rather than a whole extra row above
           the action buttons (an earlier design) or a topbar icon indistinguishable from
           Settings/Account (the design before that). The terminal's name is printed right on the
           control, not hidden behind a tooltip — a clerk needs to see which terminal is live at
           a glance, not discover it on hover. */
        {{-- flex-end, not stretch — the label sits above the box on its own, so the box and
             Save button end up exactly the same height (both min-height: 68px) with their
             bottom edges aligned, rather than Save being stretched taller to match the label's
             extra space above the box. --}}
        .pos-actions-main-row { display: flex; gap: 10px; align-items: flex-end; }
        .pos-actions-main-row .pos-save-btn { flex: 1 1 0; width: auto; }
        .pos-terminal-mini-wrap { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .pos-terminal-mini-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-primary); }
        .pos-terminal-mini-btn {
            flex: 1; min-width: 0; min-height: 68px; padding: 0 12px;
            border-radius: var(--radius-md); border: 1.5px solid var(--gold);
            background: var(--cream); color: var(--text-primary); font-weight: 700; font-size: 0.92rem;
            display: flex; align-items: center; gap: 8px; width: 100%;
        }
        .pos-terminal-mini-btn i.bi-pc-display { font-size: 1.15rem; color: var(--gold-hover); flex-shrink: 0; }
        .pos-terminal-mini-divider { width: 1.5px; align-self: stretch; background: rgba(201,149,46,0.4); flex-shrink: 0; }
        .pos-terminal-mini-btn-name { flex: 1 1 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; text-align: left; }
        .pos-terminal-mini-chevron { flex-shrink: 0; color: var(--text-secondary); font-size: 0.85rem; }
        .pos-terminal-mini-btn:active { background: #F2E4C4; }

        .pos-actions-row { display: flex; flex-direction: column; gap: 10px; }
        .pos-clear-btn { width: 100%; padding: 0 20px; min-height: 54px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--white); color: var(--text-primary); font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .pos-clear-btn:active { background: var(--cream); }

        /* A proper full-width bar (like the header) rather than plain text sitting on the
           page background — bottom of the page reads as a distinct navigation-style strip.
           A 3-column grid (not flex) is deliberate: the left/right slots stay pinned to their
           own column whether or not the center slot has anything in it, so hiding the center
           on a narrow screen can never leave the date/time stranded without its right-alignment
           (a flex:1 center spacer used to do that pushing — disappearing along with the
           content it held, which is exactly what broke it). The two outer tracks are equal
           1fr shares (not auto) specifically so the center track sits on the true page
           center regardless of how lopsided the temple name vs. the date/time text are —
           auto/auto would instead center it in whatever space happens to be left over
           between two differently-sized outer columns, which drifts off-center exactly when
           one side's text is much longer than the other's. The outer tracks' own edges are
           still the footer's true left/right edges either way, so left/right alignment is
           unaffected (and stays correct even once the center is hidden on a narrow screen). */
        .pos-footer-bar { flex-shrink: 0; background: var(--white); border-top: 1px solid var(--border); box-shadow: 0 -2px 10px rgba(15,23,42,0.04); }
        .pos-footer { margin: 0 auto; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 16px; padding: 10px 24px; color: var(--text-secondary); }
        .pos-footer-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .pos-footer-logo { width: 34px; height: 34px; border-radius: 50%; object-fit: contain; border: 1px solid var(--border); background: #fff; flex-shrink: 0; }
        .pos-footer-text { min-width: 0; display: flex; flex-direction: column; line-height: 1.35; }
        .pos-footer-text strong { display: block; color: var(--text-primary); font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pos-footer-text span { display: block; font-size: 0.76rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pos-footer-powered { display: flex; align-items: center; justify-content: center; gap: 8px; min-width: 0; font-size: 0.76rem; white-space: nowrap; }
        .pos-footer-powered img { height: 16px; width: auto; opacity: 0.82; flex-shrink: 0; }
        .pos-footer-powered .version { color: var(--text-secondary); opacity: 0.75; }
        .pos-footer-powered strong { color: var(--text-primary); font-weight: 700; }
        .pos-footer-right { text-align: right; flex-shrink: 0; line-height: 1.35; }
        .pos-footer-date { font-weight: 700; font-size: 0.82rem; color: var(--text-primary); white-space: nowrap; }
        .pos-footer-time { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-weight: 600; font-size: 0.78rem; color: var(--text-secondary); white-space: nowrap; }
        @media (max-width: 700px) { .pos-footer-powered, .pos-footer-text span { display: none; } }

        .pos-field-label { display: block; font-weight: 700; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        .pos-input {
            width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 1.02rem; font-weight: 600;
            color: var(--text-primary); background: var(--white); min-height: 50px;
        }
        .pos-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,149,46,0.15); }
        .pos-input::placeholder, .pos-amount-input-wrap input::placeholder, .pos-field-inline-input::placeholder { color: #9AA7B4; font-weight: 400; opacity: 1; }
        .pos-textarea { min-height: 72px; font-weight: 500; font-size: 0.98rem; resize: vertical; }
        .pos-row { display: grid; grid-template-columns: 1fr; gap: 10px; margin-bottom: 12px; }
        .pos-row:last-child { margin-bottom: 0; }
        .pos-row.two-col { grid-template-columns: 1fr; }
        @media (min-width: 560px) { .pos-row.two-col { grid-template-columns: 1fr 1fr; } }

        /* Donor fields — a leading icon plus a stacked label/placeholder inside one bordered
           box; touch targets stay at/above the ~48px minimum without ballooning past it. */
        .pos-field-box { display: flex; align-items: center; gap: 10px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 6px 14px; min-height: 54px; background: var(--white); }
        .pos-field-box:focus-within { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,149,46,0.15); }
        .pos-field-icon { font-size: 1.1rem; color: var(--text-secondary); flex-shrink: 0; }
        .pos-field-stack { display: flex; flex-direction: column; flex: 1; min-width: 0; }
        .pos-field-inline-label { font-size: 0.74rem; color: var(--text-secondary); font-weight: 700; }
        .pos-field-inline-input { border: none; outline: none; background: transparent; font-size: 1.02rem; font-weight: 600; color: var(--text-primary); padding: 0; width: 100%; }

        /* Shared "currency-prefixed" amount field — used for both the plain free-amount
           input and any per-tier free-amount input, so a donor/operator always sees the
           currency right next to what they're typing. Mono figure face so the typed amount
           matches the preset tiles and summary total it feeds into. */
        .pos-amount-input-wrap { display: flex; align-items: stretch; border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; background: var(--white); }
        .pos-amount-input-wrap:focus-within { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,149,46,0.15); }
        .pos-amount-prefix { display: flex; align-items: center; justify-content: center; padding: 0 14px; background: var(--cream); color: var(--text-secondary); font-weight: 800; font-size: 1.02rem; border-right: 1.5px solid var(--border); flex-shrink: 0; }
        .pos-amount-input-wrap input { border: none; flex: 1; min-width: 0; min-height: 52px; padding: 12px 14px; font-size: 1.15rem; font-weight: 600; color: var(--text-primary); background: transparent; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; }
        .pos-amount-input-wrap input:focus { outline: none; box-shadow: none; }
        .pos-amount-input-stack { display: flex; flex-direction: column; justify-content: center; flex: 1; min-width: 0; }
        .pos-amount-input-stack input { padding: 0 14px 10px; min-height: auto; }
        {{-- Above the field, not below it — a hint reading "Any amount" under the input looked
             like it was describing a value just entered, rather than guiding what to type. --}}
        .pos-amount-hint { padding: 10px 16px 0; font-size: 0.8rem; color: var(--text-secondary); }

        .pos-section-title { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin: 20px 0 10px; }
        .pos-section-title:first-child { margin-top: 0; }
        .pos-section-title i { font-size: 1rem; color: var(--gold-hover); }

        .pos-card-header-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .pos-card-header-row .pos-card-subtitle { margin: 4px 0 0; }

        /* auto-fit rather than a fixed 3 columns — reflows gracefully at any width instead of
           forcing three equal columns that can squeeze text at narrow (phone/POS) sizes. */
        .pos-quick-amounts { display: grid; grid-template-columns: repeat(auto-fit, minmax(84px, 1fr)); gap: 8px; margin-bottom: 14px; }
        .pos-quick-amount-btn, .pos-tier-quick-btn {
            display: flex; align-items: center; justify-content: center;
            background: var(--shade); border: 1px solid var(--shade-border); color: var(--text-primary);
            font-weight: 700; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-variant-numeric: tabular-nums; padding: 8px 6px; min-height: 48px;
            border-radius: var(--radius-sm); font-size: 1rem;
        }
        .pos-quick-amount-btn:active, .pos-tier-quick-btn:active { background: #E7ECF1; }
        .pos-quick-amount-btn.active, .pos-tier-quick-btn.active {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-hover) 100%);
            border-color: var(--gold); color: white; box-shadow: 0 3px 8px rgba(201,149,46,0.35);
        }

        /* Donation-type pills — sit in the card's header row (top-right), each toggling its
           own detail block below. A single-option event (the common case today) shows one
           pill that's always active; multiple options behave as an additive multi-select
           "cart", each with its own detail area revealed only while its pill is active. */
        .pos-tier-pills-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .pos-tier-pill { display: inline-flex; align-items: center; gap: 8px; padding: 0 18px; min-height: 48px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--white); font-weight: 700; font-size: 0.95rem; color: var(--text-primary); cursor: pointer; user-select: none; }
        .pos-tier-pill input[type="checkbox"] { display: none; }
        .pos-tier-pill.active { background: var(--gold); border-color: var(--gold); color: #fff; }

        .pos-tier-detail { display: none; }
        .pos-tier-detail.active { display: block; }
        .pos-tier-fixed-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 4px 0; }
        .pos-tier-fixed-amount { font-weight: 700; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: 1.05rem; color: var(--text-primary); }
        .pos-tier-qty { width: 76px; min-height: 48px; padding: 8px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-size: 1.05rem; font-weight: 700; border: 1.5px solid var(--border); border-radius: var(--radius-sm); text-align: center; }
        /* Same auto-fit reflow as .pos-quick-amounts above, and the same reasoning — this is
           the exact grid that was reported "breaking" at narrow widths under a fixed 3-column
           track. Size/look otherwise comes entirely from the shared .pos-quick-amount-btn,
           .pos-tier-quick-btn rule above. */
        .pos-tier-free-quick-amounts { display: grid; grid-template-columns: repeat(auto-fit, minmax(84px, 1fr)); gap: 8px; width: 100%; margin-bottom: 12px; }
        .pos-tier-total-row { display: none; }

        .pos-method-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .pos-method-btn { position: relative; flex: 1 1 calc(33.33% - 7px); min-width: 96px; padding: 12px 8px; min-height: 92px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--shade); font-weight: 700; font-size: 0.92rem; color: var(--text-primary); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .pos-method-btn.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 4px 10px rgba(201,149,46,0.3); }
        .pos-method-btn.active::after {
            content: ''; position: absolute; left: 50%; bottom: -8px; transform: translateX(-50%);
            width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent;
            border-top: 8px solid var(--gold);
        }
        .pos-method-icon-badge { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--white); border: 1.5px solid var(--shade-border); margin-bottom: 6px; }
        .pos-method-btn.active .pos-method-icon-badge { background: rgba(255,255,255,0.25); border-color: transparent; }
        .pos-method-icon-badge i { font-size: 1.1rem; color: var(--gold-hover); }
        .pos-method-btn.active .pos-method-icon-badge i { color: #fff; }
        /* No paired terminal at all — EFT Terminal isn't a usable option right now. */
        .pos-method-btn.disabled { opacity: 0.45; cursor: not-allowed; }
        .pos-method-btn.disabled.active { border-color: var(--border); background: var(--shade); color: var(--text-primary); box-shadow: none; }
        .pos-method-btn.disabled.active::after { display: none; }

        .pos-save-btn {
            width: 100%; padding: 18px; border-radius: var(--radius-md); border: none;
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

        .pos-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 18px 26px; border-radius: var(--radius-md); font-weight: 700; font-size: 1.1rem; box-shadow: 0 14px 34px rgba(0,0,0,0.2); z-index: 999; display: none; }
        .pos-toast.error { background: var(--error); }

        /* A bottom-corner toast is easy to miss mid-transaction, with both the operator and
           donor's attention on the center of the screen — warnings/errors now interrupt with
           a real popup instead; a plain confirmation (e.g. "Clipboard copied") still just
           uses the quieter corner toast above. */
        .pos-warning-overlay { position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1100; display: none; align-items: center; justify-content: center; padding: 20px; }
        .pos-warning-overlay.active { display: flex; }
        .pos-warning-popup { background: var(--white); border-radius: var(--radius-lg); width: 100%; max-width: 380px; box-shadow: 0 24px 60px rgba(0,0,0,0.35); overflow: hidden; text-align: center; }
        .pos-warning-icon { background: var(--error); color: #fff; font-size: 1.8rem; padding: 20px; }
        .pos-warning-body { padding: 22px 24px 26px; }
        .pos-warning-message { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); margin-bottom: 18px; }
        .pos-warning-ok-btn { width: 100%; padding: 14px; border-radius: var(--radius-sm); border: none; background: var(--maroon); color: #fff; font-weight: 700; font-size: 0.98rem; }
        .pos-warning-ok-btn:active { filter: brightness(0.92); }

        /* Cash/Bank Transfer confirmation — these two methods aren't verified on the spot by
           a terminal, so the clerk and donor both need a clear "it's recorded, a receipt is
           on its way" moment rather than a corner toast. Same popup shell as the warning
           above (just green instead of red), and dismisses itself: a tap anywhere on the
           overlay (OK button included, since the click bubbles to it) or a short timeout. */
        .pos-confirm-overlay { position: fixed; inset: 0; background: rgba(20,30,45,0.6); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); z-index: 1100; display: none; align-items: center; justify-content: center; padding: 20px; cursor: pointer; }
        .pos-confirm-overlay.active { display: flex; }
        .pos-confirm-popup { background: #fff; border-radius: 20px; width: 620px; max-width: 92vw; box-shadow: 0 24px 60px rgba(15,23,42,0.35); overflow: hidden; text-align: center; cursor: default; }
        {{-- The corner highlight is a second background-image, not a ::before — a pseudo-element
             here would paint as its own positioned layer above the plain inline icon/title text
             (positioned content paints after in-flow content, regardless of source order),
             washing over them. Stacked backgrounds have no such risk: a background always
             paints behind its own element's content, full stop. --}}
        .pos-confirm-header {
            min-height: 110px; padding: 20px 28px;
            background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #12b76a 0%, #039855 100%);
            color: #fff; display: flex; align-items: center; justify-content: center; gap: 16px;
        }
        .pos-confirm-header-icon { font-size: 1.7rem; flex-shrink: 0; }
        .pos-confirm-header-divider { width: 1px; align-self: stretch; background: rgba(255,255,255,0.4); flex-shrink: 0; }
        .pos-confirm-header-title { font-weight: 700; font-size: 1.7rem; letter-spacing: 0.3px; }
        .pos-confirm-body { padding: 36px 32px 32px; }
        .pos-confirm-icon-wrap { position: relative; width: 90px; height: 90px; margin: 0 auto 26px; display: flex; align-items: center; justify-content: center; }
        .pos-confirm-icon-halo { position: absolute; inset: 0; border-radius: 50%; background: rgba(7,148,85,0.12); }
        .pos-confirm-icon-circle { position: relative; width: 64px; height: 64px; border-radius: 50%; background: #12b76a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; box-shadow: 0 6px 16px rgba(18,183,106,0.35); }
        .pos-confirm-accent { position: absolute; width: 10px; height: 2px; border-radius: 1px; background: #12b76a; opacity: 0.6; }
        .pos-confirm-accent.accent-tl { top: 6px; left: 6px; transform: rotate(45deg); }
        .pos-confirm-accent.accent-tr { top: 6px; right: 6px; transform: rotate(-45deg); }
        .pos-confirm-accent.accent-bl { bottom: 6px; left: 6px; transform: rotate(-45deg); }
        .pos-confirm-accent.accent-br { bottom: 6px; right: 6px; transform: rotate(45deg); }
        .pos-confirm-message { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-weight: 600; font-size: 21px; line-height: 1.5; color: #142B45; margin: 0 auto 28px; max-width: 460px; }
        .pos-confirm-message .pos-confirm-amount { color: #079455; font-weight: 700; }
        .pos-confirm-message .pos-confirm-email { color: #1570EF; font-weight: 600; overflow-wrap: break-word; }
        .pos-confirm-ok-btn {
            width: 100%; min-height: 60px; padding: 14px; border-radius: 12px; border: none; cursor: pointer;
            background: linear-gradient(135deg, #1570EF, #0560D8); color: #fff;
            font-weight: 700; font-size: 18px; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .pos-confirm-ok-btn:hover { filter: brightness(1.05); }
        .pos-confirm-ok-btn:active { filter: brightness(0.92); transform: scale(0.99); }
        .pos-confirm-ok-btn:focus-visible { outline: 3px solid #1570EF; outline-offset: 2px; }
        @media (max-width: 480px) {
            .pos-confirm-header { min-height: 90px; }
            .pos-confirm-header-title { font-size: 1.3rem; }
            .pos-confirm-message { font-size: 18px; }
            .pos-confirm-ok-btn { min-height: 54px; }
        }

        /* ---------- EFT terminal status popup — center-screen, mirrors what's on the
           physical/virtual PIN pad while a card payment is in progress ---------- */
        .eft-modal-overlay {
            position: fixed; inset: 0; background: rgba(31,42,55,0.55); z-index: 1000;
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .eft-modal-overlay.active { display: flex; }
        .eft-modal {
            background: var(--white); border-radius: var(--radius-lg); width: 100%; max-width: 460px;
            max-height: calc(100vh - 40px); box-shadow: 0 24px 60px rgba(0,0,0,0.35);
            overflow: hidden; text-align: center; display: flex; flex-direction: column;
            position: relative;
        }
        {{-- A thin light chases around the modal's own border while a payment is in flight —
             only during .pending, via :has(), so it stops the instant success/error/override
             land. Built from a conic-gradient ring masked down to just the border using the
             padding-box/content-box dual-mask trick. The bright segment's angle is animated
             directly via an @property custom property — NOT by rotating the masked element
             itself, which was the earlier bug: rotating a rectangular ring as a rigid shape
             spins the rectangle away from the modal's own bounds, showing as a diagonal bar
             sweeping across the card instead of a light following its fixed border path. --}}
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
        {{-- Header colour reacts to #eftModalStatusBox's own state class (already toggled by
             the existing, untouched polling JS) via :has() — the app's own maroon brand while
             the transaction is still in progress, green once approved, red once declined. Zero
             JS changes needed. --}}
        .eft-modal-header {
            background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #a70918 0%, #d95f6b 100%);
            color: white; padding: 18px 20px; font-weight: 800; letter-spacing: 0.06em;
            font-size: 0.95rem; text-transform: uppercase; flex-shrink: 0;
        }
        .eft-modal:has(.eft-modal-status-box.success) .eft-modal-header {
            background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #12b76a 0%, #039855 100%);
        }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header {
            background: radial-gradient(ellipse at top right, rgba(255,255,255,0.14), transparent 60%), linear-gradient(135deg, #F04438 0%, #D92D20 55%, #B42318 100%);
        }
        {{-- Same :has() trick swaps the header's own title/icon text for the declined state —
             both variants are always in the DOM, CSS just shows one or the other. --}}
        .eft-modal-header-icon-error, .eft-modal-header-title-error { display: none; }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-icon-default,
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-title-default { display: none; }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-icon-error,
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-header-title-error { display: inline-block; }
        {{-- min-height:0 is the flexbox gotcha fix — without it a flex child never actually
             shrinks to scroll, it just overflows its parent instead, which is exactly how a
             long Action Framework response (mx51's "13.37" full-element test case among them)
             used to push the whole modal past the viewport instead of scrolling internally. --}}
        .eft-modal-body { padding: 22px 22px 20px; overflow-y: auto; min-height: 0; }
        {{-- Large status icon above the amount — matches the Donation Recorded popup's own
             halo-and-checkmark treatment, shown once the transaction is actually approved (not
             during the plain "Starting…" pending state, which has nothing to illustrate yet).
             Declined/cancelled deliberately does NOT get this big badge — mx51's own reference
             UI for that case is a plain inline mark next to the message, not a large circular
             icon, and matching that reads as calmer and more in line with their certified look. --}}
        .eft-modal-body-icon-wrap { display: none; position: relative; width: 84px; height: 84px; margin: 0 auto 14px; align-items: center; justify-content: center; }
        .eft-modal:has(.eft-modal-status-box.success) .eft-modal-body-icon-wrap { display: flex; }
        .eft-modal-body-icon-halo { position: absolute; inset: 0; border-radius: 50%; background: rgba(7,148,85,0.12); }
        .eft-modal-body-icon-circle { position: relative; width: 60px; height: 60px; border-radius: 50%; background: #12b76a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.7rem; box-shadow: 0 6px 16px rgba(18,183,106,0.35); }
        .eft-modal-body-icon-success { display: block; }
        .eft-modal-body-icon-accent { position: absolute; width: 9px; height: 2px; border-radius: 1px; background: #12b76a; opacity: 0.6; }
        .eft-modal-body-icon-accent.a-tl { top: 4px; left: 4px; transform: rotate(45deg); }
        .eft-modal-body-icon-accent.a-tr { top: 4px; right: 4px; transform: rotate(-45deg); }
        .eft-modal-body-icon-accent.a-bl { bottom: 4px; left: 4px; transform: rotate(-45deg); }
        .eft-modal-body-icon-accent.a-br { bottom: 4px; right: 4px; transform: rotate(45deg); }
        .eft-modal-amount { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; font-size: 2.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 14px; }
        .eft-modal-status-box {
            background: var(--cream); border: 2px solid var(--border); border-radius: var(--radius-md);
            padding: 16px; min-height: 72px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 6px; margin-bottom: 16px;
            transition: background-color .15s, border-color .15s;
        }
        {{-- Tinted per state (not just the icon/text) so approve/decline reads at a glance
             instead of blending into the same neutral box the whole flow sits in. --}}
        .eft-modal-status-box.success { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.4); }
        {{-- Declined specifically switches to a horizontal icon+divider+message row (per the
             supplied design) rather than the stacked layout pending/success keep — .eft-modal-
             status-text is `display:contents` by default so wrapping statusLine1/2 in it doesn't
             change anything for those two states; only .error turns it into its own column. --}}
        .eft-modal-status-box.error { background: #FEF3F2; border-color: #FEE4E2; flex-direction: row; justify-content: flex-start; text-align: left; }
        {{-- A static card icon with three pulsing dots beneath it, instead of the earlier
             expanding "waving circle" rings — a payment-themed but calmer processing cue. --}}
        .eft-modal-payment-anim { display: none; flex-direction: column; align-items: center; gap: 7px; margin: 0 auto 4px; }
        .eft-modal-status-box.pending .eft-modal-payment-anim { display: flex; }
        .eft-modal-payment-anim i { font-size: 1.5rem; color: var(--gold-hover); }
        .eft-modal-payment-anim-dots { display: flex; gap: 5px; }
        .eft-modal-payment-anim-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--gold-hover); opacity: 0.3; animation: eftDotPulse 1.2s ease-in-out infinite; }
        .eft-modal-payment-anim-dot:nth-child(2) { animation-delay: 0.2s; }
        .eft-modal-payment-anim-dot:nth-child(3) { animation-delay: 0.4s; }
        @keyframes eftDotPulse { 0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); } 40% { opacity: 1; transform: scale(1.2); } }
        .eft-modal-status-icon { font-size: 2rem; margin-bottom: 2px; display: none; }
        .eft-modal-status-box.success .eft-modal-status-icon.icon-success { display: block; color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-icon.icon-error {
            display: flex; align-items: center; justify-content: center; margin: 0; flex-shrink: 0;
            width: 40px; height: 40px; border-radius: 50%; background: #F04438; color: #fff; font-size: 1.1rem;
        }
        .eft-modal-status-divider { display: none; width: 2px; align-self: stretch; background: #FECDCA; margin: 0 14px; flex-shrink: 0; }
        .eft-modal-status-box.error .eft-modal-status-divider { display: block; }
        .eft-modal-status-text { display: contents; }
        .eft-modal-status-box.error .eft-modal-status-text { display: flex; flex-direction: column; gap: 2px; }
        .eft-modal-status-line { font-weight: 700; font-size: 1.05rem; color: var(--text-primary); letter-spacing: 0.02em; }
        .eft-modal-status-box.success .eft-modal-status-line { color: var(--success); }
        .eft-modal-status-box.error .eft-modal-status-line { text-align: left; color: #D92D20; }
        .eft-modal-print-notice { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 0.78rem; font-weight: 600; color: var(--success); margin: 8px 0 0; }
        {{-- Always the same wording, deliberately separate from statusLine1/2 above (which keep
             showing mx51's own real decline reason, untouched) — a fixed, generic next-step hint
             rather than a second attempt at explaining what went wrong. Suppressed specifically
             for a CANCELLED result (status-cancelled, set by finishDeclined()) — "check your
             card details" doesn't apply to a terminal timeout/cancellation, only to an actual
             card decline, and mx51's own reference UI for a cancellation shows no such hint. --}}
        .eft-modal-decline-hint { display: none; color: #667085; font-size: 0.92rem; line-height: 1.5; margin: 14px 0 0; }
        .eft-modal:has(.eft-modal-status-box.error):not(:has(.eft-modal-status-box.status-cancelled)) .eft-modal-decline-hint { display: block; }
        .eft-modal-cancel-btn {
            width: 100%; padding: 14px; border-radius: var(--radius-sm); border: 2px solid var(--border);
            background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem;
        }
        .eft-modal-cancel-btn:active { background: var(--cream); }
        {{-- Visibly greyed out the instant it's clicked (cancelBtn.disabled = true happens
             synchronously in the click handler) — without this it kept its normal look, so
             nothing on screen confirmed the tap had actually registered. --}}
        .eft-modal-cancel-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        {{-- Once declined, this same button (still "Cancel Payment" — nothing left to cancel,
             so it's the dismiss action) becomes the blue primary treatment the design calls for,
             matching the OK button on Donation Recorded. --}}
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-cancel-btn {
            background: linear-gradient(135deg, #1570EF, #0560D8); color: #fff; border: none;
        }
        .eft-modal:has(.eft-modal-status-box.error) .eft-modal-cancel-btn:active { filter: brightness(0.92); }

        /* This station's EFT terminal picker — same modal box styling as the EFT status
           popup, since it's the same visual family. Only paired terminals ever appear here
           (see renderTerminalModalList()), so there's nothing to pair/repair from this list —
           that lives entirely on the EFT Terminal Settings page now. Each row is a big
           button-style option, not a plain checkbox row — the whole card is clickable, and the
           currently-selected one is unmistakably highlighted (border/fill colour + a filled
           check + a "Selected" tag), not just a small tick easy to miss at a glance. */
        .terminal-picker-row { width: 100%; text-align: left; padding: 12px 16px; border-radius: var(--radius-sm); border: 2px solid var(--border); background: var(--white); margin-bottom: 10px; cursor: pointer; transition: border-color .12s, background-color .12s; }
        .terminal-picker-row:hover { border-color: var(--maroon); }
        .terminal-picker-row.selected { border-color: var(--maroon); background: var(--cream); }
        .terminal-picker-select { display: flex; align-items: center; gap: 10px; font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
        .terminal-picker-check { width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: transparent; font-size: 0.8rem; background: var(--white); }
        .terminal-picker-row.selected .terminal-picker-check { border-color: var(--maroon); background: var(--maroon); color: #fff; }
        .terminal-picker-sci-logo { width: 18px; height: 18px; border-radius: 4px; object-fit: cover; flex-shrink: 0; }
        .terminal-picker-details { margin-top: 4px; padding-left: 32px; font-size: 0.78rem; color: var(--text-secondary); }
        .terminal-picker-selected-tag { margin-left: auto; font-size: 0.68rem; font-weight: 800; color: var(--maroon); text-transform: uppercase; letter-spacing: 0.04em; }


        /* Terminal soft-key buttons (OK/Yes/No/Authorise) — only ever shown when Linkly's own
           display notification currently flags that key as available (data.controls), never
           guessed at or shown speculatively. */
        .eft-modal-keys { display: none; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .eft-modal-keys.active { display: flex; }
        .eft-modal-key-btn {
            flex: 1 1 auto; min-width: 90px; padding: 13px 10px; border-radius: var(--radius-sm); border: 2px solid transparent;
            font-weight: 700; font-size: 0.95rem; color: #fff;
        }
        .eft-modal-key-btn:active { filter: brightness(0.92); }
        .eft-modal-key-btn.key-ok, .eft-modal-key-btn.key-yes, .eft-modal-key-btn.key-authorise { background: var(--success); }
        .eft-modal-key-btn.key-no { background: var(--error); }

        /* CBA Smart Terminal (mx51 SCI) — dynamic Action Framework elements, rendered from
           whatever pos_instructions the terminal sends for this step (text/button/input/
           image), never a fixed set like the Linkly soft-keys above. */
        .sci-af-row { display: flex; gap: 8px 12px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
        .sci-af-row:last-child { margin-bottom: 0; }
        {{-- No forced width:100% — mx51 groups related text elements into the same
             horizontal_layout row expecting them to sit side by side (e.g. "Label 1: Value 1"
             next to "Label 2: Value 2"); forcing each onto its own line defeated that grouping
             entirely. A text element alone in its own row still reads fine at its natural
             width. --}}
        .sci-af-text { font-size: 0.88rem; color: var(--text-secondary); text-align: left; }
        {{-- Outlined, same blue accent as the Donation Recorded popup's OK button and this
             modal's own header highlight — one shared payment-flow palette, not a block of
             solid colour identical to the header above it. --}}
        .sci-af-btn { flex: 1 1 auto; min-width: 90px; padding: 10px 10px; border-radius: var(--radius-sm); border: 2px solid #1570EF; font-weight: 700; font-size: 0.88rem; color: #1570EF; background: var(--white); }
        .sci-af-btn:active { background: #EFF6FF; }
        {{-- flex-wrap lets a long label (mx51's own test fields can be verbose, e.g.
             "Input 1 (input_name_1_...)") drop to its own line above the input instead of
             forcing the row wider than the modal — that horizontal overflow was the other
             half of the "doesn't fit" complaint, alongside the vertical one above. --}}
        .sci-af-input-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; flex: 1 1 100%; }
        .sci-af-input-label { font-size: 0.8rem; color: var(--text-secondary); }
        .sci-af-input { flex: 1 1 160px; min-width: 120px; padding: 9px 12px; border-radius: var(--radius-sm); border: 2px solid var(--border); font-size: 0.88rem; }
        {{-- Capped height — mx51's own supplied branding image (SCITX06's signature step
             among others) is a real `type: 'image'` element rendered like any other, not a
             locally bundled logo; without a height cap it could render at an arbitrarily large
             natural size and dominate the whole modal. --}}
        .sci-af-image { max-width: 100%; max-height: 64px; display: block; margin: 6px auto; border-radius: var(--radius-sm); }
        .sci-af-details { text-align: left; font-size: 0.78rem; color: var(--text-secondary); line-height: 1.45; }
        #eftModalActionFramework { margin-bottom: 10px; }

        /* Manual recovery override — CBA SCI has no cancel API, so once a transaction has
           actually started, "Cancel" is replaced by an honest "confirm the real outcome"
           prompt instead of pretending the payment can be stopped mid-flight. Deliberately
           plain — an amber "unknown" icon, a question, Yes/No — not styled as a decline (it
           isn't one: the whole point is that the real outcome is still unknown). */
        .eft-modal-override-icon { width: 52px; height: 52px; border-radius: 50%; background: #F2A900; color: #fff; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; margin: 4px auto 14px; }
        .eft-modal-override-title { font-weight: 800; font-size: 1.2rem; color: var(--text-primary); margin-bottom: 8px; }
        .eft-modal-override-subtitle { font-size: 0.92rem; color: var(--text-secondary); margin-bottom: 18px; }
        .eft-modal-override-actions { display: flex; gap: 10px; margin-bottom: 10px; justify-content: center; }
        .eft-override-btn { flex: 0 1 120px; padding: 12px 10px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--white); font-weight: 700; font-size: 0.95rem; color: var(--text-primary); }
        .eft-override-btn:active { background: var(--cream); }
        .eft-override-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .eft-modal-override-saving { font-weight: 700; font-size: 1.05rem; color: var(--text-secondary); margin: 10px 0; }
        {{-- Tiny and muted, not part of the status box — a quiet "still watching" caption, not
             a claim from the terminal itself. Hidden once the override takes over, same as the
             status box it used to live inside. --}}
        .eft-modal-countdown { font-size: 0.72rem; color: var(--text-secondary); margin: 10px 0 0; text-align: center; }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-countdown { display: none; }
        {{-- The override is a genuinely different moment from a decline (mx51's own prompt
             literally asks whether it went through — the outcome is unknown, not negative), so
             none of the "Payment Declined" branding (header text/icon swap, big X, decline hint)
             applies here even though the status box also uses the 'error' tint for both. These
             rules win over the .error-driven ones above whenever the override is visible. --}}
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header {
            background: radial-gradient(ellipse at top right, rgba(255,255,255,0.18), transparent 60%), linear-gradient(135deg, #a70918 0%, #d95f6b 100%);
        }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-title-error,
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-icon-error { display: none; }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-title-default,
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-header-icon-default { display: inline-block; }
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-body-icon-wrap,
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-status-box,
        .eft-modal:has(.eft-modal-override:not([hidden])) .eft-modal-decline-hint { display: none; }

        @media (max-width: 600px) {
            .pos-method-btn { flex: 1 1 calc(50% - 5px); }
        }
    </style>
</head>
<body>
    @include('partials.test-banner')
    <header class="pos-topbar">
        <div class="pos-topbar-brand">
            <img src="{{ $temple['admin_logo_icon'] ?? $temple['logo'] ?? '' }}" alt="" class="pos-topbar-logo">
            <div class="pos-topbar-brand-text">
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
            @if($canReturnToConsole)
            <a href="{{ route('admin.events.console', $event->event_id) }}" class="pos-topbar-btn" title="Back to console"><i class="bi bi-gear-fill"></i></a>
            @endif
            <!-- Always visible, regardless of whether a terminal is currently paired — the
                 Switch button near Pay only ever shows up while "EFT Terminal" is the selected
                 payment method (and that method stays disabled with nothing paired to pick), so
                 it's not a reliable way in. This is the one entry point to terminal
                 settings/pairing a pos-level user can always reach — see EftTerminalAccess's own
                 docblock on why this tier is trusted to pair here. The gold dot flags that setup
                 still needs attention (nothing paired yet); it clears once a terminal is paired,
                 but the button itself never disappears. -->
            <button type="button" class="pos-topbar-btn" id="posEftSettingsTopbarBtn" title="EFT Terminal Settings"><img src="{{ asset('images/eft_terminal_icon.png') }}" alt="" class="pos-topbar-btn-icon-img"></button>
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
        </div>
    </header>

    <div class="pos-main">
        <div class="pos-grid">
            <div class="pos-col-left">
                <div class="pos-card">
                    <div class="pos-card-title"><span class="pos-step-badge">1</span>Donor Details</div>
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
                            <div class="pos-card-title"><span class="pos-step-badge">2</span>Donation Amount</div>
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
                                <span class="pos-amount-hint">Any amount</span>
                                <input type="text" inputmode="decimal" id="posAmount" placeholder="Enter amount">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pos-card">
                    <div class="pos-card-title pos-card-title-spaced"><span class="pos-step-badge">3</span>Details (optional)</div>
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
                    <div class="pos-card-title"><span class="pos-step-badge">4</span>Payment Method</div>
                    <div class="pos-card-subtitle">Select how the donor would like to pay</div>
                    <div class="pos-method-row" id="posMethodRow"></div>
                </div>

                <div class="pos-actions-row">
                    <div class="pos-actions-main-row">
                        {{-- Only relevant while "EFT Terminal" is the selected method — see
                             updatePosTerminalStatus() in the script below, which shows/hides
                             this and keeps the name in sync with the picker. --}}
                        <div class="pos-terminal-mini-wrap" id="posTerminalMiniWrap" hidden>
                            <span class="pos-terminal-mini-label">EFT Terminal</span>
                            <button type="button" class="pos-terminal-mini-btn" id="posTerminalSwitchBtn" title="Tap to switch terminal">
                                <i class="bi bi-pc-display"></i>
                                <span class="pos-terminal-mini-divider"></span>
                                <span class="pos-terminal-mini-btn-name" id="posTerminalMiniName">—</span>
                                <i class="bi bi-chevron-down pos-terminal-mini-chevron"></i>
                            </button>
                        </div>
                        <button type="button" class="pos-save-btn" id="posSaveBtn"><i class="bi bi-check-circle-fill me-2"></i>Save Donation</button>
                    </div>
                    <button type="button" class="pos-clear-btn" id="posClearBtn" title="Clear form"><i class="bi bi-arrow-counterclockwise"></i>Clear Form</button>
                </div>
            </div>
        </div>

    </div>

    <footer class="pos-footer-bar">
        <div class="pos-footer">
            <div class="pos-footer-left">
                <img src="{{ $temple['admin_logo_icon'] ?? $temple['logo'] ?? '' }}" alt="" class="pos-footer-logo">
                <div class="pos-footer-text">
                    <strong>&copy; {{ date('Y') }} {{ $temple['legal_name'] ?? $temple['name'] ?? '' }}</strong>
                    <span>{{ $temple['name'] ?? '' }}{{ !empty($temple['subtitle']) ? ', ' . $temple['subtitle'] : '' }}</span>
                </div>
            </div>
            <div class="pos-footer-powered">
                <span class="version">Powered by</span>
                <img src="{{ asset('images/SievesPos_simple_logo.png') }}" alt="SievesPOS">
                <span class="version">v{{ config('sievespos.version') }} · Sievesvision</span>
            </div>
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

    <div class="pos-warning-overlay" id="posWarningOverlay">
        <div class="pos-warning-popup">
            <div class="pos-warning-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="pos-warning-body">
                <div class="pos-warning-message" id="posWarningMessage"></div>
                <button type="button" class="pos-warning-ok-btn" id="posWarningOkBtn">OK</button>
            </div>
        </div>
    </div>

    <div class="pos-confirm-overlay" id="posConfirmOverlay">
        <div class="pos-confirm-popup" role="dialog" aria-modal="true" aria-labelledby="posConfirmTitle">
            <div class="pos-confirm-header">
                <i class="bi bi-heart-fill pos-confirm-header-icon"></i>
                <span class="pos-confirm-header-divider"></span>
                <span class="pos-confirm-header-title" id="posConfirmTitle">DONATION RECORDED</span>
            </div>
            <div class="pos-confirm-body">
                <div class="pos-confirm-icon-wrap">
                    <span class="pos-confirm-icon-halo"></span>
                    <span class="pos-confirm-accent accent-tl"></span>
                    <span class="pos-confirm-accent accent-tr"></span>
                    <span class="pos-confirm-accent accent-bl"></span>
                    <span class="pos-confirm-accent accent-br"></span>
                    <span class="pos-confirm-icon-circle"><i class="bi bi-check-lg"></i></span>
                </div>
                <div class="pos-confirm-message" id="posConfirmMessage"></div>
                <button type="button" class="pos-confirm-ok-btn" id="posConfirmOkBtn"><i class="bi bi-check-lg"></i>OK</button>
            </div>
        </div>
    </div>

    <!-- Shown only if a previous EFT Terminal attempt was left unresolved by a refresh/
         crash — see the DOMContentLoaded handler and startOrResumeEftPurchase() below.
         Hidden by default; never auto-triggers a new charge on its own. -->
    <div id="eftResumeBanner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:2000; background:#7a1f1f; color:#fff; padding:12px 18px; align-items:center; gap:14px; flex-wrap:wrap; justify-content:center;">
        <span id="eftResumeBannerText"></span>
        <button type="button" id="eftResumeBannerBtn" style="background:#fff; color:#7a1f1f; border:none; border-radius:8px; padding:6px 16px; font-weight:700;">Resume Checking</button>
        <button type="button" id="eftResumeBannerDismissBtn" style="background:transparent; color:#fff; border:1px solid #fff; border-radius:8px; padding:6px 16px;">Dismiss</button>
    </div>

    <div class="eft-modal-overlay" id="eftModalOverlay">
        <div class="eft-modal" role="dialog" aria-modal="true" aria-labelledby="eftModalHeaderTitle">
            <div class="eft-modal-header" id="eftModalHeaderTitle">
                <i class="bi bi-credit-card-2-front-fill me-2 eft-modal-header-icon-default"></i>
                <i class="bi bi-credit-card-2-front-fill me-2 eft-modal-header-icon-error"></i>
                <span class="eft-modal-header-title-default">Card Payment</span>
                <span class="eft-modal-header-title-error">Payment Declined</span>
            </div>
            <div class="eft-modal-body">
                <div class="eft-modal-body-icon-wrap">
                    <span class="eft-modal-body-icon-halo"></span>
                    <span class="eft-modal-body-icon-accent a-tl"></span>
                    <span class="eft-modal-body-icon-accent a-tr"></span>
                    <span class="eft-modal-body-icon-accent a-bl"></span>
                    <span class="eft-modal-body-icon-accent a-br"></span>
                    <span class="eft-modal-body-icon-circle">
                        <i class="bi bi-check-lg eft-modal-body-icon-success"></i>
                    </span>
                </div>
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
                <p class="eft-modal-print-notice" id="eftModalPrintNotice" hidden><i class="bi bi-printer-fill"></i> Merchant copy printed automatically</p>
                <p class="eft-modal-decline-hint">Please check your card details and try again, or use a different payment method.</p>
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

    <!-- This station's EFT terminal picker — which registered terminal is plugged in HERE,
         saved per-browser so two stations can each run their own concurrent POS. -->
    <div class="eft-modal-overlay" id="terminalModalOverlay">
        <div class="eft-modal">
            <div class="eft-modal-header"><i class="bi bi-credit-card-2-front-fill me-2"></i>This Station's EFT Terminal</div>
            <div class="eft-modal-body">
                <div id="terminalModalList"></div>
                <a href="#" onclick="event.preventDefault(); openEftTerminalSettingsModal();" class="d-block small mb-3"><i class="bi bi-gear me-1"></i>Open EFT Terminal Settings (pair or add a terminal)</a>
                <button type="button" class="eft-modal-cancel-btn" id="terminalModalCloseBtn">Close</button>
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
        // Admin-configurable (EFT Terminal Settings) — see App\Services\EftTransactionLimits.
        // Only applies to the EFT Terminal method; cash/bank/etc. still just need > 0.
        const EFT_MINIMUM_AMOUNT = @json($eftMinimumAmount);
        // Server-authoritative Power Fail recovery data (see PosDonationController::show())
        // — survives the browser tab itself being gone, unlike sessionStorage below.
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
        // sessionStorage only, deliberately — scoped to this one tab for exactly as long as
        // it stays open, which is what "selecting a terminal changes this event's default for
        // this browser session" means in practice: a genuinely new session (new tab, new
        // browser, or this one closed and reopened) has nothing saved, and falls straight back
        // to the registry's own default terminal below. It's also what keeps two POS tabs on
        // ONE computer genuinely independent (e.g. two virtual PIN pads for testing) — nothing
        // here is shared across tabs or persisted against the operator's account any more.
        const TERMINAL_SESSION_KEY = 'eventPosEftTerminalId_tab';
        function loadSelectedTerminalId() {
            let saved = null;
            try { saved = sessionStorage.getItem(TERMINAL_SESSION_KEY); } catch (e) {}
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
        // Keeps the terminal-switch icon's tooltip in sync with whichever terminal is actually
        // selected — printed right on the button (truncated with an ellipsis if it's long; the
        // full name is still in the title tooltip and in the picker this button opens). Whether
        // it shows AT ALL depends on "EFT Terminal" actually being the selected payment method —
        // see updatePosTerminalStatus() below, defined once `selectedMethod` exists further down.
        function updateTerminalStatusName() {
            const label = currentTerminalLabel();
            const nameEl = document.getElementById('posTerminalMiniName');
            if (nameEl) { nameEl.textContent = label; }
            const btn = document.getElementById('posTerminalSwitchBtn');
            if (btn) { btn.title = 'Current terminal: ' + label + ' — tap to switch'; }
        }
        const SCI_LOGO_URL = @json(asset('images/sci-logo.jpg'));

        // Only ever lists PAIRED terminals — an unpaired one can't take a payment, and pairing/
        // repairing one is now exclusively done from the EFT Terminal Settings page (see the
        // "Open EFT Terminal Settings" link below the list), not from this picker.
        function renderTerminalModalList() {
            const list = document.getElementById('terminalModalList');
            if (!list) { return; }
            list.innerHTML = '';
            const pairedTerminals = EFT_TERMINALS.filter(function (t) { return t.paired; });
            if (!pairedTerminals.length) {
                list.innerHTML = '<p class="text-muted small mb-0">No paired terminals yet — use the link below to pair one.</p>';
                return;
            }
            pairedTerminals.forEach(function (t) {
                const card = document.createElement('div');
                const isSelected = String(t.id) === String(selectedTerminalId);
                card.className = 'terminal-picker-row' + (isSelected ? ' selected' : '');
                card.setAttribute('role', 'button');
                card.setAttribute('tabindex', '0');

                const top = document.createElement('div');
                top.className = 'terminal-picker-select';
                const providerMark = t.provider === 'cba_sci'
                    ? '<img src="' + SCI_LOGO_URL + '" alt="SCI" class="terminal-picker-sci-logo">'
                    : '<span class="badge-pill badge-provider">LINKLY CLOUD</span>';
                top.innerHTML = '<span class="terminal-picker-check">&#10003;</span>' +
                    providerMark +
                    '<span>' + escapeHtmlPos(t.label) + (t.is_default ? ' <span class="text-muted small">· default</span>' : '') + '</span>' +
                    (isSelected ? '<span class="terminal-picker-selected-tag">Selected</span>' : '');
                card.appendChild(top);

                if (t.provider === 'cba_sci' && t.sci_pairing_id) {
                    const details = document.createElement('div');
                    details.className = 'terminal-picker-details';
                    details.textContent = 'Pairing ID: ' + t.sci_pairing_id;
                    card.appendChild(details);
                }

                function selectThisTerminal() {
                    selectedTerminalId = String(t.id);
                    saveSelectedTerminalId(selectedTerminalId);
                    updateTerminalStatusName();
                    document.getElementById('terminalModalOverlay').classList.remove('active');
                }
                card.addEventListener('click', selectThisTerminal);
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectThisTerminal(); }
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
            fetch(CBA_SCI_PICKER_REFRESH_URL + '?event_id=' + encodeURIComponent(EVENT_ID), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success && Array.isArray(data.terminals)) {
                        EFT_TERMINALS = data.terminals;
                    }
                    renderTerminalModalList();
                    updateTerminalStatusName();
                    updateEftMethodAvailability();
                })
                .catch(function () {
                    // Still show whatever was last known rather than leaving the modal stuck
                    // on the loading message if the live check itself can't be reached.
                    renderTerminalModalList();
                });
        }
        const posTerminalSwitchBtn = document.getElementById('posTerminalSwitchBtn');
        if (posTerminalSwitchBtn) {
            // Shown immediately on open (own loading state) rather than blocking the click.
            posTerminalSwitchBtn.addEventListener('click', function () {
                document.getElementById('terminalModalOverlay').classList.add('active');
                refreshTerminalPicker();
            });
        }
        document.getElementById('terminalModalCloseBtn').addEventListener('click', function () {
            document.getElementById('terminalModalOverlay').classList.remove('active');
        });

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

        // Payment method — big buttons instead of a dropdown.
        const methodRow = document.getElementById('posMethodRow');
        let selectedMethod = null;
        const methodIcons = { Cash: 'bi-cash-coin', UPI: 'bi-phone-fill', 'Bank Transfer': 'bi-bank2', Cheque: 'bi-postcard-fill', 'EFT Terminal': 'bi-credit-card-2-front-fill', Stripe: 'bi-credit-card-fill' };
        const posMethodList = ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash'];
        // EFT Terminal is the fastest, most reconciliation-friendly method when it's on offer
        // at all — default to it rather than whichever method happens to sort first, so a
        // clerk doesn't have to remember to switch off Cash every single sale. But it's only
        // genuinely "on offer" if some terminal is actually paired right now — EFT_TERMINALS is
        // paired-only on first load (see PosDonationController::show()) and re-filtered the
        // same way after every live picker refresh (see renderTerminalModalList()), so this one
        // check covers both.
        function eftTerminalAvailable() { return EFT_TERMINALS.some(function (t) { return t.paired; }); }
        const posDefaultMethod = (posMethodList.includes('EFT Terminal') && eftTerminalAvailable())
            ? 'EFT Terminal'
            : posMethodList.find(function (m) { return m !== 'EFT Terminal' || eftTerminalAvailable(); }) || posMethodList[0];
        posMethodList.forEach(function (m) {
            const btn = document.createElement('button');
            btn.type = 'button';
            const disabled = m === 'EFT Terminal' && !eftTerminalAvailable();
            btn.className = 'pos-method-btn' + (m === posDefaultMethod ? ' active' : '') + (disabled ? ' disabled' : '');
            btn.innerHTML = '<span class="pos-method-icon-badge"><i class="bi ' + (methodIcons[m] || 'bi-wallet2') + '"></i></span>' + m;
            btn.dataset.method = m;
            if (disabled) { btn.title = 'No EFT terminal is currently paired.'; }
            btn.addEventListener('click', function () {
                if (btn.classList.contains('disabled')) { return; }
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                selectedMethod = m;
                updatePosSummary();
                updatePosTerminalStatus();
            });
            methodRow.appendChild(btn);
            if (m === posDefaultMethod) { selectedMethod = m; }
        });

        // Only relevant while "EFT Terminal" is the selected method — a compact icon sitting
        // right next to Save, not a whole extra row above the action buttons, and not a topbar
        // icon easily mistaken for Settings/Account either (both previous designs).
        const posTerminalStatus = document.getElementById('posTerminalMiniWrap');
        function updatePosTerminalStatus() {
            if (!posTerminalStatus) { return; }
            posTerminalStatus.hidden = selectedMethod !== 'EFT Terminal';
            updateTerminalStatusName();
        }
        updatePosTerminalStatus();

        // Always-visible entry point to terminal pairing/settings — unlike "Switch" above the
        // Pay button, which only ever appears while "EFT Terminal" is selected (and that method
        // stays disabled with nothing paired to pick), this one works with zero terminals paired.
        const posEftSettingsTopbarBtn = document.getElementById('posEftSettingsTopbarBtn');
        function updateEftSettingsTopbarBtn() {
            if (!posEftSettingsTopbarBtn) { return; }
            posEftSettingsTopbarBtn.classList.toggle('pos-topbar-btn-setup', !eftTerminalAvailable());
        }
        updateEftSettingsTopbarBtn();
        if (posEftSettingsTopbarBtn) {
            // Straight to pairing/settings itself — the terminal picker (Switch, above the Pay
            // button) is the separate, optional "which terminal does this station use" list,
            // not a required stop on the way to Settings.
            posEftSettingsTopbarBtn.addEventListener('click', function () { openEftTerminalSettingsModal(); });
        }

        // Re-run whenever the picker's own live refresh updates EFT_TERMINALS (e.g. the
        // previously-paired terminal was just unpaired/removed elsewhere) — switches away from
        // EFT Terminal automatically if it was selected and just became unavailable, same as it
        // would never have defaulted to it in the first place on a fresh page load.
        function updateEftMethodAvailability() {
            const btn = methodRow.querySelector('.pos-method-btn[data-method="EFT Terminal"]');
            if (!btn) { return; }
            const available = eftTerminalAvailable();
            btn.classList.toggle('disabled', !available);
            btn.title = available ? '' : 'No EFT terminal is currently paired.';
            if (!available && selectedMethod === 'EFT Terminal') {
                methodRow.querySelectorAll('.pos-method-btn').forEach(function (b) { b.classList.remove('active'); });
                const fallbackBtn = Array.prototype.find.call(methodRow.querySelectorAll('.pos-method-btn'), function (b) { return !b.classList.contains('disabled'); });
                if (fallbackBtn) {
                    fallbackBtn.classList.add('active');
                    selectedMethod = fallbackBtn.dataset.method;
                    updatePosSummary();
                }
            }
            updatePosTerminalStatus();
            updateEftSettingsTopbarBtn();
        }

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
                            + '<div class="pos-amount-input-stack"><span class="pos-amount-hint">Any amount</span><input type="text" inputmode="decimal" placeholder="Enter amount" class="pos-tier-free"></div></div></div>')
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
            // A warning/error interrupts with a real popup — easy to miss as a bottom-corner
            // toast when both the operator and donor's attention is on the center of the
            // screen during a transaction. A plain success confirmation stays as the quieter
            // corner toast, since it's not something that needs to block anything.
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

        function hidePosConfirm() {
            document.getElementById('posConfirmOverlay').classList.remove('active');
        }
        // Builds the message as HTML (not textContent) so the amount/email can be highlighted —
        // both still come from the same dynamic values the caller already had, just escaped
        // before going into innerHTML since the email is donor-entered input.
        // Stays open until the operator dismisses it (OK, or tapping anywhere on the overlay) —
        // no auto-hide timer, since a clerk glancing away for a moment shouldn't come back to
        // find the confirmation already gone.
        function showPosConfirm(opts) {
            const amountHtml = '<span class="pos-confirm-amount">' + escapeHtmlPos(opts.currency + ' ' + opts.amount.toFixed(2)) + '</span>';
            let html = 'Donation of ' + amountHtml + (opts.pending ? ' recorded as pending bank transfer.' : ' recorded.');
            if (opts.email) {
                html += ' A receipt will be emailed to <span class="pos-confirm-email">' + escapeHtmlPos(opts.email) + '</span>.';
            }
            document.getElementById('posConfirmMessage').innerHTML = html;
            document.getElementById('posConfirmOverlay').classList.add('active');
        }
        // A click anywhere on the overlay dismisses it — the OK button's own click bubbles up
        // to this same listener, so one handler covers both "press OK" and "tap anywhere".
        document.getElementById('posConfirmOverlay').addEventListener('click', hidePosConfirm);

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
            cancelUrlBase: CBA_SCI_CHARGE_CANCEL_URL_BASE,
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
                overrideQuestion: document.getElementById('eftModalOverrideQuestion'),
                overrideSaving: document.getElementById('eftModalOverrideSaving'),
                countdown: document.getElementById('eftModalCountdown'),
                printNotice: document.getElementById('eftModalPrintNotice'),
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
            // No extra warning popup here — the modal's own status box already shows this exact
            // message (setStatus() sets it right before calling onDeclined/onUnresolved), so a
            // second popup on top of it was pure duplication, not new information.
            onDeclined: function () {
                activeEftProvider = null;
            },
            onUnresolved: function () {
                activeEftProvider = null;
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
        // browser tab (a POS page left open all day) but never persists beyond it and never
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
                        // Cash/Bank Transfer aren't verified on the spot by a terminal, so they
                        // get a clear center-screen confirmation instead of the quieter corner
                        // toast (UPI/EFT Terminal already have their own on-screen confirmation —
                        // the terminal prompt/receipt view — so they keep the toast).
                        if (selectedMethod === 'Cash' || selectedMethod === 'Bank Transfer') {
                            showPosConfirm({
                                amount: amount, currency: CURRENCY_CODE, email: emailValue,
                                pending: selectedMethod === 'Bank Transfer',
                            });
                        } else {
                            const pendingNote = selectedMethod === 'Bank Transfer' ? ' (Pending)' : '';
                            showToast('Saved — ' + CURRENCY_CODE + ' ' + amount.toFixed(2) + pendingNote);
                        }
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
                if (amount < EFT_MINIMUM_AMOUNT) {
                    showToast('Minimum EFT Terminal amount is ' + CURRENCY_CODE + ' ' + EFT_MINIMUM_AMOUNT.toFixed(2) + '.', true);
                    return;
                }
                const selectedTerminal = EFT_TERMINALS.find(function (t) { return String(t.id) === String(selectedTerminalId); });
                if (!selectedTerminal || !selectedTerminal.paired) {
                    // Deliberately not embedding currentTerminalLabel() here — a terminal's own
                    // label can carry a provider's brand name (as "mx51 Certification Terminal"
                    // did), which has no place leaking into a customer-facing error. Matches the
                    // server's own certification-required wording (SCITX01).
                    showToast('No active pairings found — check the terminal picker.', true);
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

            // This local ~60-second guard just stops the browser polling forever — it does
            // NOT clear the saved attempt, and startOrResumeEftPurchase() always resumes an
            // existing attempt rather than starting a new one, so clicking Pay again here is
            // safe (it re-attaches to this same Linkly session instead of double-charging).
            // Matches mx51's own 60-second recommendation (SCIREC03) for how long a POS should
            // wait before surfacing a recovery prompt, applied here too for consistency across
            // both providers — three minutes was too long to leave an operator waiting on a
            // busy day. The server independently reaches the same "unknown" conclusion around
            // 200s in pollEftCharge() if this client-side guard is somehow bypassed.
            if (Date.now() - startedAt > 60000) {
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

    @include('admin.partials.eft-terminal-settings-modal')
</body>
</html>
