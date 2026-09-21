<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Console · {{ $event->event_name }}</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link href="{{ asset('vendor/fonts/inter/inter.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/dm-sans-playfair/dm-sans-playfair.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/fonts/ibm-plex-mono/ibm-plex-mono.css') }}" rel="stylesheet">
    <style>
        :root {
            --maroon: #6B0F1A;
            --maroon-dark: #4A0A12;
            --gold: #C89B3C;
            --gold-hover: #A67C2B;
            --cream: #F9F3E7;
            --white: #FFFFFF;
            --border: #F0E5D6;
            --text-primary: #1F2A37;
            --text-secondary: #6B7280;
            --success: #10B981;
            --warning: #F59E0B;
            --error: #EF4444;
            --success-bg: #ECFDF5;
            --pending-bg: #FFF7ED;
            --error-bg: #FEF2F2;
            --serif: 'Playfair Display', Georgia, serif;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { overflow-x: hidden; }
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; font-variant-numeric: tabular-nums; background: var(--cream); color: var(--text-primary); }
        h1, h2, h3, h4 { font-family: var(--serif); }
        button, input, select, textarea { font-family: inherit; }

        /* ---------- App shell: full-width topbar on top, sidebar + main below it ---------- */
        .app-shell-wrap { display: flex; flex-direction: column; height: 100vh; }
        .app-shell { flex: 1; min-height: 0; display: flex; }

        .app-sidebar {
            width: 240px; flex-shrink: 0; background: var(--white); border-right: 1px solid var(--border);
            display: flex; flex-direction: column; justify-content: space-between;
            overflow-y: auto; transition: transform 0.25s ease;
        }
        .sidebar-nav { padding: 20px 14px; display: flex; flex-direction: column; gap: 4px; }
        .sidebar-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; border: none; background: none;
            color: var(--text-secondary); font-weight: 600; font-size: 0.92rem; text-decoration: none; text-align: left; cursor: pointer; transition: 0.15s;
        }
        .sidebar-link i { font-size: 1.05rem; width: 20px; text-align: center; flex-shrink: 0; }
        .sidebar-link:hover { background: var(--cream); color: var(--text-primary); }
        .sidebar-link.active { background: var(--gold); color: white; box-shadow: 0 4px 12px rgba(200,155,60,0.35); }

        .sidebar-decoration { padding: 24px 20px; text-align: center; border-top: 1px solid var(--border); }
        .sidebar-decoration svg { width: 110px; height: auto; margin-bottom: 12px; }
        .sidebar-decoration p { font-family: var(--serif); font-style: italic; color: var(--gold-hover); font-size: 0.85rem; line-height: 1.5; margin: 0; }
        .sidebar-decoration .lotus-divider { width: 60px; height: auto; margin: 12px auto 0; display: block; }

        .app-main { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; }

        /* ---------- Topbar (full width, above the sidebar+main row) ---------- */
        .console-topbar {
            background: linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            background-image:
                radial-gradient(circle at 8% 30%, rgba(255,255,255,0.05) 0%, transparent 45%),
                radial-gradient(circle at 92% 70%, rgba(255,255,255,0.05) 0%, transparent 45%),
                linear-gradient(135deg, var(--maroon), var(--maroon-dark));
            color: white; padding: 14px 24px; display: flex; align-items: center; gap: 16px;
            flex-shrink: 0; z-index: 40; box-shadow: 0 4px 18px rgba(74,10,18,0.25);
        }
        .sidebar-toggle { display: none; background: rgba(255,255,255,0.12); border: none; color: white; width: 38px; height: 38px; border-radius: 10px; font-size: 1.1rem; flex-shrink: 0; }
        .topbar-brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .topbar-logo { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #fff; padding: 2px; flex-shrink: 0; }
        .topbar-temple-name { font-weight: 800; font-size: 1rem; line-height: 1.2; font-family: var(--serif); }
        .topbar-temple-sub { font-size: 0.72rem; color: rgba(255,255,255,0.6); line-height: 1.2; }

        .topbar-event-title { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: center; gap: 14px; text-align: center; overflow: hidden; }
        .topbar-event-title .flourish-line { flex: 1; max-width: 90px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold), transparent); display: none; flex-shrink: 0; }
        .topbar-event-title-text { min-width: 0; max-width: 100%; }
        .topbar-event-title-text h1 { font-size: clamp(1.05rem, 2.4vw, 1.6rem); font-weight: 800; color: var(--gold); margin: 0; letter-spacing: 0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topbar-event-title-text .event-motto { font-size: 0.72rem; color: rgba(255,255,255,0.75); letter-spacing: 0.03em; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 900px) { .topbar-event-title-text .event-motto { display: none; } }

        .topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .topbar-clock { text-align: right; line-height: 1.25; display: none; }
        .topbar-clock .clock-date { font-size: 0.8rem; font-weight: 700; color: white; }
        .topbar-clock .clock-time { font-size: 0.72rem; color: rgba(255,255,255,0.7); }
        .btn-fullscreen-icon { background: rgba(255,255,255,0.12); border: none; color: white; width: 38px; height: 38px; border-radius: 50%; font-size: 1rem; flex-shrink: 0; }
        .btn-fullscreen-icon:hover { background: rgba(255,255,255,0.22); }

        .admin-pill { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.25); color: white; padding: 7px 14px; border-radius: 999px; font-weight: 700; font-size: 0.85rem; }
        .admin-pill:hover, .admin-pill:focus { background: rgba(255,255,255,0.18); color: white; }
        .admin-pill i:first-child { font-size: 1.2rem; }
        .admin-pill .bi-chevron-down { font-size: 0.7rem; }

        @media (min-width: 640px) { .topbar-clock { display: block; } }
        @media (min-width: 768px) { .topbar-event-title .flourish-line { display: block; } }

        /* ---------- Main content ---------- */
        .console-body { padding: 24px clamp(16px, 2.2vw, 32px); max-width: 100%; }
        .console-pane { display: none; }
        .console-pane.active { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; flex-wrap: wrap; }
        .page-header-icon { width: 46px; height: 46px; border-radius: 50%; background: var(--maroon); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
        .page-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .page-header p { color: var(--text-secondary); font-size: 0.87rem; margin: 2px 0 0; }
        .page-header-actions { margin-left: auto; }

        .card-panel { background: var(--white); border: 1px solid var(--border); border-radius: 14px; padding: 24px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); }
        .btn-view-all { display: inline-flex; align-items: center; gap: 6px; background: var(--white); border: 1.5px solid var(--border); color: var(--text-primary); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; }
        .btn-view-all:hover { background: var(--cream); }

        .donation-grid { display: grid; grid-template-columns: 1fr; gap: 20px; align-items: start; }
        @media (min-width: 1100px) { .donation-grid { grid-template-columns: 1.9fr 1fr; } }

        .panel-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .panel-icon { width: 44px; height: 44px; border-radius: 50%; background: rgba(200,155,60,0.14); color: var(--gold-hover); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .panel-header h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text-primary); }
        .panel-header p { margin: 0; font-size: 0.8rem; color: var(--text-secondary); }

        .mode-toggle { display: flex; gap: 10px; margin-bottom: 22px; }
        .mode-toggle button { flex: 1; padding: 13px; min-height: 48px; border-radius: 10px; border: 1.5px solid var(--border); background: var(--cream); font-weight: 700; font-size: 0.95rem; color: var(--text-secondary); transition: 0.15s; }
        .mode-toggle button.active { border-color: var(--gold); background: var(--gold); color: white; box-shadow: 0 4px 14px rgba(200,155,60,0.3); }

        .section-title { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 0.98rem; color: var(--text-primary); margin: 24px 0 14px; }
        .section-title:first-child { margin-top: 0; }
        .section-title .icon-badge-sm { width: 30px; height: 30px; border-radius: 50%; background: var(--gold); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }

        .field-row { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
        .field-row.two-col { grid-template-columns: 1fr; }
        @media (min-width: 560px) { .field-row.two-col { grid-template-columns: 1fr 1fr; } }

        .field-label { display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-primary); margin-bottom: 6px; }
        .field-label .required { color: var(--error); }
        .field-group { position: relative; }
        /* The icon centers on the input itself, not the whole field-group (which also
           includes the label above it) — anchored from the bottom edge, where the input
           always sits, rather than top:50% of the group which pushed it down into the gap
           between label and input. */
        .field-group > .field-icon { position: absolute; left: 14px; bottom: 23px; transform: translateY(50%); color: var(--text-secondary); font-size: 0.95rem; pointer-events: none; }
        .field-group input, .field-group select, .field-group textarea {
            width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 0.94rem; color: var(--text-primary); background: var(--white);
        }
        .field-group input:not(textarea), .field-group select { padding-left: 40px; min-height: 46px; }
        .field-group input:focus, .field-group select:focus, .field-group textarea:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
        .field-group textarea { resize: vertical; min-height: 70px; }

        .devotee-combobox-results { position: absolute; top: 100%; left: 0; right: 0; background: var(--white); border: 1px solid var(--border); border-radius: 10px; max-height: 280px; overflow-y: auto; z-index: 20; box-shadow: 0 16px 40px rgba(31,42,55,0.14); display: none; margin-top: 6px; }
        .devotee-combobox-results.show { display: block; }
        .devotee-combobox-item { padding: 12px 16px; cursor: pointer; border-bottom: 1px solid var(--cream); }
        .devotee-combobox-item:hover, .devotee-combobox-item.highlighted { background: var(--cream); }

        .quick-amount-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .quick-amount-btn { background: var(--white); border: 1.5px solid var(--border); color: var(--gold-hover); font-weight: 700; padding: 11px 18px; min-height: 44px; border-radius: 10px; font-size: 0.92rem; transition: 0.15s; }
        .quick-amount-btn:hover { background: var(--cream); }
        .quick-amount-btn.active { background: var(--gold); border-color: var(--gold); color: white; box-shadow: 0 4px 12px rgba(200,155,60,0.35); }
        .quick-amount-btn.custom-amount-btn { color: var(--text-secondary); }

        .checkbox-field { display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px; background: var(--cream); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 20px; }
        .checkbox-field input[type="checkbox"] { width: 20px; height: 20px; margin-top: 2px; accent-color: var(--gold); flex-shrink: 0; cursor: pointer; }
        .checkbox-field label { font-weight: 700; font-size: 0.9rem; color: var(--text-primary); cursor: pointer; margin: 0; }
        .checkbox-field .checkbox-note { display: block; font-weight: 400; font-size: 0.78rem; color: var(--text-secondary); margin-top: 2px; }

        .qty-row { display: flex; align-items: center; gap: 10px; margin: -6px 0 16px; max-width: 200px; }
        .qty-row label { font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); margin: 0; white-space: nowrap; }

        /* Multi-select donation type checkboxes, matching admin/manage-donations. */
        .donation-tier-option { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1.5px solid var(--border); border-radius: 10px; margin-bottom: 10px; cursor: pointer; transition: 0.15s; flex-wrap: wrap; background: var(--white); }
        .donation-tier-option.selected { border-color: var(--gold); background: #FDF6EA; }
        .donation-tier-option label { display: flex; align-items: center; gap: 10px; margin: 0; cursor: pointer; flex: 1; min-width: 160px; }
        .donation-tier-option input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--gold); cursor: pointer; flex-shrink: 0; }
        .donation-tier-option .tier-qty, .donation-tier-option .tier-free { padding: 8px 10px; font-size: 0.88rem; min-height: 34px; border: 1.5px solid var(--border); border-radius: 8px; }
        .donation-tier-option .tier-qty { width: 70px; }
        .donation-tier-option .tier-free { width: 110px; }
        .tier-free-quick-amounts { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; width: 100%; justify-content: flex-end; }
        .tier-free-quick-amounts .quick-amount-btn { padding: 5px 10px; font-size: 0.76rem; min-height: auto; }
        .tier-total-row { display: flex; justify-content: space-between; font-weight: 800; font-size: 0.95rem; color: var(--text-primary); padding: 10px 4px 4px; }

        /* A single donation option has nothing to choose between — it's always "on", so no
           checkbox is shown at all rather than making the user click one pointless toggle. */
        .donation-tier-option.single-option input[type="checkbox"] { display: none; }
        .donation-tier-option.single-option label { cursor: default; }

        /* ---------- Event Settings: same sidebar-nav + categorized-panel structure as
           admin/settings, in the console's own palette. A fixed-width flex column (not a
           percentage-based Bootstrap column) keeps the nav a sensible width on wide desktop
           monitors instead of stretching wider as the viewport grows, leaving the panel
           content the rest of the space. ---------- */
        .settings-layout { display: flex; gap: 28px; align-items: flex-start; flex-wrap: wrap; }
        .settings-sidebar-col { flex: 0 0 200px; max-width: 200px; }
        .settings-content-col { flex: 1 1 420px; min-width: 0; }
        .settings-nav { display: flex; flex-direction: column; gap: 4px; position: sticky; top: 90px; }
        .settings-nav-link { display: flex; align-items: center; gap: 10px; text-align: left; background: transparent; border: none; border-radius: 12px; padding: 12px 16px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); transition: 0.15s; width: 100%; }
        .settings-nav-link:hover { background: var(--cream); color: var(--gold-hover); }
        .settings-nav-link.active { background: var(--gold); color: white; box-shadow: 0 4px 12px rgba(200,155,60,0.25); }
        .settings-panel { display: none; }
        .settings-panel.active { display: block; }
        .settings-section { background: var(--cream); border: 1px solid var(--border); border-radius: 16px; padding: 22px; margin-bottom: 20px; }
        .settings-section:last-child { margin-bottom: 0; }
        .settings-section h5 { font-weight: 700; color: var(--gold-hover); margin-bottom: 14px; font-size: 0.98rem; display: flex; align-items: center; gap: 8px; }
        .settings-section .form-label { font-weight: 600; color: var(--text-primary); font-size: 0.85rem; }
        .settings-section .form-control, .settings-section .form-select { border-color: var(--border); }
        .settings-section .form-control:focus, .settings-section .form-select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(200,155,60,0.15); }
        @media (max-width: 991.98px) {
            .settings-layout { flex-direction: column; }
            .settings-sidebar-col { flex-basis: auto; max-width: 100%; width: 100%; }
            .settings-nav { flex-direction: row; overflow-x: auto; position: static; }
            .settings-nav-link { white-space: nowrap; }
        }

        /* A small, compact amount box for the "no donation types configured" case. */
        .field-group.compact { max-width: 180px; }
        .field-group.compact input { padding: 8px 10px 8px 34px; font-size: 0.9rem; min-height: 36px; }
        .field-group.compact .field-icon { font-size: 0.85rem; bottom: 18px; }

        .form-actions { display: flex; gap: 12px; margin-top: 24px; }
        .btn-reset { flex: 0 0 auto; padding: 14px 26px; border-radius: 10px; border: 1.5px solid var(--border); background: var(--white); color: var(--text-secondary); font-weight: 700; font-size: 0.95rem; }
        .btn-reset:hover { background: var(--cream); }
        .btn-save { flex: 1; padding: 14px 26px; border-radius: 10px; border: none; background: linear-gradient(135deg, var(--gold), var(--gold-hover)); color: white; font-weight: 800; font-size: 1.02rem; box-shadow: 0 8px 20px rgba(200,155,60,0.32); }
        .btn-save:disabled { opacity: 0.6; }

        .summary-panel h4 { font-size: 1rem; font-weight: 800; margin: 0 0 16px; display: flex; align-items: center; gap: 8px; color: var(--text-primary); }
        .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .summary-tile { background: var(--cream); border: 1px solid var(--border); border-radius: 10px; padding: 14px; min-width: 0; }
        .summary-tile .icon-badge-sm { width: 26px; height: 26px; font-size: 0.7rem; margin-bottom: 6px; }
        .summary-tile .label { display: flex; align-items: center; gap: 5px; color: var(--text-secondary); font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 4px; }
        .summary-tile .value { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); overflow-wrap: break-word; }
        .btn-view-pending { display: block; width: 100%; text-align: center; margin-top: 14px; padding: 11px; border-radius: 10px; border: 1.5px solid var(--gold); background: var(--white); color: var(--gold-hover); font-weight: 700; font-size: 0.85rem; cursor: pointer; }
        .btn-view-pending:hover { background: var(--cream); }

        .recent-mini-card h4 { font-size: 0.98rem; font-weight: 800; margin: 0 0 14px; display: flex; align-items: center; gap: 8px; color: var(--text-primary); }
        .recent-mini-list { display: flex; flex-direction: column; gap: 12px; max-height: 360px; overflow-y: auto; }
        .recent-mini-item { padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .recent-mini-item:last-child { border-bottom: none; padding-bottom: 0; }
        .recent-mini-top { display: flex; justify-content: space-between; gap: 8px; }
        .recent-mini-name { font-weight: 700; font-size: 0.87rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .recent-mini-amount { font-weight: 800; font-size: 0.87rem; color: var(--text-primary); flex-shrink: 0; }
        .recent-mini-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 5px; font-size: 0.75rem; color: var(--text-secondary); }

        .table-scroll-wrap { overflow: auto; border-radius: 10px; }
        table.console-table { width: 100%; border-collapse: collapse; table-layout: auto; }
        table.console-table th, table.console-table td { padding: 8px 8px; text-align: left; font-size: 0.8rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
        /* Headers wrap onto 2-3 lines instead of forcing wide columns for long donation
           option labels ("Sponsorship for a Conch") — the data underneath is just numbers. */
        table.console-table th { background: var(--cream); font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.62rem; letter-spacing: 0.02em; line-height: 1.25; position: sticky; top: 0; z-index: 5; white-space: normal; vertical-align: bottom; }
        table.console-table td.col-name { white-space: normal; min-width: 110px; max-width: 160px; font-weight: 600; }
        table.console-table td.col-amount, table.console-table th.col-amount { text-align: right; font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; }
        table.console-table th.col-amount { max-width: 80px; font-family: 'Inter', sans-serif; }
        table.console-table td.col-amount.total { font-weight: 700; color: var(--text-primary); }
        table.console-table td.col-contact { white-space: normal; max-width: 130px; }
        table.console-table td.col-contact .contact-email { display: block; font-size: 0.72rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 130px; }
        table.console-table td.col-txn { max-width: 100px; overflow: hidden; text-overflow: ellipsis; font-family: 'IBM Plex Mono', 'SFMono-Regular', Consolas, monospace; font-size: 0.74rem; color: var(--text-secondary); }
        table.console-table tbody tr:hover { background: var(--cream); }
        .status-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
        .status-pill.status-paid { background: var(--success-bg); color: var(--success); }
        .status-pill.status-pending { background: var(--pending-bg); color: var(--warning); }
        .status-pill.status-cancelled, .status-pill.status-failed { background: var(--error-bg); color: var(--error); }

        .btn-refresh { background: var(--white); border: 1.5px solid var(--border); padding: 9px 20px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; color: var(--text-primary); }
        .btn-refresh:hover { background: var(--cream); }
        .btn-export { background: linear-gradient(135deg, #1f9d6a, #34b380); border: none; padding: 9px 20px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; color: white; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-export:hover { color: white; opacity: 0.92; }

        .btn-action-edit, .btn-action-delete, .btn-action-resend, .btn-action-approve, .btn-action-checkstatus {
            border: none; width: 32px; height: 32px; padding: 0; border-radius: 50%; font-size: 0; transition: 0.15s;
            display: inline-flex; align-items: center; justify-content: center; margin: 2px;
        }
        .btn-action-edit i, .btn-action-delete i, .btn-action-resend i, .btn-action-approve i, .btn-action-checkstatus i { font-size: 0.92rem; }
        .btn-action-edit { background: rgba(200,155,60,0.14); color: var(--gold-hover); }
        .btn-action-edit:hover { background: var(--gold); color: white; }
        .btn-action-delete { background: var(--error-bg); color: var(--error); }
        .btn-action-delete:hover { background: var(--error); color: white; }
        .btn-action-resend { background: rgba(42,111,219,0.1); color: #2a6fdb; }
        .btn-action-resend:hover { background: #2a6fdb; color: white; }
        .btn-action-approve { background: var(--success-bg); color: var(--success); }
        .btn-action-approve:hover { background: var(--success); color: white; }
        .btn-action-checkstatus { background: var(--pending-bg); color: var(--warning); }
        .btn-action-checkstatus:hover { background: var(--warning); color: white; }

        /* ---------- Dashboard pane ---------- */
        .stat-tile { background: var(--white); border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(31,42,55,0.04); border: 1px solid var(--border); display: flex; align-items: center; gap: 14px; min-width: 0; }
        .stat-tile .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: white; flex-shrink: 0; }
        .stat-tile .stat-text { min-width: 0; }
        .stat-tile .label { color: var(--text-secondary); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        .stat-tile .value { font-family: 'IBM Plex Mono', 'Inter', monospace; font-variant-numeric: tabular-nums; font-size: 1.3rem; font-weight: 700; color: var(--text-primary); margin-top: 2px; overflow-wrap: break-word; }

        .breakdown-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .breakdown-row:last-child { border-bottom: none; }
        .breakdown-row .breakdown-bar-wrap { flex: 1; min-width: 0; }
        .breakdown-row .breakdown-label { display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
        .breakdown-row .breakdown-bar-bg { height: 8px; border-radius: 6px; background: var(--cream); overflow: hidden; }
        .breakdown-row .breakdown-bar-fill { height: 100%; background: linear-gradient(90deg, var(--gold), var(--gold-hover)); border-radius: 6px; }

        .fullscreen-hint { position: fixed; top: 90px; left: 50%; transform: translateX(-50%); background: rgba(31,42,55,0.92); color: white; padding: 10px 22px; border-radius: 40px; font-size: 0.85rem; font-weight: 600; z-index: 200; box-shadow: 0 10px 24px rgba(0,0,0,0.2); }
        .qe-toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: white; padding: 16px 24px; border-radius: 12px; font-weight: 700; box-shadow: 0 14px 34px rgba(0,0,0,0.18); z-index: 999; display: none; font-size: 1rem; }
        .qe-toast.error { background: var(--error); }

        /* ---------- Responsive / iPad ---------- */
        @media (max-width: 1023px) {
            .sidebar-toggle { display: inline-flex; align-items: center; justify-content: center; }
            .app-sidebar { position: fixed; left: 0; top: 70px; height: calc(100vh - 70px); transform: translateX(-100%); box-shadow: 0 0 40px rgba(0,0,0,0.2); z-index: 50; }
            .app-sidebar.open { transform: translateX(0); }
            .sidebar-backdrop { display: none; position: fixed; left: 0; right: 0; top: 70px; bottom: 0; background: rgba(31,42,55,0.4); z-index: 45; }
            .sidebar-backdrop.show { display: block; }
        }
        @media (max-width: 600px) {
            .topbar-temple-sub { display: none; }
            .console-topbar { padding: 12px 16px; }
        }
    </style>
</head>
<body>
    @php
        $backRoute = session('active_role', auth()->user()->role ?? null) === 'Event Coordinator' ? 'event-coordinator.my-events' : 'admin.events.index';
        $backLabel = $backRoute === 'event-coordinator.my-events' ? 'My Events' : 'Events';
    @endphp
    <div class="app-shell-wrap">
        <header class="console-topbar">
            <button type="button" class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div class="topbar-brand">
                @if($temple['logo'] ?? null)<img src="{{ $temple['logo'] }}" class="topbar-logo" alt="">@endif
                <div>
                    <div class="topbar-temple-name">{{ $temple['name'] ?? 'Temple' }}</div>
                    @if($temple['subtitle'] ?? null)<div class="topbar-temple-sub">{{ $temple['subtitle'] }}</div>@endif
                </div>
            </div>
            <div class="topbar-event-title">
                <span class="flourish-line"></span>
                <div class="topbar-event-title-text">
                    <h1>{{ $event->event_name }}</h1>
                    <div class="event-motto">Our Temple &bull; Our Community &bull; A Brighter Tomorrow</div>
                </div>
                <span class="flourish-line"></span>
            </div>
            <div class="topbar-right">
                <div class="topbar-clock" id="topbarClock">
                    <div class="clock-date"><i class="bi bi-calendar3 me-1"></i><span id="clockDate"></span></div>
                    <div class="clock-time" id="clockTime"></div>
                </div>
                <button type="button" class="btn-fullscreen-icon" id="fullscreenBtn" title="Toggle fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
                <div class="dropdown">
                    <button class="admin-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i><span>{{ \Illuminate\Support\Str::limit(auth()->user()->name ?? 'Admin', 14) }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if($switchableEvents->count())
                        <li><h6 class="dropdown-header">Switch Event</h6></li>
                        @foreach($switchableEvents as $switchEvent)
                        <li><a class="dropdown-item" href="{{ route('admin.events.console', $switchEvent->event_id) }}"><i class="bi bi-arrow-left-right me-2"></i>{{ $switchEvent->event_name }}</a></li>
                        @endforeach
                        <li><hr class="dropdown-divider"></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ route($backRoute) }}"><i class="bi bi-collection me-2"></i>All Events</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('logout') }}"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <div class="app-shell">
            <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
            <aside class="app-sidebar" id="appSidebar">
                <div class="sidebar-nav">
                    <button type="button" class="sidebar-link" data-pane="pane-dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></button>
                    @if($canAddDonation)
                    <button type="button" class="sidebar-link active" data-pane="pane-entry"><i class="bi bi-heart-fill"></i><span>New Donation</span></button>
                    <a href="{{ route('admin.events.pos', $event->event_id) }}" class="sidebar-link"><i class="bi bi-lightning-charge-fill"></i><span>POS Mode</span></a>
                    @endif
                    <button type="button" class="sidebar-link {{ $canAddDonation ? '' : 'active' }}" data-pane="pane-table"><i class="bi bi-card-list"></i><span>All Donations</span></button>
                    @if($canEditEvent)
                    <button type="button" class="sidebar-link" data-pane="pane-settings"><i class="bi bi-gear-fill"></i><span>Settings</span></button>
                    @endif
                    @if($canManageEventCoordinators)
                    <button type="button" class="sidebar-link" data-pane="pane-coordinators"><i class="bi bi-people-fill"></i><span>Event Coordinators</span></button>
                    @endif
                    @if($canEditEvent)
                    <button type="button" class="sidebar-link" data-pane="pane-eftpos"><i class="bi bi-credit-card-2-front-fill"></i><span>EFTPOS</span></button>
                    @endif
                    @if($canViewEventLogs)
                    <button type="button" class="sidebar-link" data-pane="pane-logs"><i class="bi bi-journal-text"></i><span>Logs</span></button>
                    @endif
                </div>
                <div class="sidebar-decoration">
                    <svg viewBox="0 0 200 130" aria-hidden="true">
                        <polygon points="100,6 112,24 88,24" fill="var(--gold)"/>
                        <rect x="93" y="24" width="14" height="8" fill="var(--gold)"/>
                        <polygon points="100,20 120,38 80,38" fill="var(--gold)" opacity="0.88"/>
                        <rect x="72" y="38" width="56" height="10" fill="var(--gold)" opacity="0.88"/>
                        <polygon points="100,34 130,54 70,54" fill="var(--gold)" opacity="0.74"/>
                        <rect x="60" y="54" width="80" height="12" fill="var(--gold)" opacity="0.74"/>
                        <polygon points="100,50 142,72 58,72" fill="var(--gold)" opacity="0.6"/>
                        <rect x="45" y="72" width="110" height="16" fill="var(--gold)" opacity="0.6"/>
                        <rect x="35" y="88" width="130" height="28" fill="var(--gold)" opacity="0.48"/>
                        <rect x="55" y="100" width="14" height="16" fill="var(--cream)"/>
                        <rect x="131" y="100" width="14" height="16" fill="var(--cream)"/>
                        <rect x="92" y="96" width="16" height="20" fill="var(--maroon)"/>
                    </svg>
                    <p>&ldquo;A small contribution creates a lasting legacy.&rdquo;</p>
                    <svg class="lotus-divider" viewBox="0 0 60 20" aria-hidden="true">
                        <path d="M30 18 C22 18 16 12 16 6 C22 6 27 10 30 16 C33 10 38 6 44 6 C44 12 38 18 30 18 Z" fill="var(--gold)" opacity="0.8"/>
                    </svg>
                </div>
            </aside>

            <div class="app-main">
                <div class="console-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                <!-- DASHBOARD -->
                <div class="console-pane {{ $canAddDonation ? '' : '' }}" id="pane-dashboard">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-speedometer2"></i></div>
                        <div>
                            <h2>Dashboard</h2>
                            <p>Full donation statistics for {{ $event->event_name }}</p>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="stat-tile"><div class="stat-icon" style="background:var(--success);"><i class="bi bi-cash-coin"></i></div><div class="stat-text"><div class="label">Paid Total</div><div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['paid_total'], 2) }}</div></div></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-tile"><div class="stat-icon" style="background:var(--warning);"><i class="bi bi-hourglass-split"></i></div><div class="stat-text"><div class="label">Pending Total</div><div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['pending_total'], 2) }}</div></div></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-tile"><div class="stat-icon" style="background:var(--maroon);"><i class="bi bi-people-fill"></i></div><div class="stat-text"><div class="label">Total Donors</div><div class="value">{{ $summary['total_donors'] }}</div></div></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-tile"><div class="stat-icon" style="background:var(--gold);"><i class="bi bi-receipt"></i></div><div class="stat-text"><div class="label">Total Donations</div><div class="value">{{ $summary['donation_count'] }}</div></div></div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card-panel h-100">
                                <div class="section-title" style="margin-top:0;"><span class="icon-badge-sm"><i class="bi bi-tags-fill"></i></span>By Donation Type (Paid)</div>
                                @forelse($options as $opt)
                                    @php $amt = $summary['option_totals'][$opt->id] ?? 0; $pct = $summary['paid_total'] > 0 ? min(100, round($amt / $summary['paid_total'] * 100)) : 0; @endphp
                                    <div class="breakdown-row">
                                        <div class="breakdown-bar-wrap">
                                            <div class="breakdown-label"><span>{{ $opt->label }}</span><span>{{ $temple['currency'] ?? '' }} {{ number_format($amt, 2) }}</span></div>
                                            <div class="breakdown-bar-bg"><div class="breakdown-bar-fill" style="width:{{ $pct }}%;"></div></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No donation types configured for this event.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card-panel h-100">
                                <div class="section-title" style="margin-top:0;"><span class="icon-badge-sm"><i class="bi bi-credit-card-fill"></i></span>By Payment Method (Paid)</div>
                                @forelse($summary['method_totals'] as $method => $amt)
                                    @php $pct = $summary['paid_total'] > 0 ? min(100, round($amt / $summary['paid_total'] * 100)) : 0; @endphp
                                    <div class="breakdown-row">
                                        <div class="breakdown-bar-wrap">
                                            <div class="breakdown-label"><span>{{ $method }}</span><span>{{ $temple['currency'] ?? '' }} {{ number_format($amt, 2) }}</span></div>
                                            <div class="breakdown-bar-bg"><div class="breakdown-bar-fill" style="width:{{ $pct }}%;"></div></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No paid donations recorded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ALL DONATIONS (full detailed table) -->
                <div class="console-pane {{ $canAddDonation ? '' : 'active' }}" id="pane-table">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-card-list"></i></div>
                        <div>
                            <h2>All Donations</h2>
                            <p>Every donation recorded for {{ $event->event_name }}</p>
                        </div>
                        <div class="page-header-actions d-flex gap-2">
                            <a href="{{ route('admin.donations.export', ['event_id' => $event->event_id]) }}" class="btn-export"><i class="bi bi-file-earmark-excel-fill"></i>Export to Excel</a>
                            <button type="button" class="btn-refresh" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                        </div>
                    </div>
                    <div class="card-panel" style="padding:0;">
                        <div class="table-scroll-wrap" style="max-height: calc(100vh - 220px);">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Type</th><th>ID</th><th>Name</th><th>Contact</th>
                                    @foreach($options as $opt)<th class="col-amount">{{ $opt->label }}</th>@endforeach
                                    <th class="col-amount">Other</th><th class="col-amount">Total</th><th>Payment</th><th>Txn ID</th><th>Date</th><th>Status</th><th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row->donation_type === 'devotee' ? 'Devotee' : 'Guest' }}</td>
                                    <td><strong>{{ $row->display_id }}</strong></td>
                                    <td class="col-name">{{ $row->display_name }}</td>
                                    <td class="col-contact">
                                        @if($row->mobile)<div>{{ $row->mobile }}</div>@endif
                                        @if($row->email)<div class="text-muted contact-email" title="{{ $row->email }}">{{ $row->email }}</div>@endif
                                        @if(!$row->mobile && !$row->email)—@endif
                                    </td>
                                    @foreach($options as $opt)
                                    <td class="col-amount">@if(($row->option_amounts[$opt->id] ?? 0) > 0){{ number_format($row->option_amounts[$opt->id], 2) }}@else — @endif</td>
                                    @endforeach
                                    <td class="col-amount">@if($row->other_amount > 0){{ number_format($row->other_amount, 2) }}@else — @endif</td>
                                    <td class="col-amount total">{{ number_format($row->amount, 2) }}</td>
                                    <td>{{ $row->payment_method }}</td>
                                    <td class="col-txn" title="{{ $row->transaction_id }}">
                                        {{ $row->transaction_id ?: '—' }}
                                        @if(!empty($row->linkly_txn_ref))
                                        <div class="text-muted small" title="Linkly reference: {{ $row->linkly_txn_ref }}">Linkly: {{ $row->linkly_txn_ref }}</div>
                                        @endif
                                    </td>
                                    <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
                                    <td><span class="status-pill status-{{ strtolower($row->payment_status) }}">{{ $row->payment_status === 'Paid' ? 'Completed' : $row->payment_status }}</span></td>
                                    <td class="text-end">
                                        @include('admin.partials.donation-actions', ['row' => $row])
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="{{ 8 + $options->count() }}" class="text-center text-muted py-5">No donations recorded for this event yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                @if($canAddDonation)
                <!-- DONATIONS WORKSPACE (Quick Entry + Recent Donations combined) -->
                <div class="console-pane active" id="pane-entry">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-heart-fill"></i></div>
                        <div>
                            <h2>Log New Donation</h2>
                            <p>Record a new donation received for {{ $event->event_name }}</p>
                        </div>
                        <div class="page-header-actions">
                            <button type="button" class="btn-view-all" data-pane="pane-table"><i class="bi bi-list-ul me-1"></i>View All Donations</button>
                        </div>
                    </div>

                    <div class="donation-grid">
                        <div class="card-panel">
                            <div class="mode-toggle">
                                <button type="button" id="qeToggleDevotee"><i class="bi bi-person-check-fill me-1"></i>Existing Devotee</button>
                                <button type="button" class="active" id="qeToggleGuest"><i class="bi bi-person-heart me-1"></i>Guest</button>
                            </div>

                            <div id="qeDevoteeFields" style="display:none;">
                                <div class="field-row two-col">
                                    <div class="field-group devotee-combobox-wrap">
                                        <label class="field-label">Search Devotee <span class="required">*</span></label>
                                        <i class="bi bi-search field-icon"></i>
                                        <input type="text" id="qeDevoteeSearch" placeholder="Name, email, or mobile...">
                                        <input type="hidden" id="qeDevoteeId">
                                        <div class="devotee-combobox-results" id="qeDevoteeResults"></div>
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Donation Date <span class="required">*</span></label>
                                        <i class="bi bi-calendar3 field-icon"></i>
                                        <input type="date" id="qeDonationDateDevotee">
                                    </div>
                                </div>
                            </div>

                            <div id="qeGuestFields">
                                <div class="field-row two-col">
                                    <div class="field-group">
                                        <label class="field-label">Donor Name <span class="required">*</span></label>
                                        <i class="bi bi-person field-icon"></i>
                                        <input type="text" id="qeGuestName" placeholder="Enter full name">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Donation Date <span class="required">*</span></label>
                                        <i class="bi bi-calendar3 field-icon"></i>
                                        <input type="date" id="qeDonationDate">
                                    </div>
                                </div>
                                <div class="field-row two-col">
                                    <div class="field-group">
                                        <label class="field-label">Email{{ $event->require_donor_email ? ' *' : ' (Optional)' }}</label>
                                        <i class="bi bi-envelope field-icon"></i>
                                        <input type="email" id="qeGuestEmail" placeholder="example@email.com">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Mobile{{ $event->require_donor_mobile ? ' *' : ' (Optional)' }}</label>
                                        <i class="bi bi-telephone field-icon"></i>
                                        <input type="text" id="qeGuestMobile" placeholder="04XX XXX XXX">
                                    </div>
                                </div>
                            </div>

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-currency-dollar"></i></span>Donation Amount</div>

                            <!-- Event has configured donation types: pick one or more, total is computed. -->
                            <div id="qeTiersWrap" style="display:none;">
                                <div id="qeTiers"></div>
                                <div class="tier-total-row"><span>Total</span><span id="qeTierTotal">{{ $temple['currency'] ?? '' }} 0.00</span></div>
                            </div>

                            <!-- No donation types configured: a plain, compact amount field. -->
                            <div id="qeSimpleAmountWrap">
                                <div class="quick-amount-row" id="qeQuickAmounts"></div>
                                <div class="field-group compact" style="margin-top:10px;">
                                    <label class="field-label">Amount (AUD)</label>
                                    <i class="bi bi-currency-dollar field-icon"></i>
                                    <input type="text" inputmode="decimal" id="qeAmount" placeholder="0.00">
                                </div>
                            </div>

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-file-earmark-text-fill"></i></span>Additional Information</div>
                            <div class="field-row">
                                <div class="field-group">
                                    <textarea id="qeDetails" rows="2" placeholder="e.g. In memory of..., Family name..., Special request..."></textarea>
                                </div>
                            </div>

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-credit-card-fill"></i></span>Payment Details</div>
                            <div class="field-row two-col">
                                <div class="field-group">
                                    <label class="field-label">Payment Method</label>
                                    <i class="bi bi-wallet2 field-icon"></i>
                                    <select id="qePaymentMethod"></select>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Reference (Optional)</label>
                                    <i class="bi bi-hash field-icon"></i>
                                    <input type="text" id="qeTransactionId" placeholder="Transaction / cheque no.">
                                </div>
                            </div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">Status</label>
                                    <i class="bi bi-check2-circle field-icon"></i>
                                    <select id="qePaymentStatus">
                                        <option value="Paid">Completed</option>
                                        <option value="Pending">Pending</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn-reset" id="qeResetBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                                <button type="button" class="btn-save" id="qeSaveBtn"><i class="bi bi-save2-fill me-2"></i>Save Donation</button>
                            </div>
                        </div>

                        <div>
                            <div class="card-panel summary-panel">
                                <h4><i class="bi bi-pie-chart-fill" style="color:var(--gold);"></i>Donation Summary</h4>
                                <div class="summary-grid">
                                    <div class="summary-tile">
                                        <div class="label"><i class="bi bi-cash-coin"></i>Total Collected</div>
                                        <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['paid_total'], 2) }}</div>
                                    </div>
                                    <div class="summary-tile">
                                        <div class="label"><i class="bi bi-people-fill"></i>Total Donors</div>
                                        <div class="value">{{ $summary['total_donors'] }}</div>
                                    </div>
                                    <div class="summary-tile">
                                        <div class="label"><i class="bi bi-calendar-check"></i>Today's Collection</div>
                                        <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['today_total'], 2) }}</div>
                                    </div>
                                    <div class="summary-tile">
                                        <div class="label"><i class="bi bi-hourglass-split"></i>Pending Transfers</div>
                                        <div class="value">{{ $temple['currency'] ?? '' }} {{ number_format($summary['pending_total'], 2) }}</div>
                                    </div>
                                </div>
                                <button type="button" class="btn-view-pending" data-pane="pane-table">View Pending ({{ $summary['pending_count'] }})</button>
                            </div>

                            <div class="card-panel mt-3 recent-mini-card">
                                <h4><i class="bi bi-clock-history" style="color:var(--gold);"></i>Recent Donations</h4>
                                <div class="recent-mini-list">
                                    @forelse($rows->take(8) as $row)
                                        <div class="recent-mini-item">
                                            <div class="recent-mini-top">
                                                <span class="recent-mini-name">{{ $row->display_name }}</span>
                                                <span class="recent-mini-amount">{{ $temple['currency'] ?? '' }} {{ number_format($row->amount, 2) }}</span>
                                            </div>
                                            <div class="recent-mini-bottom">
                                                <span>{{ date('d M', strtotime($row->donation_date)) }}</span>
                                                <span class="status-pill status-{{ strtolower($row->payment_status) }}">{{ $row->payment_status === 'Paid' ? 'Completed' : $row->payment_status }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted small mb-0">No donations recorded for this event yet.</p>
                                    @endforelse
                                </div>
                                <button type="button" class="btn-view-all w-100 mt-3 justify-content-center" data-pane="pane-table"><i class="bi bi-list-ul me-1"></i>View All Donations</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($canEditEvent)
                <!-- SETTINGS -->
                <div class="console-pane" id="pane-settings">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-gear-fill"></i></div>
                        <div>
                            <h2>Event Settings</h2>
                            <p>Manage details, donation options, contacts and gallery for {{ $event->event_name }}</p>
                        </div>
                    </div>

                    <div class="card-panel">
                        <form action="{{ route('admin.events.update', $event->event_id) }}" method="POST">
                            @csrf
                            <div class="settings-layout">
                                <div class="settings-sidebar-col">
                                    <div class="settings-nav">
                                        <button type="button" class="settings-nav-link active" data-panel="details"><i class="bi bi-info-circle-fill"></i> Event Details</button>
                                        <button type="button" class="settings-nav-link" data-panel="public-page"><i class="bi bi-window"></i> Public Page</button>
                                        <button type="button" class="settings-nav-link" data-panel="donations"><i class="bi bi-wallet2"></i> Donations &amp; Payments</button>
                                        <button type="button" class="settings-nav-link" data-panel="media"><i class="bi bi-images"></i> Media &amp; Images</button>
                                        <button type="button" class="settings-nav-link" data-panel="options"><i class="bi bi-cash-coin"></i> Donation Options</button>
                                        <button type="button" class="settings-nav-link" data-panel="contacts"><i class="bi bi-telephone-fill"></i> Public Contacts</button>
                                        <button type="button" class="settings-nav-link" data-panel="gallery"><i class="bi bi-image-fill"></i> Gallery Images</button>
                                    </div>
                                </div>

                                <div class="settings-content-col">
                                    <!-- EVENT DETAILS -->
                                    <div class="settings-panel active" data-panel-content="details">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-info-circle-fill"></i>Event Information</h5>
                                            <div class="row g-3">
                                                <div class="col-md-8">
                                                    <label class="form-label">Event Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="event_name" class="form-control rounded-3" value="{{ $event->event_name }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">URL Slug</label>
                                                    <input type="text" name="slug" class="form-control rounded-3" value="{{ $event->slug }}" placeholder="Leave blank to auto-generate">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control rounded-3" rows="3">{{ $event->description }}</textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Event Date <span class="text-danger">*</span></label>
                                                    <input type="date" name="event_date" class="form-control rounded-3" value="{{ $event->event_date }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Location / Venue <span class="text-danger">*</span></label>
                                                    <input type="text" name="location" class="form-control rounded-3" value="{{ $event->location }}" required>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-check">
                                                        <input type="checkbox" name="date_tbc" class="form-check-input" id="settingsDateTbc" value="1" {{ $event->date_tbc ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="settingsDateTbc">Date to be confirmed<span class="d-block text-muted small">Shows "Date to be confirmed" publicly instead of the date above (still used internally for sorting).</span></label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                                    <input type="time" name="start_time" class="form-control rounded-3" value="{{ date('H:i', strtotime($event->start_time)) }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                                                    <input type="time" name="end_time" class="form-control rounded-3" value="{{ date('H:i', strtotime($event->end_time)) }}" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select rounded-3">
                                                        @foreach(['Upcoming', 'Ongoing', 'Completed', 'Cancelled'] as $status)
                                                            <option value="{{ $status }}" {{ $event->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PUBLIC PAGE -->
                                    <div class="settings-panel" data-panel-content="public-page">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-window"></i>Donor Requirements</h5>
                                            <div class="form-check mb-2">
                                                <input type="checkbox" name="show_donation_summary" class="form-check-input" id="settingsShowSummary" value="1" {{ $event->show_donation_summary ? 'checked' : '' }}>
                                                <label class="form-check-label" for="settingsShowSummary">Show "amount raised so far" on the public event page</label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input type="checkbox" name="require_donor_email" class="form-check-input" id="settingsRequireEmail" value="1" {{ $event->require_donor_email ? 'checked' : '' }}>
                                                <label class="form-check-label" for="settingsRequireEmail">Require donor email on this event's donation form</label>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="require_donor_mobile" class="form-check-input" id="settingsRequireMobile" value="1" {{ $event->require_donor_mobile ? 'checked' : '' }}>
                                                <label class="form-check-label" for="settingsRequireMobile">Require donor mobile on this event's donation form</label>
                                            </div>
                                        </div>

                                        <div class="settings-section">
                                            <h5><i class="bi bi-palette-fill"></i>Theme Colours</h5>
                                            <p class="text-muted small">An entirely optional, reversible switch — off (the default) uses the temple's usual theme; turning it on applies this event's own colours instead. Turning it back off always restores the temple's default look without losing whatever custom colours are saved here, so switching back and forth never needs re-entering them.</p>
                                            <div class="form-check form-switch mb-3">
                                                <input type="hidden" name="theme_enabled" value="0">
                                                <input type="checkbox" class="form-check-input" role="switch" id="themeEnabledSwitch" name="theme_enabled" value="1" {{ $event->theme_enabled ? 'checked' : '' }}>
                                                <label class="form-check-label" for="themeEnabledSwitch">Use a custom theme for this event</label>
                                            </div>
                                            <div id="themeColorFields" style="{{ $event->theme_enabled ? '' : 'display:none;' }}">
                                                <div class="row g-3">
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small d-block">Primary <span class="text-muted">(buttons)</span></label>
                                                        <div class="d-flex gap-2 align-items-center">
                                                            <input type="color" class="form-control form-control-color" style="width:44px;" value="{{ $event->theme_primary_color ?: $temple['primary_color'] }}" data-color-for="theme_primary_color">
                                                            <input type="text" name="theme_primary_color" class="form-control form-control-sm" placeholder="Inherit" value="{{ $event->theme_primary_color }}" maxlength="20" id="theme_primary_color">
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small d-block">Accent <span class="text-muted">(highlights)</span></label>
                                                        <div class="d-flex gap-2 align-items-center">
                                                            <input type="color" class="form-control form-control-color" style="width:44px;" value="{{ $event->theme_accent_color ?: $temple['accent_color'] }}" data-color-for="theme_accent_color">
                                                            <input type="text" name="theme_accent_color" class="form-control form-control-sm" placeholder="Inherit" value="{{ $event->theme_accent_color }}" maxlength="20" id="theme_accent_color">
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small d-block">Dark <span class="text-muted">(hero &amp; headings)</span></label>
                                                        <div class="d-flex gap-2 align-items-center">
                                                            <input type="color" class="form-control form-control-color" style="width:44px;" value="{{ $event->theme_dark_color ?: $temple['dark_color'] }}" data-color-for="theme_dark_color">
                                                            <input type="text" name="theme_dark_color" class="form-control form-control-sm" placeholder="Inherit" value="{{ $event->theme_dark_color }}" maxlength="20" id="theme_dark_color">
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small d-block">Page background</label>
                                                        <div class="d-flex gap-2 align-items-center">
                                                            <input type="color" class="form-control form-control-color" style="width:44px;" value="{{ $event->theme_body_color ?: '#fbf8f1' }}" data-color-for="theme_body_color">
                                                            <input type="text" name="theme_body_color" class="form-control form-control-sm" placeholder="Inherit" value="{{ $event->theme_body_color }}" maxlength="20" id="theme_body_color">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @if($event->slug)
                                            <a href="{{ route('events.show', $event->slug) }}" target="_blank" class="btn btn-outline-secondary btn-sm mt-3"><i class="bi bi-box-arrow-up-right me-1"></i>Preview Public Page</a>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- DONATIONS & PAYMENTS -->
                                    <div class="settings-panel" data-panel-content="donations">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-bank2"></i>Event Donation Account</h5>
                                            <p class="text-muted small mb-3">Leave any field blank to use the temple's global donation account from System Settings — set one here only to override it just for this event.</p>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Account Name</label>
                                                    <input type="text" name="donation_account_name" class="form-control rounded-3" value="{{ $event->donation_account_name }}" placeholder="{{ $event->effectiveDonationAccountName() }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Bank Name</label>
                                                    <input type="text" name="donation_bank_name" class="form-control rounded-3" value="{{ $event->donation_bank_name }}" placeholder="{{ $event->effectiveDonationBankName() }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">BSB</label>
                                                    <input type="text" name="donation_bsb" class="form-control rounded-3" value="{{ $event->donation_bsb }}" placeholder="{{ $event->effectiveDonationBsb() }}">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Account Number</label>
                                                    <input type="text" name="donation_account_number" class="form-control rounded-3" value="{{ $event->donation_account_number }}" placeholder="{{ $event->effectiveDonationAccountNumber() }}">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Public Contact Email(s)</label>
                                                    <input type="text" name="donation_contact_email" class="form-control rounded-3" value="{{ $event->donation_contact_email }}" placeholder="{{ \App\Models\Setting::get('donation_receipt_email', '') }}">
                                                    <div class="form-text">Comma-separated. Shown to donors on this event's public donation page ("send your transfer receipt to...").</div>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Coordinator Email(s)</label>
                                                    <input type="text" name="coordinator_emails" class="form-control rounded-3" value="{{ $event->coordinator_emails }}" placeholder="cc1@example.com, cc2@example.com">
                                                    <div class="form-text">Comma-separated. CC'd internally on this event's donation receipts — never shown to donors.</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="settings-section">
                                            <h5><i class="bi bi-credit-card-fill"></i>Payment Methods</h5>
                                            @php $eventMethodsOverride = $event->paymentMethodsOverride(); @endphp
                                            <div class="form-check mb-2">
                                                <input type="hidden" name="use_global_payment_methods" value="0">
                                                <input type="checkbox" name="use_global_payment_methods" class="form-check-input" id="settingsUseGlobalMethods" value="1" {{ $eventMethodsOverride === null ? 'checked' : '' }}>
                                                <label class="form-check-label" for="settingsUseGlobalMethods">Use the global donation settings<span class="d-block text-muted small">Uncheck to choose which payment methods are available for this event specifically (e.g. disable Stripe just for this event).</span></label>
                                            </div>
                                            <div id="settingsPaymentMethodsList" style="{{ $eventMethodsOverride === null ? 'display:none;' : '' }}">
                                                <div class="d-flex flex-wrap gap-3">
                                                    @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'EFT Terminal', 'Stripe'] as $method)
                                                    <div class="form-check">
                                                        <input type="checkbox" name="enabled_payment_methods[]" value="{{ $method }}" class="form-check-input" id="settingsMethod{{ strtolower(str_replace(' ', '', $method)) }}"
                                                            {{ in_array($method, $eventMethodsOverride ?? $globalPaymentMethods, true) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="settingsMethod{{ strtolower(str_replace(' ', '', $method)) }}">{{ $method }}</label>
                                                    </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MEDIA & IMAGES -->
                                    <div class="settings-panel" data-panel-content="media">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-images"></i>Media &amp; Images</h5>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Header Image Path</label>
                                                    <input type="text" name="header_image" class="form-control rounded-3" value="{{ $event->header_image }}" placeholder="images/events/header.jpg">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Flyer Image Path</label>
                                                    <input type="text" name="flyer_image" class="form-control rounded-3" value="{{ $event->flyer_image }}" placeholder="images/events/flyer.png">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">QR Code Image Path</label>
                                                    <input type="text" name="qr_code_image" class="form-control rounded-3" value="{{ $event->qr_code_image }}" placeholder="images/events/qr.png">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- DONATION OPTIONS -->
                                    <div class="settings-panel" data-panel-content="options">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-cash-coin"></i>Donation Options</h5>
                                            @include('admin.partials.event-donation-options-fields', ['options' => $options, 'formSuffix' => 'settings'])
                                        </div>
                                    </div>

                                    <!-- PUBLIC CONTACTS -->
                                    <div class="settings-panel" data-panel-content="contacts">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-telephone-fill"></i>Public Contacts</h5>
                                            @include('admin.partials.event-contacts-fields', ['contacts' => $event->contactList(), 'formSuffix' => 'settings'])
                                        </div>
                                    </div>

                                    <!-- GALLERY IMAGES -->
                                    <div class="settings-panel" data-panel-content="gallery">
                                        <div class="settings-section">
                                            <h5><i class="bi bi-image-fill"></i>Gallery Images</h5>
                                            @include('admin.partials.event-gallery-fields', ['galleryImages' => $event->galleryImages(), 'formSuffix' => 'settings'])
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn-save"><i class="bi bi-save2-fill me-2"></i>Save Settings</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @endif

                @if($canManageEventCoordinators)
                <!-- EVENT COORDINATORS -->
                <div class="console-pane" id="pane-coordinators">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <h2>Event Coordinators</h2>
                            <p>People with console access to {{ $event->event_name }}</p>
                        </div>
                    </div>

                    <div class="card-panel mb-3">
                        <div class="section-title" style="margin-top:0;"><span class="icon-badge-sm"><i class="bi bi-person-plus-fill"></i></span>Add a Coordinator</div>
                        <ul class="nav nav-pills mb-3 small" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#coordExistingPane" type="button">Existing User</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#coordNewPane" type="button">New Person</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="coordExistingPane">
                                <form action="{{ route('admin.events.coordinators.store', $event->event_id) }}" method="POST" class="d-flex gap-2 flex-wrap">
                                    @csrf
                                    <input type="hidden" name="return_context" value="console">
                                    <select name="user_id" class="form-select" style="max-width:280px;" required>
                                        <option value="">-- Choose a user --</option>
                                        @foreach($allUsersForCoordinators as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                        @endforeach
                                    </select>
                                    <select name="level" class="form-select" style="max-width:160px;">
                                        <option value="entry" selected>Entry</option>
                                        <option value="pos">POS</option>
                                        <option value="view">View</option>
                                        @if($activeRole === 'Admin')
                                        <option value="admin">Admin</option>
                                        @endif
                                    </select>
                                    <button type="submit" class="btn-save" style="flex:0 0 auto; padding:10px 24px;">Add</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="coordNewPane">
                                <form action="{{ route('admin.events.coordinators.store', $event->event_id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="return_context" value="console">
                                    <div class="field-row two-col">
                                        <div class="field-group"><label class="field-label">Full Name</label><input type="text" name="name" required></div>
                                        <div class="field-group"><label class="field-label">Email</label><input type="email" name="email" required></div>
                                    </div>
                                    <div class="field-row two-col">
                                        <div class="field-group"><label class="field-label">Mobile</label><input type="text" name="mobile" required></div>
                                        <div class="field-group">
                                            <label class="field-label">Access Level</label>
                                            <select name="level">
                                                <option value="entry" selected>Entry</option>
                                                <option value="pos">POS</option>
                                                <option value="view">View</option>
                                                @if($activeRole === 'Admin')
                                                <option value="admin">Admin</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn-save">Create &amp; Add</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card-panel" style="padding:0;">
                        <div class="table-scroll-wrap">
                        <table class="console-table">
                            <thead>
                                <tr><th>Name</th><th>Email</th><th>Level</th><th>Status</th><th>Last Login</th><th>Last Reset Email Sent</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                @forelse($eventCoordinators as $coord)
                                @php $canTouchThisCoord = $activeRole === 'Admin' || $coord->level !== 'admin'; @endphp
                                <tr>
                                    <td class="col-name">{{ $coord->name }}</td>
                                    <td>{{ $coord->email }}</td>
                                    <td>
                                        @if($canTouchThisCoord)
                                        <form action="{{ route('admin.events.coordinators.updateLevel', [$event->event_id, $coord->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="return_context" value="console">
                                            <select name="level" class="form-select form-select-sm d-inline-block" style="width:auto;" onchange="this.form.submit()">
                                                <option value="view" {{ $coord->level === 'view' ? 'selected' : '' }}>View</option>
                                                <option value="entry" {{ $coord->level === 'entry' ? 'selected' : '' }}>Entry</option>
                                                <option value="pos" {{ $coord->level === 'pos' ? 'selected' : '' }}>POS</option>
                                                @if($activeRole === 'Admin')
                                                <option value="admin" {{ $coord->level === 'admin' ? 'selected' : '' }}>Admin</option>
                                                @endif
                                            </select>
                                        </form>
                                        @else
                                        <span class="status-pill status-paid">Admin</span>
                                        @endif
                                    </td>
                                    <td><span class="status-pill status-{{ $coord->status === 'Active' ? 'paid' : 'cancelled' }}">{{ $coord->status === 'Active' ? 'Active' : 'Locked' }}</span></td>
                                    <td>{{ $coord->last_login_at ? date('d M Y H:i', strtotime($coord->last_login_at)) : 'Never' }}</td>
                                    <td>{{ $coord->last_reset_email_sent_at ? date('d M Y H:i', strtotime($coord->last_reset_email_sent_at)) : 'Never' }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.events.coordinators.sendResetLink', [$event->event_id, $coord->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="return_context" value="console">
                                            <button type="submit" class="btn-action-resend" title="Send password reset link"><i class="bi bi-key-fill"></i></button>
                                        </form>
                                        @if($canTouchThisCoord)
                                        <form action="{{ route('admin.events.coordinators.toggleLock', [$event->event_id, $coord->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $coord->status === 'Active' ? 'Lock' : 'Unlock' }} this account?')">
                                            @csrf
                                            <input type="hidden" name="return_context" value="console">
                                            <button type="submit" class="btn-action-checkstatus" title="{{ $coord->status === 'Active' ? 'Lock account' : 'Unlock account' }}">
                                                <i class="bi {{ $coord->status === 'Active' ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.events.coordinators.destroy', [$event->event_id, $coord->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this coordinator\'s access to this event?')">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="return_context" value="console">
                                            <button type="submit" class="btn-action-delete" title="Remove access"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No coordinators assigned yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
                @endif

                @if($canEditEvent)
                <!-- EFTPOS / LINKLY CORE PAYMENTS -->
                <div class="console-pane" id="pane-eftpos">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                        <div>
                            <h2>EFTPOS — Linkly Core Payments</h2>
                            <p>Terminal pairing, accreditation testing and per-transaction refunds for this event.</p>
                        </div>
                        <div class="page-header-actions">
                            <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-question-circle me-1"></i>Help</a>
                        </div>
                    </div>

                    <div class="card-panel mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="text-muted small">Environment: <strong class="text-uppercase">{{ $linklyMode }}</strong> &middot; each terminal below is independently paired, so a second station can run its own concurrently.</div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-question-circle me-1"></i>Help</a>
                                <a href="{{ route('admin.events.pos', $event->event_id) }}" target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Open POS Terminal Screen (Purchase)</a>
                            </div>
                        </div>
                        @foreach($eftTerminals as $terminal)
                        @php $lastKnown = $terminal->lastKnownStatus(); @endphp
                        <div class="row g-3 align-items-center border-top pt-3 mt-2">
                            <div class="col-md-3">
                                <strong>{{ $terminal->label }}</strong>
                                @if($terminal->is_default)<span class="badge bg-primary ms-1">Default</span>@endif
                                <div class="text-muted small">{{ $terminal->key }}</div>
                            </div>
                            <div class="col-md-2">
                                <span class="status-pill status-{{ $terminal->isPaired($linklyMode) ? 'paid' : 'cancelled' }}">{{ $terminal->isPaired($linklyMode) ? 'Paired' : 'Not paired' }}</span>
                            </div>
                            <div class="col-md-2">
                                @if($lastKnown['state'] === 'online')
                                <span class="status-pill status-paid" title="Last confirmed via a {{ $lastKnown['via'] }} at {{ $lastKnown['at']->format('d M Y H:i') }}"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Online</span>
                                @elseif($lastKnown['state'] === 'offline')
                                <span class="status-pill status-cancelled" title="Last attempt via a {{ $lastKnown['via'] }} at {{ $lastKnown['at']->format('d M Y H:i') }}"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Offline</span>
                                @else
                                <span class="status-pill status-pending">Not checked</span>
                                @endif
                                @if($lastKnown['at'])
                                <div class="text-muted" style="font-size:0.68rem;">{{ $lastKnown['at']->diffForHumans() }}</div>
                                @endif
                            </div>
                            <div class="col-md-3">
                                <form action="{{ route('admin.events.eft.pair', $event->event_id) }}" method="POST" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                                    <input type="text" name="pair_code" class="form-control form-control-sm rounded-3" placeholder="Pair / repair code" required maxlength="10">
                                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-plug-fill me-1"></i>Pair</button>
                                </form>
                            </div>
                            <div class="col-md-2">
                                <form action="{{ route('admin.events.eft.logon', $event->event_id) }}" method="POST" onsubmit="return confirm('Check {{ $terminal->label }} is online now?')">
                                    @csrf
                                    <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Check Status</button>
                                </form>
                            </div>
                        </div>
                        @endforeach

                        <form action="{{ route('admin.eft-terminals.store') }}" method="POST" class="row g-2 align-items-end border-top pt-3 mt-2">
                            @csrf
                            <input type="hidden" name="return_context" value="event-console:{{ $event->event_id }}">
                            <div class="col-md-4">
                                <label class="field-label small">New terminal key</label>
                                <input type="text" name="key" class="form-control form-control-sm" placeholder="e.g. event-counter-2" maxlength="40" required>
                            </div>
                            <div class="col-md-5">
                                <label class="field-label small">Label</label>
                                <input type="text" name="label" class="form-control form-control-sm" placeholder="e.g. Event Counter 2" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add Terminal</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-panel" style="padding:0;">
                        <div class="p-3 pb-0"><h5 class="mb-0"><i class="bi bi-clock-history me-1"></i>Recent Linkly Transactions</h5></div>
                        <div class="table-scroll-wrap" style="max-height: calc(100vh - 420px);">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Type</th><th class="col-amount">Amount</th><th>Transaction Reference</th><th>Terminal</th><th>Donation</th><th>Date/Time</th><th>Result</th><th>Response Code</th><th>Session ID</th><th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($linklyTransactions as $txn)
                                @php
                                    $pillClass = match($txn->status) {
                                        'approved' => 'paid',
                                        'initiated', 'in_progress' => 'pending',
                                        default => 'cancelled',
                                    };
                                    $canRefundRow = $txn->txn_type === 'purchase' && $txn->status === 'approved' && !$linklyTransactions->contains(fn ($t) => $t->original_transaction_id === $txn->id && in_array($t->status, ['initiated', 'in_progress', 'approved']));
                                @endphp
                                <tr>
                                    <td class="text-capitalize">{{ $txn->txn_type }}</td>
                                    <td class="col-amount">{{ $txn->amount !== null ? number_format($txn->amount, 2) : '—' }}</td>
                                    <td class="col-txn"><span id="txnref-{{ $txn->id }}">{{ $txn->pos_txn_ref }}</span></td>
                                    <td class="small text-muted">{{ $txn->eftTerminal->label ?? '—' }}</td>
                                    <td>
                                        @if($txn->donation_id)
                                        {{-- Same 'DN'/'GD' + zero-padded id format used everywhere else a donation is
                                             identified (see EventDonationBreakdown::forEvent()'s display_id). --}}
                                        {{ ($txn->donation_type === 'devotee' ? 'DN' : 'GD') . str_pad($txn->donation_id, 5, '0', STR_PAD_LEFT) }}
                                        @else
                                        <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td><span id="txntime-{{ $txn->id }}">{{ $txn->created_at->format('d M Y H:i:s') }}</span></td>
                                    <td><span class="status-pill status-{{ $pillClass }}">{{ ucfirst($txn->status) }}</span></td>
                                    <td>{{ $txn->response_code ?: '—' }}</td>
                                    <td class="text-muted small text-break">{{ $txn->linkly_session_id ?: '—' }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Copy reference + timestamp" onclick="copyEftRef('{{ $txn->id }}')"><i class="bi bi-clipboard"></i></button>
                                        @if($txn->linkly_session_id)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Check transaction status" onclick="checkEftStatus('{{ $txn->linkly_session_id }}')"><i class="bi bi-arrow-clockwise"></i></button>
                                        <form action="{{ route('admin.events.eft.reprint', [$event->event_id, $txn->linkly_session_id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reprint receipt"><i class="bi bi-receipt"></i></button>
                                        </form>
                                        @endif
                                        @if($canRefundRow)
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Refund" onclick="openEftRefundModal({{ $txn->id }}, {{ $txn->amount }})"><i class="bi bi-arrow-counterclockwise"></i> Refund</button>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="10" class="text-center text-muted py-4">No Linkly transactions recorded for this event yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
                @endif

                @if($canViewEventLogs)
                <!-- EVENT LOGS -->
                <div class="console-pane" id="pane-logs">
                    <div class="page-header">
                        <div class="page-header-icon"><i class="bi bi-journal-text"></i></div>
                        <div>
                            <h2>Logs</h2>
                            <p>Recent recorded actions for {{ $event->event_name }} (most recent 200)</p>
                        </div>
                    </div>
                    <div class="card-panel" style="padding:0;">
                        <div class="table-scroll-wrap" style="max-height: calc(100vh - 220px);">
                        <table class="console-table">
                            <thead>
                                <tr>
                                    <th>Date/Time</th><th>Action</th><th>Performed By</th><th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($eventLogs as $log)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') }}</td>
                                    <td>{{ $log->action }}</td>
                                    <td>{{ $log->performed_by_name ?? 'System' }}</td>
                                    <td>{{ $log->ip_address ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-5">No log entries recorded for this event yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    </div>

    <div class="qe-toast" id="qeToast"></div>

    @if($canEditEvent)
    <!-- EFTPOS REFUND MODAL — a real terminal transaction (the customer may need to
         re-present their card), so it runs the same async start+poll+cancel flow as a
         Purchase, just against the refund-start endpoint instead. -->
    <div class="modal fade" id="eftRefundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-arrow-counterclockwise text-danger me-2"></i>Refund Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" id="eftRefundCloseBtn"></button>
                </div>
                <div class="modal-body py-3">
                    <div id="eftRefundFormArea">
                        <label class="form-label">Refund amount</label>
                        <input type="number" step="0.01" min="0.01" class="form-control rounded-3" id="eftRefundAmount">
                        <p class="text-muted small mt-2 mb-0">The customer may be asked to present their card again on the terminal to complete the refund.</p>
                    </div>
                    <div id="eftRefundStatusArea" style="display:none;" class="text-center py-3">
                        <div class="spinner-border text-danger mb-2" role="status"></div>
                        <div class="fw-bold" id="eftRefundStatusLine1">Starting…</div>
                        <div class="text-muted small" id="eftRefundStatusLine2"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="eftRefundConfirmBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Confirm Refund</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- EDIT MODALS (devotee + guest) — same fields as the main Manage Donations page -->
    @if($canEditDonation)
        @foreach($rows->where('donation_type', 'devotee') as $row)
        <div class="modal fade" id="editDevoteeDonationModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.donations.updateDevotee', $row->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Devotee Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Devotee</label>
                                <input type="text" class="form-control rounded-3" value="{{ $row->display_name }}" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $row->amount }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Mode</label>
                                    <select name="payment_mode" class="form-select rounded-3" required>
                                        @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'EFT Terminal', 'Stripe'] as $mode)
                                            <option value="{{ $mode }}" {{ $row->payment_method === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Status</label>
                                    <select name="payment_status" class="form-select rounded-3" required>
                                        @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                            <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="event_id" value="{{ $event->event_id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $row->transaction_id }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Date</label>
                                <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $row->donation_date }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donation Option / Purpose</label>
                                <input type="text" name="purpose" class="form-control rounded-3" value="{{ $row->purpose }}">
                                @if($options->count())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($options as $opt)
                                    <span class="option-chip" style="background:#faf5eb;border:1px solid #f0ece6;color:#7b6b5a;font-size:.75rem;padding:4px 12px;border-radius:40px;cursor:pointer;" onclick="appendOptionChipEc(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Details / Remarks</label>
                                <textarea name="remarks" rows="2" class="form-control rounded-3">{{ $row->remarks }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #C89B3C, #A67C2B); border:none;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach

        @foreach($rows->where('donation_type', 'guest') as $row)
        <div class="modal fade" id="editGuestDonationModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <form action="{{ route('admin.donations.updateGuest', $row->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Guest Donation</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Donor Full Name</label>
                                <input type="text" name="donor_name" class="form-control rounded-3" value="{{ $row->donor_name }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email</label>
                                    <input type="email" name="email" class="form-control rounded-3" value="{{ $row->email }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Mobile</label>
                                    <input type="text" name="mobile" class="form-control rounded-3" value="{{ $row->mobile }}">
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Donation Amount</label>
                                    <input type="number" step="0.01" name="amount" class="form-control rounded-3" value="{{ $row->amount }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Donation Date</label>
                                    <input type="date" name="donation_date" class="form-control rounded-3" value="{{ $row->donation_date }}" required>
                                </div>
                            </div>
                            <input type="hidden" name="event_id" value="{{ $event->event_id }}">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Purpose</label>
                                <input type="text" name="purpose" class="form-control rounded-3" value="{{ $row->purpose }}" required>
                                @if($options->count())
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($options as $opt)
                                    <span class="option-chip" style="background:#faf5eb;border:1px solid #f0ece6;color:#7b6b5a;font-size:.75rem;padding:4px 12px;border-radius:40px;cursor:pointer;" onclick="appendOptionChipEc(this, 'input[name=purpose]')">{{ $opt->label }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Details / Dedication</label>
                                <textarea name="purpose_details" rows="2" class="form-control rounded-3">{{ $row->purpose_details }}</textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Method</label>
                                    <select name="payment_method" class="form-select rounded-3" required>
                                        @foreach(['Cash', 'UPI', 'Bank', 'EFT Terminal', 'Stripe'] as $method)
                                            <option value="{{ $method }}" {{ $row->payment_method === $method ? 'selected' : '' }}>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Payment Status</label>
                                    <select name="payment_status" class="form-select rounded-3" required>
                                        @foreach(['Paid', 'Pending', 'Cancelled', 'Failed'] as $status)
                                            <option value="{{ $status }}" {{ $row->payment_status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control rounded-3" value="{{ $row->transaction_id }}">
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                            <button type="submit" class="btn btn-primary text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #2a6fdb, #548ee8); border:none;">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    @endif

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        function appendOptionChipEc(chipEl, selector) {
            const scope = chipEl.closest('.modal-body') || document;
            const input = scope.querySelector(selector);
            if (!input) { return; }
            const label = chipEl.textContent.trim();
            const existing = input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            if (!existing.includes(label)) { existing.push(label); }
            input.value = existing.join(', ');
        }

        // Theme Colours: the <input type="color"> swatch is just a friendlier way to fill in
        // the real text field that actually gets submitted — typing a hex code directly into
        // the text field still works on its own.
        document.querySelectorAll('[data-color-for]').forEach(function (swatch) {
            const textField = document.getElementById(swatch.dataset.colorFor);
            if (!textField) { return; }
            swatch.addEventListener('input', function () { textField.value = swatch.value; });
        });
        const themeEnabledSwitch = document.getElementById('themeEnabledSwitch');
        if (themeEnabledSwitch) {
            themeEnabledSwitch.addEventListener('change', function () {
                document.getElementById('themeColorFields').style.display = this.checked ? '' : 'none';
            });
        }

        // Sidebar / pane switching — sidebar links, the "View All Donations" button, and the
        // "View Pending" button all just switch which console-pane is visible.
        function switchPane(paneId) {
            document.querySelectorAll('[data-pane]').forEach(function (el) {
                el.classList.toggle('active', el.dataset.pane === paneId && el.classList.contains('sidebar-link'));
            });
            document.querySelectorAll('.console-pane').forEach(function (p) { p.classList.toggle('active', p.id === paneId); });
            closeSidebarDrawer();
        }
        document.querySelectorAll('[data-pane]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (this.tagName === 'A') { return; }
                e.preventDefault();
                switchPane(this.dataset.pane);
            });
        });

        // Sidebar drawer for tablet/narrow screens.
        const sidebar = document.getElementById('appSidebar');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        const sidebarToggle = document.getElementById('sidebarToggle');
        function closeSidebarDrawer() { sidebar.classList.remove('open'); sidebarBackdrop.classList.remove('show'); }
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('open');
                sidebarBackdrop.classList.toggle('show');
            });
        }
        sidebarBackdrop.addEventListener('click', closeSidebarDrawer);

        // The Settings and Event Coordinators forms are plain full-page POST/redirects (not
        // AJAX), so the client-side "which pane is active" state would otherwise reset back
        // to New Donation after saving. Remember the pane across that one navigation via
        // localStorage.
        document.querySelectorAll('#pane-settings form').forEach(function (form) {
            form.addEventListener('submit', function () {
                try { localStorage.setItem('consoleActivePane', 'pane-settings'); } catch (e) {}
            });
        });
        document.querySelectorAll('#pane-coordinators form').forEach(function (form) {
            form.addEventListener('submit', function () {
                try { localStorage.setItem('consoleActivePane', 'pane-coordinators'); } catch (e) {}
            });
        });
        document.querySelectorAll('#pane-eftpos form').forEach(function (form) {
            form.addEventListener('submit', function () {
                try { localStorage.setItem('consoleActivePane', 'pane-eftpos'); } catch (e) {}
            });
        });
        (function restoreActivePane() {
            let savedPane = null;
            try { savedPane = localStorage.getItem('consoleActivePane'); } catch (e) {}
            if (savedPane && document.getElementById(savedPane)) {
                switchPane(savedPane);
                try { localStorage.removeItem('consoleActivePane'); } catch (e) {}
            }
        })();

        // Live clock in the topbar.
        function tickClock() {
            const now = new Date();
            const dateEl = document.getElementById('clockDate');
            const timeEl = document.getElementById('clockTime');
            if (dateEl) { dateEl.textContent = now.toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }); }
            if (timeEl) { timeEl.textContent = now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }); }
        }
        tickClock();
        setInterval(tickClock, 30000);

        // Fullscreen — only the explicit topbar button triggers it; no tap-anywhere fallback.
        function goFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            }
        }
        document.getElementById('fullscreenBtn').addEventListener('click', function () {
            if (!document.fullscreenElement) { goFullscreen(); } else { document.exitFullscreen(); }
        });

        // Settings: show the per-event payment-method checkboxes only when overriding the
        // global defaults.
        const useGlobalMethodsCheckbox = document.getElementById('settingsUseGlobalMethods');
        const paymentMethodsList = document.getElementById('settingsPaymentMethodsList');
        if (useGlobalMethodsCheckbox && paymentMethodsList) {
            useGlobalMethodsCheckbox.addEventListener('change', function () {
                paymentMethodsList.style.display = this.checked ? 'none' : 'block';
            });
        }

        // Settings category navigation — same sidebar-nav + categorized-panel pattern as
        // admin/settings.
        (function () {
            const navLinks = document.querySelectorAll('.settings-nav-link');
            const panels = document.querySelectorAll('.settings-panel');
            navLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    navLinks.forEach(function (l) { l.classList.remove('active'); });
                    panels.forEach(function (p) { p.classList.remove('active'); });
                    this.classList.add('active');
                    const target = document.querySelector('[data-panel-content="' + this.dataset.panel + '"]');
                    if (target) { target.classList.add('active'); }
                });
            });
        })();

        @if($canAddDonation)
        const DEVOTEES = @json($devotees);
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($effectivePaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_DEVOTEE_URL = @json(route('admin.events.console.storeDevotee', $event->event_id));
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EVENT_ID = {{ $event->event_id }};
        const QUICK_AMOUNTS = [101, 501, 1001, 2001];
        const REQUIRE_EMAIL = @json((bool) $event->require_donor_email);
        const REQUIRE_MOBILE = @json((bool) $event->require_donor_mobile);
        const CURRENCY_CODE = @json($temple['currency'] ?? '');
        const EFT_CHARGE_STATUS_URL_BASE = @json(url('/admin/eft/charge/status'));
        const EFT_CHARGE_CANCEL_URL_BASE = @json(url('/admin/eft/charge/cancel'));
        const EFT_REFUND_URL_BASE = @json(url('/admin/events/' . $event->event_id . '/eft/refund'));

        let qeMode = 'guest';
        const toggleDevoteeBtn = document.getElementById('qeToggleDevotee');
        const toggleGuestBtn = document.getElementById('qeToggleGuest');
        const devoteeFields = document.getElementById('qeDevoteeFields');
        const guestFields = document.getElementById('qeGuestFields');

        toggleDevoteeBtn.addEventListener('click', function () {
            qeMode = 'devotee';
            toggleDevoteeBtn.classList.add('active');
            toggleGuestBtn.classList.remove('active');
            devoteeFields.style.display = '';
            guestFields.style.display = 'none';
        });
        toggleGuestBtn.addEventListener('click', function () {
            qeMode = 'guest';
            toggleGuestBtn.classList.add('active');
            toggleDevoteeBtn.classList.remove('active');
            guestFields.style.display = '';
            devoteeFields.style.display = 'none';
        });

        // Payment method select, respecting the configured enabled list.
        const paymentSelect = document.getElementById('qePaymentMethod');
        (ENABLED_PAYMENT_METHODS.length ? ENABLED_PAYMENT_METHODS : ['Cash']).forEach(function (m) {
            const opt = document.createElement('option');
            opt.value = m === 'Bank Transfer' ? 'Bank Transfer' : m;
            opt.textContent = m;
            paymentSelect.appendChild(opt);
        });

        // Devotee search combobox — client-side filter over a pre-loaded array.
        const devoteeSearch = document.getElementById('qeDevoteeSearch');
        const devoteeResults = document.getElementById('qeDevoteeResults');
        const devoteeIdInput = document.getElementById('qeDevoteeId');

        devoteeSearch.addEventListener('input', function () {
            devoteeIdInput.value = '';
            const q = this.value.trim().toLowerCase();
            if (q.length < 2) { devoteeResults.classList.remove('show'); return; }
            const matches = DEVOTEES.filter(function (d) {
                return (d.name && d.name.toLowerCase().includes(q))
                    || (d.email && d.email.toLowerCase().includes(q))
                    || (d.mobile && d.mobile.toLowerCase().includes(q));
            }).slice(0, 15);
            if (!matches.length) { devoteeResults.classList.remove('show'); return; }
            devoteeResults.innerHTML = matches.map(function (d) {
                return '<div class="devotee-combobox-item" data-id="' + d.devotee_id + '" data-name="' + escapeHtmlQe(d.name) + '">'
                    + '<div class="fw-semibold">' + escapeHtmlQe(d.name) + '</div>'
                    + '<div class="text-muted small">' + escapeHtmlQe(d.email || '') + (d.mobile ? ' · ' + escapeHtmlQe(d.mobile) : '') + '</div></div>';
            }).join('');
            devoteeResults.classList.add('show');
        });
        devoteeResults.addEventListener('click', function (e) {
            const item = e.target.closest('.devotee-combobox-item');
            if (!item) { return; }
            devoteeIdInput.value = item.dataset.id;
            devoteeSearch.value = item.dataset.name;
            devoteeResults.classList.remove('show');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.devotee-combobox-wrap')) { devoteeResults.classList.remove('show'); }
        });

        function escapeHtmlQe(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // Amount fields use type="text" + inputmode="decimal" rather than type="number" — a
        // plain type="number" input silently reports an empty .value (not the text visible
        // on screen) whenever the browser's own numeric grammar rejects what was typed, which
        // is exactly what could produce "Enter a valid amount" even though a number was
        // clearly entered. Sanitizing on input (digits + at most one decimal point) keeps the
        // same numeric-only behaviour without that failure mode.
        function sanitizeDecimalInputQe(el) {
            let v = el.value.replace(/[^0-9.]/g, '');
            const firstDot = v.indexOf('.');
            if (firstDot !== -1) {
                v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
            }
            el.value = v;
        }
        function bindDecimalSanitizerQe(el) {
            el.addEventListener('input', function () { sanitizeDecimalInputQe(el); });
        }

        const amountInput = document.getElementById('qeAmount');
        const quickAmountsRow = document.getElementById('qeQuickAmounts');
        const tiersWrap = document.getElementById('qeTiersWrap');
        const simpleAmountWrap = document.getElementById('qeSimpleAmountWrap');
        const tiersContainer = document.getElementById('qeTiers');
        const tierTotalDisplay = document.getElementById('qeTierTotal');
        let selections = [];
        let purposeValue = 'Event Donation';

        if (EVENT_OPTIONS.length) {
            // Event has configured donation types — a multi-select checkbox list (same
            // pattern as admin/manage-donations), summing into a computed Total.
            tiersWrap.style.display = 'block';
            simpleAmountWrap.style.display = 'none';

            // A single option has nothing to pick between — auto-select it with no checkbox
            // to click, rather than making the user check the one and only box.
            const singleOption = EVENT_OPTIONS.length === 1;

            let html = '';
            EVENT_OPTIONS.forEach(function (opt, idx) {
                const hasAmount = opt.amount !== null;
                html += '<div class="donation-tier-option' + (singleOption ? ' single-option selected' : '') + '" data-idx="' + idx + '">'
                    + '<label><input type="checkbox" class="tier-cb" data-idx="' + idx + '"' + (singleOption ? ' checked' : '') + '>'
                    + '<span><strong>' + escapeHtmlQe(opt.label) + '</strong><br><span class="text-muted small">'
                    + (hasAmount ? (CURRENCY_CODE + ' ' + opt.amount.toFixed(2) + (opt.allow_quantity ? ' each' : '')) : 'Any amount')
                    + '</span></span></label>'
                    + (opt.allow_quantity ? '<input type="number" min="1" value="1" class="tier-qty" style="' + (singleOption ? '' : 'display:none;') + '">' : '')
                    + (!hasAmount ? '<div class="d-flex flex-column"><input type="text" inputmode="decimal" placeholder="Amount" class="tier-free">'
                        + '<div class="tier-free-quick-amounts"></div></div>' : '')
                    + '</div>';
            });
            tiersContainer.innerHTML = html;
            tiersContainer.querySelectorAll('.tier-free').forEach(bindDecimalSanitizerQe);

            // Quick-amount mini chips for each free-amount tier.
            tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                const quickWrap = row.querySelector('.tier-free-quick-amounts');
                if (!quickWrap) { return; }
                const freeInput = row.querySelector('.tier-free');
                const cb = row.querySelector('.tier-cb');
                QUICK_AMOUNTS.forEach(function (amt) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'quick-amount-btn';
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
                tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                    const idx = row.dataset.idx;
                    const cb = row.querySelector('.tier-cb');
                    const qtyInput = row.querySelector('.tier-qty');
                    const freeInput = row.querySelector('.tier-free');
                    if (qtyInput) { qtyInput.style.display = cb.checked ? 'inline-block' : 'none'; }
                    row.classList.toggle('selected', cb.checked);
                    if (!cb.checked) { return; }
                    const opt = EVENT_OPTIONS[idx];
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
            }

            tiersContainer.addEventListener('change', recalcTiers);
            tiersContainer.addEventListener('input', recalcTiers);
            if (singleOption) { recalcTiers(); }
        } else {
            // No donation types configured for this event — a plain, compact amount field
            // with quick-amount presets; typing directly into the field covers any custom
            // value, so there's no separate "Custom Amount" control.
            tiersWrap.style.display = 'none';
            simpleAmountWrap.style.display = 'block';
            bindDecimalSanitizerQe(amountInput);

            QUICK_AMOUNTS.forEach(function (amt) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'quick-amount-btn';
                btn.textContent = '$' + amt.toLocaleString();
                btn.addEventListener('click', function () {
                    amountInput.value = amt.toFixed(2);
                    amountInput.dispatchEvent(new Event('input'));
                    quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                });
                quickAmountsRow.appendChild(btn);
            });
            amountInput.addEventListener('input', function () {
                quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) {
                    b.classList.toggle('active', parseFloat(b.textContent.replace(/[^0-9.]/g, '')) === parseFloat(amountInput.value));
                });
            });
            purposeValue = 'Event Donation';
        }

        function showToast(message, isError) {
            const toast = document.getElementById('qeToast');
            toast.textContent = message;
            toast.classList.toggle('error', !!isError);
            toast.style.display = 'block';
            setTimeout(function () { toast.style.display = 'none'; }, 2500);
        }

        const donationDateInput = document.getElementById('qeDonationDate');
        const donationDateDevoteeInput = document.getElementById('qeDonationDateDevotee');
        const paymentStatusSelect = document.getElementById('qePaymentStatus');

        function resetQuickEntry() {
            devoteeSearch.value = '';
            devoteeIdInput.value = '';
            document.getElementById('qeGuestName').value = '';
            document.getElementById('qeGuestEmail').value = '';
            document.getElementById('qeGuestMobile').value = '';
            document.getElementById('qeTransactionId').value = '';
            document.getElementById('qeDetails').value = '';
            amountInput.value = '';
            quickAmountsRow.querySelectorAll('.quick-amount-btn').forEach(function (b) { b.classList.remove('active'); });
            if (tiersContainer) {
                // A single-option row has no checkbox to click — it stays permanently
                // selected across a reset, same as it starts out.
                tiersContainer.querySelectorAll('.tier-cb').forEach(function (cb) {
                    if (!cb.closest('.donation-tier-option').classList.contains('single-option')) { cb.checked = false; }
                });
                tiersContainer.querySelectorAll('.tier-free').forEach(function (i) { i.value = ''; });
                tiersContainer.querySelectorAll('.donation-tier-option').forEach(function (row) {
                    if (!row.classList.contains('single-option')) { row.classList.remove('selected'); }
                });
                if (tierTotalDisplay) { tierTotalDisplay.textContent = CURRENCY_CODE + ' 0.00'; }
            }
            const today = new Date().toISOString().slice(0, 10);
            donationDateInput.value = today;
            donationDateDevoteeInput.value = today;
            paymentStatusSelect.value = 'Paid';
            selections = [];
            purposeValue = 'Event Donation';
        }
        resetQuickEntry();
        document.getElementById('qeResetBtn').addEventListener('click', resetQuickEntry);

        document.getElementById('qeSaveBtn').addEventListener('click', function () {
            const amount = parseFloat((amountInput.value || '').trim());
            if (!amount || amount <= 0) { showToast('Enter a valid amount.', true); return; }

            const btn = this;
            btn.disabled = true;

            const body = new URLSearchParams();
            body.set('event_id', EVENT_ID);
            body.set('amount', amount.toFixed(2));
            body.set('transaction_id', document.getElementById('qeTransactionId').value);
            body.set('selections_json', JSON.stringify(selections));
            body.set('payment_status', paymentStatusSelect.value);

            let url;
            if (qeMode === 'devotee') {
                if (!devoteeIdInput.value) { showToast('Search and select a devotee first.', true); btn.disabled = false; return; }
                url = STORE_DEVOTEE_URL;
                body.set('devotee_id', devoteeIdInput.value);
                body.set('donation_date', donationDateDevoteeInput.value || new Date().toISOString().slice(0, 10));
                // Devotee donations only accept Cash/UPI/Bank Transfer/Cheque.
                body.set('payment_mode', paymentSelect.value);
                body.set('purpose', purposeValue);
                body.set('remarks', document.getElementById('qeDetails').value);
            } else {
                const name = document.getElementById('qeGuestName').value.trim();
                if (!name) { showToast('Enter the donor name.', true); btn.disabled = false; return; }
                const guestEmail = document.getElementById('qeGuestEmail').value.trim();
                if (REQUIRE_EMAIL && !guestEmail) { showToast('Enter the donor email.', true); btn.disabled = false; return; }
                const guestMobile = document.getElementById('qeGuestMobile').value.trim();
                if (REQUIRE_MOBILE && !guestMobile) { showToast('Enter the donor mobile number.', true); btn.disabled = false; return; }
                url = STORE_GUEST_URL;
                body.set('donor_name', name);
                body.set('donation_date', donationDateInput.value || new Date().toISOString().slice(0, 10));
                body.set('email', guestEmail);
                body.set('mobile', guestMobile);
                // Guest donations only accept Cash/UPI/Bank — translate the shared payment
                // method list's "Bank Transfer" label to the value this route actually accepts.
                body.set('payment_method', paymentSelect.value === 'Bank Transfer' ? 'Bank' : paymentSelect.value);
                body.set('purpose', purposeValue);
                body.set('purpose_details', document.getElementById('qeDetails').value);
            }

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    btn.disabled = false;
                    if (result.status >= 200 && result.status < 300 && result.data.success) {
                        showToast(result.data.message || 'Saved.');
                        resetQuickEntry();
                    } else {
                        showToast(result.data.message || 'Failed to save.', true);
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    showToast('Network error — please try again.', true);
                });
        });

        @endif

        @if($canEditEvent)
        // ---------- EFTPOS / Linkly accreditation pane ----------
        function copyEftRef(txnId) {
            const ref = document.getElementById('txnref-' + txnId).textContent;
            const time = document.getElementById('txntime-' + txnId).textContent;
            const text = ref + '\t' + time;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { showToast('Copied: ' + text); }, function () { showToast('Could not copy.', true); });
            } else {
                showToast('Copy not supported in this browser.', true);
            }
        }

        function checkEftStatus(sessionId) {
            showToast('Checking status…');
            fetch(EFT_CHARGE_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function () { location.reload(); })
                .catch(function () { showToast('Could not check status — network error.', true); });
        }

        let eftRefundTransactionId = null;
        let eftRefundPollCancelled = false;
        const eftRefundModalEl = document.getElementById('eftRefundModal');
        const eftRefundBsModal = eftRefundModalEl ? new bootstrap.Modal(eftRefundModalEl) : null;

        function openEftRefundModal(transactionId, amount) {
            eftRefundTransactionId = transactionId;
            eftRefundPollCancelled = false;
            document.getElementById('eftRefundAmount').value = Number(amount).toFixed(2);
            document.getElementById('eftRefundFormArea').style.display = '';
            document.getElementById('eftRefundStatusArea').style.display = 'none';
            document.getElementById('eftRefundConfirmBtn').style.display = '';
            document.getElementById('eftRefundConfirmBtn').disabled = false;
            if (eftRefundBsModal) { eftRefundBsModal.show(); }
        }

        function setEftRefundStatus(line1, line2) {
            document.getElementById('eftRefundStatusLine1').textContent = line1 || '';
            document.getElementById('eftRefundStatusLine2').textContent = line2 || '';
        }

        document.getElementById('eftRefundConfirmBtn') && document.getElementById('eftRefundConfirmBtn').addEventListener('click', function () {
            const amount = parseFloat(document.getElementById('eftRefundAmount').value);
            if (!amount || amount <= 0) { showToast('Enter a valid refund amount.', true); return; }
            if (!confirm('Refund ' + CURRENCY_CODE + ' ' + amount.toFixed(2) + ' on the terminal now?')) { return; }

            document.getElementById('eftRefundFormArea').style.display = 'none';
            document.getElementById('eftRefundStatusArea').style.display = '';
            document.getElementById('eftRefundConfirmBtn').style.display = 'none';
            setEftRefundStatus('Starting refund…', '');
            eftRefundPollCancelled = false;

            const clientRef = 'refund-' + eftRefundTransactionId + '-' + Date.now();
            const body = new URLSearchParams();
            body.set('amount', amount.toFixed(2));
            body.set('client_ref', clientRef);

            fetch(EFT_REFUND_URL_BASE + '/' + encodeURIComponent(eftRefundTransactionId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { status: res.status, data: data }; }); })
                .then(function (result) {
                    if (!(result.status >= 200 && result.status < 300 && result.data.success)) {
                        setEftRefundStatus('Could not start refund', result.data.message || '');
                        showToast(result.data.message || 'Could not start the refund.', true);
                        return;
                    }
                    pollEftRefund(result.data.session_id, Date.now());
                })
                .catch(function () {
                    setEftRefundStatus('Network error', 'Please try again.');
                    showToast('Could not reach the EFT terminal — please try again.', true);
                });
        });

        function pollEftRefund(sessionId, startedAt) {
            if (eftRefundPollCancelled) { return; }
            if (Date.now() - startedAt > 180000) {
                setEftRefundStatus('Timed out', 'Check Transaction Status before retrying.');
                return;
            }

            fetch(EFT_CHARGE_STATUS_URL_BASE + '/' + encodeURIComponent(sessionId) + '?event_id=' + encodeURIComponent(EVENT_ID), { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (eftRefundPollCancelled) { return; }
                    if (data.display && data.display.length) { setEftRefundStatus(data.display[0], data.display[1] || ''); }
                    if (!data.done) {
                        setTimeout(function () { pollEftRefund(sessionId, startedAt); }, 1200);
                        return;
                    }
                    if (data.success) {
                        setEftRefundStatus('REFUND APPROVED', data.auth_code ? 'Auth ' + data.auth_code : '');
                        showToast('Refund approved.');
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        setEftRefundStatus('REFUND ' + (data.payment_status || 'NOT COMPLETED').toUpperCase(), data.message || '');
                        showToast(data.message || 'Refund was not completed.', true);
                        document.getElementById('eftRefundConfirmBtn').style.display = '';
                    }
                })
                .catch(function () {
                    setTimeout(function () { pollEftRefund(sessionId, startedAt); }, 1200);
                });
        }

        if (eftRefundModalEl) {
            eftRefundModalEl.addEventListener('hidden.bs.modal', function () {
                eftRefundPollCancelled = true;
            });
        }
        @endif
    </script>
</body>
</html>
