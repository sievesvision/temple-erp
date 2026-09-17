@extends('admin.layouts.app')

@section('title', 'Audit Logs')

@section('page-css')
<style>
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 {
        font-weight: 700;
        font-size: 1.8rem;
        color: #2d1f0e;
        margin: 0;
    }
    .page-header h1 i {
        color: #b8863a;
        margin-right: 12px;
    }
    .page-header .subtitle {
        color: #7b6b5a;
        font-size: 0.95rem;
        margin-top: 4px;
    }
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .table-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f0ece6;
        padding: 18px 24px;
        font-weight: 600;
        font-size: 1.05rem;
        color: #2d1f0e;
    }
    .table-card .table thead th {
        font-weight: 600;
        color: #5a4e3e;
        border-bottom: 2px solid #f0ece6;
        padding: 14px 16px;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #faf8f5;
    }
    .table-card .table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f5f0ea;
        color: #1e1e2a;
        font-weight: 500;
        vertical-align: middle;
    }
    .table-card .table tbody tr:hover {
        background: #faf8f5;
    }
    .filter-bar {
        background: white;
        border-radius: 20px;
        padding: 16px 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-journal-text"></i>Audit Logs</h1>
    <div class="subtitle">Every logged action across the system, newest first.</div>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('admin.logs.index') }}" class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control rounded-3" placeholder="Search by action or user..." value="{{ $search }}">
        </div>
        <div class="col-md-3">
            <input type="date" name="date_from" class="form-control rounded-3" value="{{ $dateFrom }}" placeholder="From">
        </div>
        <div class="col-md-3">
            <input type="date" name="date_to" class="form-control rounded-3" value="{{ $dateTo }}" placeholder="To">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Filter</button>
        </div>
        @if($search || $dateFrom || $dateTo)
        <div class="col-md-1">
            <a href="{{ route('admin.logs.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Clear</a>
        </div>
        @endif
    </form>
</div>

<div class="table-card">
    <div class="card-header">
        <span>Audit Log Entries</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Action</th>
                    <th>Performed By</th>
                    <th>Event</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->performed_by_name ?? 'System' }}</td>
                    <td>
                        @if($log->event_name)
                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">{{ $log->event_name }}</span>
                        @else
                            <span class="text-muted">&mdash;</span>
                        @endif
                    </td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-journal fs-1 d-block mb-2 text-warning"></i>
                        No log entries found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="p-3 border-top">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
