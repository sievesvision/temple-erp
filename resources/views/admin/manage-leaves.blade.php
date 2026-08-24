@extends('admin.layouts.app')

@section('title', 'Manage Leaves')

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
    .btn-approve {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: none;
        padding: 6px 16px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-approve:hover {
        background: #28a745;
        color: white;
    }
    .btn-reject {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: none;
        padding: 6px 16px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-reject:hover {
        background: #dc3545;
        color: white;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <h1><i class="bi bi-calendar-x-fill"></i>Manage Priest Leaves</h1>
    <div class="subtitle">Review, approve, or reject leave applications submitted by temple priests</div>
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

<div class="table-card">
    <div class="card-header">
        <span>Priest Leave Applications</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Priest Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Reason</th>
                    <th>Submitted On</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($leaves as $l)
                <tr>
                    <td><strong>{{ $l->priest_name }}</strong></td>
                    <td class="fw-bold text-dark">{{ date('d M Y', strtotime($l->start_date)) }}</td>
                    <td class="fw-bold text-dark">{{ date('d M Y', strtotime($l->end_date)) }}</td>
                    <td>{{ $l->reason }}</td>
                    <td class="small text-muted">{{ date('d M Y, h:i A', strtotime($l->created_at)) }}</td>
                    <td>
                        <span class="badge rounded-pill px-3 py-2 bg-{{ $l->status === 'Approved' ? 'success' : ($l->status === 'Rejected' ? 'danger' : 'warning') }} bg-opacity-10 text-{{ $l->status === 'Approved' ? 'success' : ($l->status === 'Rejected' ? 'danger' : 'warning') }}">
                            {{ $l->status }}
                        </span>
                    </td>
                    <td class="text-end">
                        @if($l->status === 'Pending')
                        <form action="{{ route('admin.leaves.status', $l->id) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="status" value="Approved">
                            <button type="submit" class="btn-approve">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                        </form>
                        <form action="{{ route('admin.leaves.status', $l->id) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="status" value="Rejected">
                            <button type="submit" class="btn-reject">
                                <i class="bi bi-x-lg"></i> Reject
                            </button>
                        </form>
                        @else
                        <span class="text-muted small">Processed</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No leave applications found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
