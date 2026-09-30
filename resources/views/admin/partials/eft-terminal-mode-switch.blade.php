{{-- Sandbox/Live switch for each EFT provider — System-Admin-only (literal Admin role), since
     going live means every subsequent transaction on that provider's terminals moves real
     money. Lives only here on Admin Settings (moved off the EFT Terminal registry itself,
     which every console and an event-admin/ticket-admin controller can also reach — this
     stays a genuine System-Admin-only decision). Expects $linklyMode, $cbaSciMode; posts to
     EftTerminalController::updateMode(), which redirects back here via plain
     redirect()->back(). --}}
<div class="eftr-mode-switch-card">
    <div class="eftr-mode-switch-heading"><i class="bi bi-toggles me-1"></i>Environment — each provider switches independently</div>
    <div class="eftr-mode-switch-row">
        <span class="eftr-mode-switch-label">Linkly Cloud</span>
        <form action="{{ route('admin.eft-terminals.updateMode') }}" method="POST" class="eftr-mode-switch-form">
            @csrf
            <input type="hidden" name="provider" value="linkly">
            <div class="btn-group btn-group-sm" role="group">
                <button type="submit" name="mode" value="sandbox" class="btn {{ $linklyMode === 'sandbox' ? 'btn-secondary' : 'btn-outline-secondary' }}" {{ $linklyMode === 'sandbox' ? 'disabled' : '' }}>Sandbox</button>
                <button type="submit" name="mode" value="live" class="btn {{ $linklyMode === 'live' ? 'btn-danger' : 'btn-outline-danger' }}" {{ $linklyMode === 'live' ? 'disabled' : '' }} onclick="return confirm('Switch Linkly Cloud to LIVE mode? Every subsequent transaction will process a real card.');">Live</button>
            </div>
        </form>
    </div>
    <div class="eftr-mode-switch-row">
        <span class="eftr-mode-switch-label">mx51 Cloud</span>
        <form action="{{ route('admin.eft-terminals.updateMode') }}" method="POST" class="eftr-mode-switch-form">
            @csrf
            <input type="hidden" name="provider" value="cba_sci">
            <div class="btn-group btn-group-sm" role="group">
                <button type="submit" name="mode" value="sandbox" class="btn {{ $cbaSciMode === 'sandbox' ? 'btn-secondary' : 'btn-outline-secondary' }}" {{ $cbaSciMode === 'sandbox' ? 'disabled' : '' }}>Sandbox</button>
                <button type="submit" name="mode" value="live" class="btn {{ $cbaSciMode === 'live' ? 'btn-danger' : 'btn-outline-danger' }}" {{ $cbaSciMode === 'live' ? 'disabled' : '' }} onclick="return confirm('Switch mx51 Cloud to LIVE mode? Every subsequent transaction will process a real card.');">Live</button>
            </div>
        </form>
    </div>
</div>
