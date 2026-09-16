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
        body { margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--cream); color: var(--text-primary); }
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
        .field-group > .field-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.95rem; pointer-events: none; }
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

        /* A small, compact amount box for the "no donation types configured" case. */
        .field-group.compact { max-width: 180px; }
        .field-group.compact input { padding: 8px 10px 8px 34px; font-size: 0.9rem; min-height: 36px; }
        .field-group.compact .field-icon { font-size: 0.85rem; }

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
        table.console-table { width: 100%; border-collapse: collapse; }
        table.console-table th, table.console-table td { padding: 12px 14px; text-align: left; font-size: 0.85rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
        table.console-table th { background: var(--cream); font-weight: 700; color: var(--text-secondary); text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.04em; position: sticky; top: 0; z-index: 5; }
        table.console-table td.col-name { white-space: normal; min-width: 140px; font-weight: 600; }
        table.console-table td.col-amount, table.console-table th.col-amount { text-align: right; font-variant-numeric: tabular-nums; }
        table.console-table td.col-amount.total { font-weight: 800; color: var(--text-primary); }
        table.console-table td.col-txn { max-width: 130px; overflow: hidden; text-overflow: ellipsis; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.78rem; color: var(--text-secondary); }
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
        .stat-tile .value { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); margin-top: 2px; overflow-wrap: break-word; }

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
                    @endif
                    <button type="button" class="sidebar-link {{ $canAddDonation ? '' : 'active' }}" data-pane="pane-table"><i class="bi bi-card-list"></i><span>All Donations</span></button>
                    @if($canEditEvent)
                    <button type="button" class="sidebar-link" data-pane="pane-settings"><i class="bi bi-gear-fill"></i><span>Settings</span></button>
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
                                    <td>
                                        @if($row->mobile)<div>{{ $row->mobile }}</div>@endif
                                        @if($row->email)<div class="text-muted" style="font-size:0.78rem;">{{ $row->email }}</div>@endif
                                        @if(!$row->mobile && !$row->email)—@endif
                                    </td>
                                    @foreach($options as $opt)
                                    <td class="col-amount">@if(($row->option_amounts[$opt->id] ?? 0) > 0){{ number_format($row->option_amounts[$opt->id], 2) }}@else — @endif</td>
                                    @endforeach
                                    <td class="col-amount">@if($row->other_amount > 0){{ number_format($row->other_amount, 2) }}@else — @endif</td>
                                    <td class="col-amount total">{{ number_format($row->amount, 2) }}</td>
                                    <td>{{ $row->payment_method }}</td>
                                    <td class="col-txn" title="{{ $row->transaction_id }}">{{ $row->transaction_id ?: '—' }}</td>
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
                                        <label class="field-label">Email (Optional)</label>
                                        <i class="bi bi-envelope field-icon"></i>
                                        <input type="email" id="qeGuestEmail" placeholder="example@email.com">
                                    </div>
                                    <div class="field-group">
                                        <label class="field-label">Mobile (Optional)</label>
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
                                    <input type="number" step="0.01" id="qeAmount" placeholder="0.00">
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
                            <input type="hidden" name="slug" value="{{ $event->slug }}">

                            <div class="section-title" style="margin-top:0;"><span class="icon-badge-sm"><i class="bi bi-info-circle-fill"></i></span>Event Details</div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">Event Name <span class="required">*</span></label>
                                    <i class="bi bi-tag field-icon"></i>
                                    <input type="text" name="event_name" value="{{ $event->event_name }}" required>
                                </div>
                            </div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">Description</label>
                                    <textarea name="description" rows="3">{{ $event->description }}</textarea>
                                </div>
                            </div>
                            <div class="field-row two-col">
                                <div class="field-group">
                                    <label class="field-label">Event Date <span class="required">*</span></label>
                                    <i class="bi bi-calendar3 field-icon"></i>
                                    <input type="date" name="event_date" value="{{ $event->event_date }}" required>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Location / Venue <span class="required">*</span></label>
                                    <i class="bi bi-geo-alt field-icon"></i>
                                    <input type="text" name="location" value="{{ $event->location }}" required>
                                </div>
                            </div>
                            <div class="checkbox-field">
                                <input type="checkbox" name="date_tbc" id="settingsDateTbc" value="1" {{ $event->date_tbc ? 'checked' : '' }}>
                                <label for="settingsDateTbc">Date to be confirmed<span class="checkbox-note">Shows "Date to be confirmed" publicly instead of the date above (still used internally for sorting).</span></label>
                            </div>
                            <div class="field-row two-col">
                                <div class="field-group">
                                    <label class="field-label">Start Time <span class="required">*</span></label>
                                    <i class="bi bi-clock field-icon"></i>
                                    <input type="time" name="start_time" value="{{ date('H:i', strtotime($event->start_time)) }}" required>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">End Time <span class="required">*</span></label>
                                    <i class="bi bi-clock-history field-icon"></i>
                                    <input type="time" name="end_time" value="{{ date('H:i', strtotime($event->end_time)) }}" required>
                                </div>
                            </div>
                            <div class="field-row two-col">
                                <div class="field-group">
                                    <label class="field-label">Status</label>
                                    <i class="bi bi-flag field-icon"></i>
                                    <select name="status">
                                        @foreach(['Upcoming', 'Ongoing', 'Completed', 'Cancelled'] as $status)
                                            <option value="{{ $status }}" {{ $event->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Coordinator Emails</label>
                                    <i class="bi bi-envelope field-icon"></i>
                                    <input type="text" name="coordinator_emails" value="{{ $event->coordinator_emails }}" placeholder="cc1@example.com, cc2@example.com">
                                </div>
                            </div>

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-window"></i></span>Public Page</div>
                            <div class="checkbox-field">
                                <input type="checkbox" name="show_donation_summary" id="settingsShowSummary" value="1" {{ $event->show_donation_summary ? 'checked' : '' }}>
                                <label for="settingsShowSummary">Show "amount raised so far" on the public event page</label>
                            </div>
                            <div class="checkbox-field">
                                <input type="checkbox" name="require_donor_contact_details" id="settingsRequireContact" value="1" {{ $event->require_donor_contact_details ? 'checked' : '' }}>
                                <label for="settingsRequireContact">Require donor name, email &amp; mobile on this event's donation form</label>
                            </div>

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-credit-card-fill"></i></span>Payment Methods</div>
                            @php $eventMethodsOverride = $event->paymentMethodsOverride(); @endphp
                            <div class="checkbox-field">
                                <input type="hidden" name="use_global_payment_methods" value="0">
                                <input type="checkbox" name="use_global_payment_methods" id="settingsUseGlobalMethods" value="1" {{ $eventMethodsOverride === null ? 'checked' : '' }}>
                                <label for="settingsUseGlobalMethods">Use the global donation settings<span class="checkbox-note">Uncheck to choose which payment methods are available for this event specifically (e.g. disable Stripe just for this event).</span></label>
                            </div>
                            <div id="settingsPaymentMethodsList" style="{{ $eventMethodsOverride === null ? 'display:none;' : '' }} margin-bottom:20px;">
                                <div class="row g-2">
                                    @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Stripe'] as $method)
                                    <div class="col-6 col-md-4">
                                        <div class="checkbox-field" style="margin-bottom:0;">
                                            <input type="checkbox" name="enabled_payment_methods[]" value="{{ $method }}" id="settingsMethod{{ strtolower(str_replace(' ', '', $method)) }}"
                                                {{ in_array($method, $eventMethodsOverride ?? $globalPaymentMethods, true) ? 'checked' : '' }}>
                                            <label for="settingsMethod{{ strtolower(str_replace(' ', '', $method)) }}">{{ $method }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="field-row two-col">
                                <div class="field-group">
                                    <label class="field-label">Header Image Path</label>
                                    <i class="bi bi-image field-icon"></i>
                                    <input type="text" name="header_image" value="{{ $event->header_image }}" placeholder="images/events/header.jpg">
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Flyer Image Path</label>
                                    <i class="bi bi-file-earmark-image field-icon"></i>
                                    <input type="text" name="flyer_image" value="{{ $event->flyer_image }}" placeholder="images/events/flyer.png">
                                </div>
                            </div>
                            <div class="field-row">
                                <div class="field-group">
                                    <label class="field-label">QR Code Image Path</label>
                                    <i class="bi bi-qr-code field-icon"></i>
                                    <input type="text" name="qr_code_image" value="{{ $event->qr_code_image }}" placeholder="images/events/qr.png">
                                </div>
                            </div>

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-cash-coin"></i></span>Donation Options</div>
                            @include('admin.partials.event-donation-options-fields', ['options' => $options, 'formSuffix' => 'settings'])

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-telephone-fill"></i></span>Public Contacts</div>
                            @include('admin.partials.event-contacts-fields', ['contacts' => $event->contactList(), 'formSuffix' => 'settings'])

                            <div class="section-title"><span class="icon-badge-sm"><i class="bi bi-images"></i></span>Gallery Images</div>
                            @include('admin.partials.event-gallery-fields', ['galleryImages' => $event->galleryImages(), 'formSuffix' => 'settings'])

                            <div class="form-actions">
                                <button type="submit" class="btn-save"><i class="bi bi-save2-fill me-2"></i>Save Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    </div>

    <div class="qe-toast" id="qeToast"></div>

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
                                        @foreach(['Cash', 'UPI', 'Bank Transfer', 'Cheque', 'Stripe'] as $mode)
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
                                        @foreach(['Cash', 'UPI', 'Bank', 'Stripe'] as $method)
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

        // The Settings form is a plain full-page POST/redirect (not AJAX), so the client-side
        // "which pane is active" state would otherwise reset back to New Donation after
        // saving. Remember the pane across that one navigation via localStorage.
        document.querySelectorAll('#pane-settings form').forEach(function (form) {
            form.addEventListener('submit', function () {
                try { localStorage.setItem('consoleActivePane', 'pane-settings'); } catch (e) {}
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

        @if($canAddDonation)
        const DEVOTEES = @json($devotees);
        const EVENT_OPTIONS = @json($eventOptionsForJs);
        const ENABLED_PAYMENT_METHODS = @json($effectivePaymentMethods);
        const CSRF_TOKEN = @json(csrf_token());
        const STORE_DEVOTEE_URL = @json(route('admin.events.console.storeDevotee', $event->event_id));
        const STORE_GUEST_URL = @json(route('admin.events.console.storeGuest', $event->event_id));
        const EVENT_ID = {{ $event->event_id }};
        const QUICK_AMOUNTS = [101, 501, 1001, 2001];
        const CURRENCY_CODE = @json($temple['currency'] ?? '');

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
                    + (!hasAmount ? '<div class="d-flex flex-column"><input type="number" min="0" step="0.01" placeholder="Amount" class="tier-free">'
                        + '<div class="tier-free-quick-amounts"></div></div>' : '')
                    + '</div>';
            });
            tiersContainer.innerHTML = html;

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
            const amount = parseFloat(amountInput.value);
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
                url = STORE_GUEST_URL;
                body.set('donor_name', name);
                body.set('donation_date', donationDateInput.value || new Date().toISOString().slice(0, 10));
                body.set('email', document.getElementById('qeGuestEmail').value);
                body.set('mobile', document.getElementById('qeGuestMobile').value);
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
    </script>
</body>
</html>
