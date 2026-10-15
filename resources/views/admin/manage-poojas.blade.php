@extends('admin.layouts.app')

@section('title','Manage Poojas')

@section('page-css')
<style>
    .page-header {
        background: white;
        border-radius: 24px;
        padding: 24px 32px;
        margin-bottom: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .page-header h1 { font-weight: 700; font-size: 1.8rem; color: #2d1f0e; margin: 0; }
    .page-header h1 i { color: #b8863a; margin-right: 12px; }
    .page-header .subtitle { color: #7b6b5a; font-size: 0.95rem; margin-top: 4px; }

    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
        padding: 0;
        margin-bottom: 24px;
    }
    .table-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f0ece6;
        padding: 18px 24px;
        font-weight: 700;
        font-size: 1.05rem;
        color: #2d1f0e;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .table-card .card-header .count-badge {
        background: #f5f0ea;
        color: #7b6b5a;
        border-radius: 40px;
        padding: 2px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        margin-left: 8px;
    }
    .table-card .table { margin: 0; }
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
        padding: 12px 16px;
        border-bottom: 1px solid #f5f0ea;
        color: #1e1e2a;
        font-weight: 500;
        vertical-align: top;
    }
    .pooja-desc { color: #7b6b5a; font-size: 0.82rem; max-width: 420px; }
    .badge-status { padding: 5px 14px; border-radius: 40px; font-weight: 600; font-size: 0.72rem; }
    .badge-status.active { background: #d1fae5; color: #065f46; }
    .badge-status.inactive { background: #fee2e2; color: #991b1b; }

    .btn-action {
        padding: 5px 11px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.72rem;
        margin: 2px;
        border: none;
        cursor: pointer;
    }
    .btn-action.edit { background: #f3e8ff; color: #6b21a8; }
    .btn-action.delete { background: #fee2e2; color: #991b1b; }

    .modal-content { border-radius: 20px; border: none; }
    .modal-body label { font-weight: 600; font-size: 0.85rem; color: #5a4e3e; margin-bottom: 4px; }
</style>
@endsection

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mx-4 mt-4 mb-0" style="background: #d1fae5; color: #065f46; border-left: 4px solid #059669; border-radius:16px;">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mx-4 mt-4 mb-0" style="background: #fee2e2; color: #991b1b; border-left: 4px solid #dc3545; border-radius:16px;">
    <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mx-4 mt-4 mb-0" style="background: #fee2e2; color: #991b1b; border-left: 4px solid #dc3545; border-radius:16px;">
    <i class="bi bi-exclamation-circle-fill me-2"></i><strong>Please check the form:</strong>
    <ul class="mb-0 mt-1">@foreach($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="container-fluid px-4 py-4">
    <div class="page-header">
        <div>
            <h1><i class="bi bi-journal-bookmark-fill"></i> Manage Poojas</h1>
            <div class="subtitle"><i class="bi bi-info-circle me-1"></i> The pooja catalog devotees, staff and priests pick from when booking — Archanas are sold separately as Tickets.</div>
        </div>
        @if($canAdd)
        <button type="button" class="btn btn-warning rounded-pill fw-bold px-4" data-bs-toggle="modal" data-bs-target="#addPoojaModal">
            <i class="bi bi-plus-circle"></i> Add Pooja
        </button>
        @endif
    </div>

    <div class="table-card p-4 mb-4">
        <form method="GET" action="{{ route('admin.poojas.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4 col-sm-6">
                    <label class="form-label fw-bold">Search</label>
                    <input type="text" name="search" class="form-control rounded-pill px-3" value="{{ request('search') }}" placeholder="Name or category...">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold">Category</label>
                    <select name="category" class="form-select rounded-pill px-3">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status" class="form-select rounded-pill px-3">
                        <option value="">All</option>
                        <option value="Active" {{ request('status') === 'Active' ? 'selected' : '' }}>Active</option>
                        <option value="Inactive" {{ request('status') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold"><i class="bi bi-filter"></i> Filter</button>
                </div>
            </div>
        </form>
    </div>

    @php $grouped = $poojas->groupBy(fn($p) => $p->category ?: 'Uncategorised'); @endphp

    @forelse($grouped as $categoryName => $categoryPoojas)
    <div class="table-card">
        <div class="card-header">
            <span><i class="bi bi-tags-fill me-2" style="color:#b8863a;"></i> {{ $categoryName }} <span class="count-badge">{{ $categoryPoojas->count() }}</span></span>
        </div>
        <div style="overflow-x:auto;">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description / Notes</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categoryPoojas as $pooja)
                    <tr>
                        <td><strong>{{ $pooja->pooja_name }}</strong></td>
                        <td class="pooja-desc">{{ $pooja->description }}</td>
                        <td>{{ $temple['currency'] }} {{ number_format($pooja->pooja_fee, 2) }}</td>
                        <td><span class="badge-status {{ strtolower($pooja->status) }}">{{ $pooja->status }}</span></td>
                        <td>
                            @if($canEdit)
                            <button class="btn-action edit editPoojaBtn"
                                data-bs-toggle="modal" data-bs-target="#editPoojaModal"
                                data-id="{{ $pooja->pooja_id }}"
                                data-name="{{ $pooja->pooja_name }}"
                                data-description="{{ $pooja->description }}"
                                data-category="{{ $pooja->category }}"
                                data-fee="{{ $pooja->pooja_fee }}"
                                data-duration="{{ $pooja->duration }}"
                                data-status="{{ $pooja->status }}"
                                data-online="{{ $pooja->online_allowed ? 1 : 0 }}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            @endif
                            @if($canDelete)
                            <form action="{{ route('admin.poojas.delete', $pooja->pooja_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this pooja? If it has past bookings it will be deactivated instead.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-action delete"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div class="table-card p-5 text-center text-muted">
        <i class="bi bi-journal-x" style="font-size:2rem;"></i>
        <p class="mt-2 mb-0">No poojas found. @if($canAdd)Click "Add Pooja" to create the first one.@endif</p>
    </div>
    @endforelse
</div>

@if($canAdd)
<!-- ADD POOJA MODAL -->
<div class="modal fade" id="addPoojaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.poojas.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-warning"></i> Add Pooja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    @include('admin.partials.pooja-form-fields', ['pooja' => null])
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Add Pooja</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($canEdit)
<!-- EDIT POOJA MODAL -->
<div class="modal fade" id="editPoojaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editPoojaForm" action="">
                @csrf
                @method('POST')
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2 text-warning"></i> Edit Pooja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    @include('admin.partials.pooja-form-fields', ['pooja' => null, 'prefix' => 'edit_'])
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<datalist id="poojaCategoryOptions">
    @foreach(\App\Http\Controllers\PoojaController::SUGGESTED_CATEGORIES as $cat)
        <option value="{{ $cat }}">
    @endforeach
    @foreach($categories as $cat)
        <option value="{{ $cat }}">
    @endforeach
</datalist>
@endsection

@section('page-js')
<script>
    $(document).ready(function() {
        setTimeout(function() { $('.alert-success, .alert-danger').fadeOut('slow'); }, 5000);

        $('.editPoojaBtn').on('click', function() {
            const data = $(this).data();
            $('#editPoojaForm').attr('action', '{{ url("/admin/pooja/update") }}/' + data.id);
            $('#edit_pooja_name').val(data.name);
            $('#edit_description').val(data.description);
            $('#edit_category').val(data.category);
            $('#edit_pooja_fee').val(data.fee);
            $('#edit_duration').val(data.duration);
            $('#edit_status').val(data.status);
            $('#edit_online_allowed').prop('checked', data.online == 1);
        });
    });
</script>
@endsection
