@extends('admin.layouts.app')

@section('title', 'Organisation Transactions')

@section('page-css')
<style>
    .page-header {
        background: white; border-radius: 24px; padding: 24px 32px; margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }
    .hub-links { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .hub-link {
        background: white; border: 1px solid rgba(184, 134, 58, 0.12); border-radius: 14px;
        padding: 12px 20px; color: #2d1f0e; text-decoration: none; font-weight: 600; font-size: 0.88rem;
        display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    }
    .hub-link:hover { border-color: #b8863a; color: #b8863a; }
    .hub-link i { color: #b8863a; }
    .filter-bar {
        background: white; border-radius: 20px; padding: 16px 20px; margin-bottom: 20px;
        border: 1px solid rgba(184, 134, 58, 0.06); box-shadow: 0 8px 24px rgba(0,0,0,0.02);
    }
    .table-card {
        background: white; border-radius: 24px; border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02); overflow: hidden;
    }
    .table-card .card-header { background: transparent; border-bottom: 1px solid #f0ece6; padding: 18px 24px; font-weight: 600; font-size: 1.05rem; color: #2d1f0e; }
    .table-card .table thead th { font-weight: 600; color: #5a4e3e; border-bottom: 2px solid #f0ece6; padding: 14px 16px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; background: #faf8f5; }
    .table-card .table tbody td { padding: 12px 16px; border-bottom: 1px solid #f5f0ea; color: #1e1e2a; font-weight: 500; vertical-align: middle; font-size: 0.88rem; }
    .table-card .table tbody tr:hover { background: #faf8f5; }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-graph-up-arrow"></i>Organisation Transactions</h1>
    <div class="subtitle">The EFT/card transaction ledger across every event and Ticket Sales, in one place — donations, ticket orders and audit activity are already organisation-wide on their own pages, linked below.</div>
</div>

<div class="hub-links">
    <a href="{{ route('admin.donations.index') }}" class="hub-link"><i class="bi bi-cash-coin"></i>All Donations</a>
    <a href="{{ route('admin.tickets.index') }}" class="hub-link"><i class="bi bi-ticket-perforated-fill"></i>All Ticket Orders</a>
    <a href="{{ route('admin.reports.index') }}" class="hub-link"><i class="bi bi-bar-chart-fill"></i>Reports</a>
    <a href="{{ route('admin.logs.index') }}" class="hub-link"><i class="bi bi-journal-text"></i>Audit Logs</a>
</div>

<div class="filter-bar">
    <form method="GET" action="{{ route('admin.transactions.index') }}" class="row g-2 align-items-center">
        <div class="col-md-2">
            <select name="event_id" class="form-select rounded-3">
                <option value="">All Events</option>
                <option value="tickets" {{ $eventFilter === 'tickets' ? 'selected' : '' }}>Ticket Sales</option>
                @foreach($events as $e)
                    <option value="{{ $e->event_id }}" {{ (string) $eventFilter === (string) $e->event_id ? 'selected' : '' }}>{{ $e->event_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="provider" class="form-select rounded-3">
                <option value="">Any Provider</option>
                <option value="linkly" {{ $providerFilter === 'linkly' ? 'selected' : '' }}>Linkly Cloud</option>
                <option value="cba_sci" {{ $providerFilter === 'cba_sci' ? 'selected' : '' }}>CBA Smart Terminal</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="operator_id" class="form-select rounded-3">
                <option value="">Any Operator</option>
                @foreach($operators as $op)
                    <option value="{{ $op->id }}" {{ (string) $operatorFilter === (string) $op->id ? 'selected' : '' }}>{{ $op->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control rounded-3" value="{{ $dateFrom }}" placeholder="From">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control rounded-3" value="{{ $dateTo }}" placeholder="To">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Filter</button>
            @if($eventFilter || $providerFilter || $operatorFilter || $dateFrom || $dateTo)
            <a href="{{ route('admin.transactions.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="table-card">
    <div class="card-header"><span>Transactions ({{ $transactions->count() }}@if($transactions->count() === 300)+@endif)</span></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Event</th>
                    <th>Provider</th>
                    <th>Terminal</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $row)
                <tr>
                    <td>{{ $row->created_at?->format('d M Y, h:i A') }}</td>
                    <td>{{ $row->txn->event?->event_name ?? 'Ticket Sales' }}</td>
                    <td>{{ $row->provider === 'linkly' ? 'Linkly Cloud' : 'CBA Smart Terminal' }}</td>
                    <td>{{ $row->txn->eftTerminal?->label ?? '—' }}</td>
                    <td class="text-capitalize">{{ strtolower($row->txn->txn_type ?? '') }}</td>
                    <td>{{ $row->txn->currency_code ?? 'AUD' }} {{ number_format($row->txn->amount ?? 0, 2) }}</td>
                    <td>
                        @php $status = $row->txn->status ?? ''; @endphp
                        <span class="badge {{ in_array(strtoupper($status), ['APPROVED','FINALISED']) ? 'bg-success' : (in_array(strtoupper($status), ['DECLINED','FAILED','CANCELLED']) ? 'bg-danger' : 'bg-secondary') }}">{{ $status }}</span>
                    </td>
                    <td>{{ $row->txn->initiator?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-graph-up-arrow fs-1 d-block mb-2 text-warning"></i>
                        No transactions match these filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
