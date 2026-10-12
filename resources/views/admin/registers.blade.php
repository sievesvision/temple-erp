@extends('admin.layouts.app')

@section('title', 'Registers')

@section('page-css')
<style>
    .page-header {
        background: white; border-radius: 24px; padding: 24px 32px; margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }
    .table-card {
        background: white; border-radius: 24px; border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02); overflow: hidden;
    }
    .table-card .card-header { background: transparent; border-bottom: 1px solid #f0ece6; padding: 18px 24px; font-weight: 600; font-size: 1.05rem; color: #2d1f0e; }
    .table-card .table thead th { font-weight: 600; color: #5a4e3e; border-bottom: 2px solid #f0ece6; padding: 14px 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; background: #faf8f5; }
    .table-card .table tbody td { padding: 14px 16px; border-bottom: 1px solid #f5f0ea; color: #1e1e2a; font-weight: 500; vertical-align: middle; }
    .table-card .table tbody tr:hover { background: #faf8f5; }
    .btn-action-reset {
        background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: none; padding: 6px 14px;
        border-radius: 40px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-action-reset:hover { background: #8b5cf6; color: white; }
    .btn-action-danger {
        background: rgba(220, 38, 38, 0.1); color: #dc2626; border: none; padding: 6px 14px;
        border-radius: 40px; font-weight: 600; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-action-danger:hover { background: #dc2626; color: white; }
    code.pairing-code { font-size: 1.3rem; letter-spacing: 0.2em; background: #faf8f5; padding: 4px 12px; border-radius: 8px; }
</style>
@endsection

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="bi bi-display"></i>Registers</h1>
        <div class="subtitle">Physical counters/stations — each one pairs to its own browser once, and can be pointed at a different event's or Ticket Sales' own default EFT terminal.</div>
    </div>
    <button type="button" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;" data-bs-toggle="modal" data-bs-target="#createRegisterModal">
        <i class="bi bi-plus-lg me-1"></i>Create Register
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #d1fae5; color: #065f46;">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 p-3" role="alert" style="background: #fee2e2; color: #991b1b;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('register_pairing_code'))
    <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4 p-3" role="alert">
        Pairing code for <strong>{{ session('register_pairing_code.name') }}</strong> — valid for 10 minutes, shown once:
        <code class="pairing-code ms-2">{{ session('register_pairing_code.code') }}</code>
        <div class="small text-muted mt-1">Open <strong>{{ url('/register/pair') }}</strong> on that register's own browser and enter this code.</div>
    </div>
@endif

<div class="table-card">
    <div class="card-header"><span>All Registers</span></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Last Seen</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registers as $r)
                <tr>
                    <td><strong>{{ $r->name }}</strong></td>
                    <td>
                        @if($r->isPaired())
                            <span class="badge bg-success">Paired</span>
                        @else
                            <span class="badge bg-secondary">Not Paired</span>
                        @endif
                    </td>
                    <td>{{ $r->last_seen_at ? $r->last_seen_at->diffForHumans() : 'Never' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.registers.terminals', $r->id) }}" class="btn-action-reset">
                            <i class="bi bi-credit-card-2-front-fill"></i> Default Terminals
                        </a>
                        <form action="{{ route('admin.registers.pairingCode', $r->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Generate a new pairing code for {{ $r->name }}? Any unused existing code is invalidated.')">
                            @csrf
                            <button type="submit" class="btn-action-reset"><i class="bi bi-qr-code"></i> Pairing Code</button>
                        </form>
                        @if($r->isPaired())
                        <form action="{{ route('admin.registers.unpair', $r->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Unpair {{ $r->name }}? Its browser will need a new pairing code to reconnect.')">
                            @csrf
                            <button type="submit" class="btn-action-danger"><i class="bi bi-x-circle"></i> Unpair</button>
                        </form>
                        @endif
                        <form action="{{ route('admin.registers.destroy', $r->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete {{ $r->name }} entirely? This also removes its terminal defaults.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-action-danger"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-5">
                        <i class="bi bi-display fs-1 d-block mb-2 text-warning"></i>
                        No registers yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createRegisterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form method="POST" action="{{ route('admin.registers.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-display text-warning me-2"></i>Create Register</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <label class="form-label fw-semibold">Name</label>
                    <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Front Counter" required>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
