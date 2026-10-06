@php
    $lastKnown = $terminal->lastKnownStatus();
    $detailId = 'terminal-detail-' . $terminal->id;
    $isMx51 = $terminal->provider === 'cba_sci';
    $paired = $terminal->isPairedFor($linklyMode);
    $mode = $isMx51 ? $cbaSciMode : $linklyMode;
    // Expanded by default right after you've just added, paired, tested or unpaired THIS
    // specific terminal — see the various controller actions' ->with('expandTerminalId', ...)
    // — so the result of what you just did is immediately visible instead of collapsed away.
    $startExpanded = (int) session('expandTerminalId') === $terminal->id;
    // 'New Terminal' is addAndPair()'s own placeholder label when no pairing nickname was ever
    // given (see EftTerminalController::addAndPair()) — the TID is more useful to show in that
    // case than a generic name nobody chose.
    $hasRealLabel = $terminal->label && $terminal->label !== 'New Terminal';
@endphp
<div class="terminal-card{{ $paired ? '' : ' terminal-card-inactive' }}">
    <div class="terminal-summary-row" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="{{ $startExpanded ? 'true' : 'false' }}" aria-controls="{{ $detailId }}">
        <span class="terminal-icon"><img src="{{ asset('images/eft_terminal_icon.png') }}" alt=""></span>

        <div class="terminal-summary-main">
            <div class="terminal-summary-name">
                {{-- The pairing nickname IS the label now — if none was ever given, the
                     physical terminal's own TID is more useful here than a generic name. --}}
                @if($isMx51 && !$hasRealLabel && $terminal->sci_tid)
                <strong>TID: {{ $terminal->sci_tid }}</strong>
                @else
                <strong>{{ $terminal->label }}</strong>
                @if($isMx51)
                @if($hasRealLabel && $terminal->sci_tid)<span class="text-muted small">(TID: {{ $terminal->sci_tid }})</span>@endif
                @else
                <span class="text-muted small">({{ $terminal->key }})</span>
                @endif
                @endif
            </div>
            <div class="terminal-summary-meta">{{ $isMx51 ? 'TID: ' . ($terminal->sci_tid ?: '—') : 'Key: ' . $terminal->key }} &nbsp;|&nbsp; Provider: {{ $isMx51 ? 'SCI' : 'Linkly Cloud' }} &nbsp;|&nbsp; Mode: {{ strtoupper($mode) }}</div>
            <div class="terminal-summary-badges">
                <span class="badge-pill badge-provider">{{ $isMx51 ? 'SCI' : 'LINKLY CLOUD' }}</span>
                @if($terminal->is_default)<span class="badge-pill badge-info">Default</span>@endif
                <span class="badge-pill {{ $paired ? 'badge-ok' : 'badge-bad' }}">{{ $paired ? 'Paired' : 'Not Paired' }}</span>
            </div>
        </div>

        <div class="terminal-summary-status">
            <div class="status-row">
                <i class="bi bi-broadcast"></i> <span class="status-label">Connection</span>
                @if($lastKnown['state'] === 'online')
                <span class="badge-pill badge-ok">Online</span>
                @elseif($lastKnown['state'] === 'offline')
                <span class="badge-pill badge-bad">Offline</span>
                @else
                <span class="badge-pill badge-warn">Not checked</span>
                @endif
            </div>
            <div class="status-row">
                <i class="bi bi-clock-history"></i> <span class="status-label">Last seen</span>
                <span class="text-muted small">{{ $lastKnown['at'] ? $lastKnown['at']->diffForHumans() : 'Not available' }}</span>
            </div>
            <div class="status-row">
                <i class="bi bi-check-circle"></i> <span class="status-label">Status</span>
                <span class="badge-pill {{ $paired ? 'badge-ok' : 'badge-bad' }}">{{ $paired ? 'Usable' : 'Not available' }}</span>
            </div>
        </div>

        {{-- The row itself already toggles this card's details (data-bs-toggle above) — no
             separate Settings button needed. "Set as default"/"Remove" live in the "…" menu;
             anyone trusted to add/pair a terminal is trusted to change or remove any terminal
             in the registry too, since it's still one shared, global list, not scoped per
             event/Tickets (see EftTerminalAccess's own docblock). --}}
        @if(($canManageRegistryLevel ?? false) && !$terminal->is_default)
        <div class="terminal-summary-actions" onclick="event.stopPropagation();">
            <div class="dropdown">
                <button type="button" class="btn-terminal-more" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <form action="{{ route('admin.eft-terminals.setDefault', $terminal) }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item">Set as default</button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('admin.eft-terminals.destroy', $terminal) }}" method="POST" onsubmit="return confirm('Remove this terminal?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger">Remove</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
        @endif
    </div>

    {{-- Just the pairing controls — everything else here (label, TID/key, provider, mode) is
         already shown on the summary row above, and Set default/Remove live in its "…" menu. --}}
    <div class="collapse{{ $startExpanded ? ' show' : '' }}" id="{{ $detailId }}">
        <div class="terminal-detail-body">
            @if($isMx51)
            @include('admin.partials.cba-sci-pairing', ['terminal' => $terminal])
            @else
            <form action="{{ route('admin.eft.pair') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Pairing Code</label>
                    <input type="text" name="pair_code" class="form-control rounded-3" placeholder="6-digit code from the terminal" maxlength="10" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">{{ $paired ? 'Re-pair' : 'Pair' }}</button>
                </div>
            </form>
            @if($paired)
            <form action="{{ route('admin.eft-terminals.checkConnection', $terminal) }}" method="POST" class="mt-2">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Check Connection</button>
            </form>
            <p class="text-muted small mt-2 mb-0" style="font-size:0.78rem;">Sends a real Logon to the terminal — it will visibly respond.</p>
            @endif
            @endif
        </div>
    </div>
</div>
