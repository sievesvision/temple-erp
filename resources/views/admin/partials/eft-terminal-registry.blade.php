{{-- The EFT Terminal registry — @include'd verbatim by the standalone EFT Terminal Settings
     page, both consoles' own EFT Terminal Settings pane, and Admin Settings' EFT Terminal
     panel, so pairing/adding/removing a terminal is implemented exactly once no matter where
     it's reached from. Expects: $activeTerminals, $inactiveTerminals, $linklyMode,
     $cbaSciMode, $canManageRegistryLevel, $isSystemAdmin, $allOperational — the first five
     mirror EftTerminalController::index()'s own variables exactly; $isSystemAdmin (literal
     Admin role, stricter than $canManageRegistryLevel) gates Set Default/Remove Terminal the
     same way eft-terminal-card.blade.php always has. The host page must link
     css/eft-terminal-registry.css once and already define the --maroon/--gold/--cream/
     --white/--border/--text-primary/--text-secondary/--serif tokens this relies on (every
     current host already does, as they all share the same temple-branding palette) and
     js/eft-terminal-registry.js once (drives the Add Terminal wizard — see
     eft-terminal-add-wizard.blade.php). The top-right button and its data-*-url attributes
     live here, not in that partial — opening the wizard hides both #eftTerminalsList and
     #eftRegistryBottomSettings and relabels the button itself "Back to Terminals" (same
     element throughout, never removed — only its label/icon and click behaviour flip), so the
     page shows either the full registry or the wizard, never a mix of the two.
     Every per-terminal card action (pair/test/unpair/rename/etc.) still redirects with plain
     redirect()->back(), so it always lands back on whichever of those pages it was actually
     submitted from — nothing here needs to know which one that is. The Add Terminal wizard is
     the one exception: it's AJAX-driven end to end (see js/eft-terminal-registry.js) and never
     navigates away, refreshing only the #eftTerminalsList container above on success.
     "Transaction Limits" and "Receipt Printing & Signature" (System-Admin-only, wrapped in
     #eftRegistryBottomSettings) render last, below the terminal list — they're account-wide
     defaults an admin sets up once, not something that needs top billing over the terminals
     themselves, and not part of adding a terminal either. --}}
<div class="eft-terminals-top-row">
    {{-- Required on every EFTPOS-related settings surface (Linkly accreditation requirement
         1.4) — kept even though the old explanatory banner it used to sit inside was removed
         as developer-only noise. --}}
    <a href="{{ route('eft.pairing-guide') }}" target="_blank" class="eft-pairing-guide-link"><i class="bi bi-question-circle me-1"></i>Pairing Guide</a>
    @if($canManageRegistryLevel)
    <button type="button" class="btn-add-terminal" id="addTerminalToggleBtn"
            data-add-url="{{ route('admin.eft-terminals.addAndPair') }}"
            data-test-url="{{ route('admin.cba-sci.test') }}"
            data-cancel-new-url-base="{{ url('/admin/eft-terminals') }}"
            data-refresh-url="{{ route('admin.eft-terminals.index') }}"
            data-csrf="{{ csrf_token() }}">
        <i class="bi bi-plus-lg"></i> Add New Terminal
    </button>
    @endif
</div>

<div id="eftTerminalsList">
@if($activeTerminals->isEmpty() && $inactiveTerminals->isEmpty())
<p class="text-muted">No terminals registered yet — add one above.</p>
@endif

@if($activeTerminals->isNotEmpty())
<div class="eft-section-heading-row">
    <div class="eft-section-heading">Active Terminals <span class="eft-section-count count-ok">{{ $activeTerminals->count() }}</span></div>
    @if($allOperational)
    <span class="eft-operational-flag"><span class="dot"></span> All systems operational</span>
    @endif
</div>
@foreach($activeTerminals as $terminal)
    @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin, 'canManageRegistryLevel' => $canManageRegistryLevel])
@endforeach
@endif

@if($inactiveTerminals->isNotEmpty())
<div class="eft-section-heading-row">
    <div class="eft-section-heading">Inactive Terminals <span class="eft-section-count">{{ $inactiveTerminals->count() }}</span></div>
</div>
@foreach($inactiveTerminals as $terminal)
    @include('admin.partials.eft-terminal-card', ['terminal' => $terminal, 'linklyMode' => $linklyMode, 'cbaSciMode' => $cbaSciMode, 'isSystemAdmin' => $isSystemAdmin, 'canManageRegistryLevel' => $canManageRegistryLevel])
@endforeach
@endif
</div>

@if($canManageRegistryLevel)
@include('admin.partials.eft-terminal-add-wizard')
@endif

@if($isSystemAdmin)
<div id="eftRegistryBottomSettings">
@include('admin.partials.eft-terminal-transaction-limits')
@include('admin.partials.eft-terminal-receipt-settings')
</div>
@endif
