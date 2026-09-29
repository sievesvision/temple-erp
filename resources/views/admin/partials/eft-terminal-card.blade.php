@php
    $lastKnown = $terminal->lastKnownStatus();
    $detailId = 'terminal-detail-' . $terminal->id;
    // Expanded by default right after you've just added, paired, tested or unpaired THIS
    // specific terminal — see the various controller actions' ->with('expandTerminalId', ...)
    // — so the result of what you just did is immediately visible instead of collapsed away.
    $startExpanded = (int) session('expandTerminalId') === $terminal->id;
@endphp
<div class="card-panel{{ $terminal->isPairedFor($linklyMode) ? '' : ' card-panel-inactive' }} p-0">
    <div class="d-flex align-items-center gap-3 flex-wrap terminal-summary-row" role="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="{{ $startExpanded ? 'true' : 'false' }}" aria-controls="{{ $detailId }}">
        <i class="bi bi-chevron-right terminal-summary-chevron"></i>
        <strong>{{ $terminal->label }}</strong>
        <span class="text-muted small">({{ $terminal->key }})</span>
        <span class="badge bg-secondary-subtle text-secondary-emphasis text-uppercase" style="font-size:0.68rem;">{{ $terminal->provider === 'cba_sci' ? 'mx51 Cloud' : 'Linkly Cloud' }}</span>
        @if($terminal->is_default)<span class="badge bg-primary">Default</span>@endif
        <span class="status-pill {{ $terminal->isPairedFor($linklyMode) ? 'paid' : 'cancelled' }}">{{ $terminal->isPairedFor($linklyMode) ? 'Paired' : 'Not Paired' }}</span>
        @if($lastKnown['state'] === 'online')
        <span class="status-pill paid">Online</span>
        @elseif($lastKnown['state'] === 'offline')
        <span class="status-pill cancelled">Offline</span>
        @else
        <span class="status-pill pending">Not checked</span>
        @endif
        @if($lastKnown['at'])
        <span class="text-muted small">({{ $lastKnown['at']->diffForHumans() }})</span>
        @endif
    </div>
    <div class="collapse{{ $startExpanded ? ' show' : '' }}" id="{{ $detailId }}">
        <div class="terminal-detail-body">
            @if($terminal->provider === 'linkly')
            <form action="{{ route('admin.eft.pair') }}" method="POST" class="row g-3 align-items-end mb-2">
                @csrf
                <input type="hidden" name="terminal_id" value="{{ $terminal->id }}">
                <div class="col-md-5">
                    <input type="text" name="pair_code" class="form-control rounded-3" placeholder="6-digit code from the terminal" maxlength="10" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">{{ $terminal->isPaired($linklyMode) ? 'Re-pair' : 'Pair' }}</button>
                </div>
            </form>
            @else
            @include('admin.partials.cba-sci-pairing', ['terminal' => $terminal])
            @endif
            @if($isSystemAdmin)
            <div class="d-flex gap-2">
                @if(!$terminal->is_default)
                <form action="{{ route('admin.eft-terminals.setDefault', $terminal) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Set as Default</button>
                </form>
                <form action="{{ route('admin.eft-terminals.destroy', $terminal) }}" method="POST" onsubmit="return confirm('Remove this terminal?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
