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
@endphp
<div class="terminal-card{{ $paired ? '' : ' terminal-card-inactive' }}">
    <div class="terminal-summary-row" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="{{ $startExpanded ? 'true' : 'false' }}" aria-controls="{{ $detailId }}">
        <span class="terminal-icon"><img src="{{ asset('images/eft_terminal_icon.png') }}" alt=""></span>

        <div class="terminal-summary-main">
            <div class="terminal-summary-name">
                <strong>{{ $terminal->label }}</strong>
                <span class="text-muted small">({{ $terminal->key }})</span>
            </div>
            <div class="terminal-summary-meta">Key: {{ $terminal->key }} &nbsp;|&nbsp; Provider: {{ $isMx51 ? 'mx51 Cloud' : 'Linkly Cloud' }} &nbsp;|&nbsp; Mode: {{ strtoupper($mode) }}</div>
            <div class="terminal-summary-badges">
                <span class="badge-pill badge-provider">{{ $isMx51 ? 'MX51 CLOUD' : 'LINKLY CLOUD' }}</span>
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

        <div class="terminal-summary-actions" onclick="event.stopPropagation();">
            <button type="button" class="btn-terminal-settings" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="{{ $startExpanded ? 'true' : 'false' }}" aria-controls="{{ $detailId }}">
                <i class="bi bi-gear"></i> Settings
            </button>
            @if($isSystemAdmin)
            <div class="dropdown">
                <button type="button" class="btn-terminal-more" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" class="dropdown-item" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}">Settings</button></li>
                    @if(!$terminal->is_default)
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
                    @endif
                </ul>
            </div>
            @endif
        </div>
    </div>

    <div class="collapse{{ $startExpanded ? ' show' : '' }}" id="{{ $detailId }}">
        <div class="terminal-detail-title"><i class="bi bi-credit-card-2-front-fill"></i> {{ $terminal->label }} <span class="text-muted fw-normal">— Settings</span></div>
        <div class="terminal-detail-body">

            <div class="terminal-detail-section">
                <div class="terminal-detail-heading">Terminal Details</div>
                <div class="terminal-detail-grid">
                    <div>
                        <span class="text-muted small">Label</span>
                        @if($canManageRegistryLevel ?? false)
                        {{-- Renamable — a terminal's label had no way to be changed before this,
                             which mattered in practice: "mx51 Certification Terminal" leaked
                             mx51's own name into a customer-facing error that embedded it. --}}
                        <form action="{{ route('admin.eft-terminals.update', $terminal) }}" method="POST" class="d-flex align-items-center gap-2 mt-1">
                            @csrf
                            <input type="text" name="label" value="{{ $terminal->label }}" class="form-control form-control-sm rounded-3" style="max-width: 220px;" maxlength="255" required>
                            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-3">Save</button>
                        </form>
                        @else
                        <br><strong>{{ $terminal->label }}</strong>
                        @endif
                    </div>
                    <div><span class="text-muted small">Unique Terminal Code</span><br><strong>{{ $terminal->key }}</strong></div>
                    <div><span class="text-muted small">Provider</span><br><strong>{{ $isMx51 ? 'mx51 Cloud' : 'Linkly Cloud' }}</strong></div>
                </div>
            </div>

            <div class="terminal-detail-section">
                <div class="terminal-detail-heading">Pairing</div>
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

            @if($isSystemAdmin && !$terminal->is_default)
            <div class="terminal-detail-section">
                <div class="terminal-detail-heading">Configuration</div>
                <form action="{{ route('admin.eft-terminals.setDefault', $terminal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Set as Default</button>
                </form>
            </div>
            @endif

            @if($isSystemAdmin && !$terminal->is_default)
            <div class="terminal-detail-section terminal-danger-zone">
                <div class="terminal-detail-heading text-danger">Danger Zone</div>
                <form action="{{ route('admin.eft-terminals.destroy', $terminal) }}" method="POST" onsubmit="return confirm('Remove this terminal?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove Terminal</button>
                </form>
            </div>
            @endif

        </div>
    </div>
</div>
