@extends('admin.layouts.app')

@section('title', 'Manage Inventory')

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
    .btn-add {
        background: linear-gradient(135deg, #b8863a, #d4a05a);
        color: white;
        border: none;
        padding: 12px 28px;
        border-radius: 40px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(184, 134, 58, 0.3);
        color: white;
    }
    .stat-card {
        background: white;
        border-radius: 24px;
        padding: 22px 24px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(184, 134, 58, 0.06);
        transition: transform 0.15s, box-shadow 0.2s;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 32px rgba(184, 134, 58, 0.08);
    }
    .stat-card.active-filter {
        border-color: #b8863a;
        background: #fdfaf6;
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #7b6b5a;
        font-weight: 600;
    }
    .stat-card .stat-number {
        font-size: 2.2rem;
        font-weight: 700;
        color: #1e1e2a;
        letter-spacing: -0.5px;
        margin: 4px 0 0 0;
    }
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    .stat-icon.gold {
        background: #b8863a;
    }
    .stat-icon.yellow {
        background: #eab308;
    }
    .stat-icon.red {
        background: #ef4444;
    }
    .table-card {
        background: white;
        border-radius: 24px;
        border: 1px solid rgba(184, 134, 58, 0.06);
        box-shadow: 0 8px 24px rgba(0,0,0,0.02);
        overflow: hidden;
        margin-bottom: 24px;
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
    .btn-action-edit {
        background: rgba(184, 134, 58, 0.1);
        color: #b8863a;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-edit:hover {
        background: #b8863a;
        color: white;
    }
    .btn-action-adjust {
        background: rgba(42, 111, 219, 0.1);
        color: #2a6fdb;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-adjust:hover {
        background: #2a6fdb;
        color: white;
    }
    .btn-action-delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: none;
        padding: 6px 14px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-action-delete:hover {
        background: #dc3545;
        color: white;
    }
    .nav-tabs .nav-link {
        border-radius: 12px 12px 0 0;
        color: #7b6b5a;
        font-weight: 500;
        border: none;
        padding: 12px 20px;
    }
    .nav-tabs .nav-link.active {
        color: #b8863a;
        border-bottom: 3px solid #b8863a;
        font-weight: 600;
        background: transparent;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1><i class="bi bi-box-seam"></i>Manage Inventory</h1>
        <div class="subtitle">Add items, adjust stock levels, track logs, and configure low stock thresholds</div>
    </div>
    <div>
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="bi bi-plus-lg"></i> Add New Item
        </button>
    </div>
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

@if ($errors->any())
    <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 p-3" style="background: #fee2e2; color: #991b1b;">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- STATS ROW -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <a href="{{ route('admin.inventory.index') }}" class="stat-card {{ is_null($filter) ? 'active-filter' : '' }}">
            <div>
                <div class="stat-label">Total Inventory Items</div>
                <div class="stat-number">{{ number_format($totalItems) }}</div>
            </div>
            <div class="stat-icon gold"><i class="bi bi-box-seam"></i></div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('admin.inventory.index', ['filter' => 'low']) }}" class="stat-card {{ $filter === 'low' ? 'active-filter' : '' }}">
            <div>
                <div class="stat-label">Low Stock Alert</div>
                <div class="stat-number">{{ number_format($lowStock) }}</div>
            </div>
            <div class="stat-icon yellow"><i class="bi bi-exclamation-triangle"></i></div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('admin.inventory.index', ['filter' => 'out']) }}" class="stat-card {{ $filter === 'out' ? 'active-filter' : '' }}">
            <div>
                <div class="stat-label">Out Of Stock</div>
                <div class="stat-number">{{ number_format($outOfStock) }}</div>
            </div>
            <div class="stat-icon red"><i class="bi bi-x-circle"></i></div>
        </a>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="card border-0 shadow-sm rounded-4 p-0" style="background: white;">
    <div class="px-4 pt-3 border-bottom">
        <ul class="nav nav-tabs border-0" id="inventoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="catalog-tab" data-bs-toggle="tab" data-bs-target="#catalog-pane" type="button" role="tab"><i class="bi bi-list-task text-warning me-1"></i>Stock Catalog</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab"><i class="bi bi-journal-text text-warning me-1"></i>Stock Ledger (Logs)</button>
            </li>
        </ul>
    </div>

    <div class="tab-content p-4" id="inventoryTabsContent">
        <!-- Stock Catalog Pane -->
        <div class="tab-pane fade show active" id="catalog-pane" role="tabpanel">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Item ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity In Hand</th>
                            <th>Status</th>
                            <th>Min Threshold</th>
                            <th>Last Restocked</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i)
                        <tr>
                            <td><strong>IT{{ str_pad($i->item_id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                            <td><span class="fw-semibold text-dark">{{ $i->item_name }}</span></td>
                            <td>{{ $i->category }}</td>
                            <td>
                                <span class="fw-bold">{{ number_format($i->quantity, 2) }}</span>
                                <span class="text-muted small ms-1">{{ $i->unit }}</span>
                            </td>
                            <td>
                                @php $status = $i->stock_status; @endphp
                                <span class="badge bg-{{ $status['class'] }} rounded-pill px-3 py-2 fw-semibold text-white">
                                    {{ $status['text'] }}
                                </span>
                            </td>
                            <td>{{ number_format($i->minimum_threshold, 2) }} {{ $i->unit }}</td>
                            <td>{{ $i->last_restocked ? date('d M Y', strtotime($i->last_restocked)) : 'Never' }}</td>
                            <td class="text-end">
                                <button class="btn-action-adjust me-1" data-bs-toggle="modal" data-bs-target="#adjustStockModal{{ $i->item_id }}">
                                    <i class="bi bi-arrow-down-up"></i> Adjust Stock
                                </button>
                                <button class="btn-action-edit me-1" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $i->item_id }}">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <form action="{{ route('admin.inventory.delete', $i->item_id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item? This deletes all transaction logs too.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action-delete">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- ADJUST STOCK LEVEL MODAL -->
                        <div class="modal fade" id="adjustStockModal{{ $i->item_id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <form action="{{ route('admin.inventory.adjust', $i->item_id) }}" method="POST">
                                        @csrf
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-arrow-down-up text-warning me-2"></i>Adjust Stock Level</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body py-3">
                                            <div class="text-center p-3 mb-3 bg-light rounded-3">
                                                <span class="text-muted small d-block">Current Stock</span>
                                                <h4 class="fw-bold mb-0 text-dark">{{ number_format($i->quantity, 2) }} {{ $i->unit }}</h4>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Adjustment Type</label>
                                                <select name="transaction_type" class="form-select rounded-3" required>
                                                    <option value="Restock">Restock (Add Stock)</option>
                                                    <option value="Consume">Consume (Deduct Stock)</option>
                                                    <option value="Adjustment">Adjustment (Force Set Absolute Stock)</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Quantity</label>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" name="quantity" class="form-control rounded-start-3" placeholder="e.g. 5.50" required>
                                                    <span class="input-group-text rounded-end-3 bg-light">{{ $i->unit }}</span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Remarks</label>
                                                <input type="text" name="remarks" class="form-control rounded-3" placeholder="e.g. Received new shipment, Temple event consumption" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                                            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Adjust Stock</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- EDIT ITEM MODAL -->
                        <div class="modal fade" id="editItemModal{{ $i->item_id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <form action="{{ route('admin.inventory.update', $i->item_id) }}" method="POST">
                                        @csrf
                                        <div class="modal-header border-0 pb-0">
                                            <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Item Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body py-3">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Item Name</label>
                                                <input type="text" name="item_name" class="form-control rounded-3" value="{{ $i->item_name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Category</label>
                                                <select name="category" class="form-select rounded-3" required>
                                                    <option value="Pooja Items" {{ $i->category === 'Pooja Items' ? 'selected' : '' }}>Pooja Items</option>
                                                    <option value="Prasadam Ingredients" {{ $i->category === 'Prasadam Ingredients' ? 'selected' : '' }}>Prasadam Ingredients</option>
                                                    <option value="Decoration" {{ $i->category === 'Decoration' ? 'selected' : '' }}>Decoration</option>
                                                    <option value="Maintenance & Utilities" {{ $i->category === 'Maintenance & Utilities' ? 'selected' : '' }}>Maintenance & Utilities</option>
                                                    <option value="Stationery" {{ $i->category === 'Stationery' ? 'selected' : '' }}>Stationery</option>
                                                </select>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Unit of Measure</label>
                                                    <input type="text" name="unit" class="form-control rounded-3" value="{{ $i->unit }}" placeholder="e.g. kg, pieces, liters" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fw-semibold">Min Alert Threshold</label>
                                                    <input type="number" step="0.01" name="minimum_threshold" class="form-control rounded-3" value="{{ $i->minimum_threshold }}" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 pt-0">
                                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                                            <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 text-warning"></i>
                                No inventory items found. Add items to track them.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Ledger Pane -->
        <div class="tab-pane fade" id="ledger-pane" role="tabpanel">
            <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-clock-history me-2 text-warning"></i>Stock Transaction Ledger (Auditing Logs)</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Date</th>
                            <th>Item Name</th>
                            <th>Transaction Type</th>
                            <th>Adjusted Qty</th>
                            <th>Remarks / Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        <tr>
                            <td><strong>LG{{ str_pad($tx->transaction_id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                            <td>{{ date('d M Y g:i A', strtotime($tx->created_at)) }}</td>
                            <td><strong>{{ $tx->item->item_name ?? 'Deleted Item' }}</strong></td>
                            <td>
                                @if($tx->transaction_type === 'Restock')
                                <span class="badge bg-success rounded-pill px-3 py-1">Restock</span>
                                @elseif($tx->transaction_type === 'Consume')
                                <span class="badge bg-danger rounded-pill px-3 py-1">Consume</span>
                                @else
                                <span class="badge bg-secondary rounded-pill px-3 py-1">Adjustment</span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold text-{{ $tx->transaction_type === 'Restock' ? 'success' : 'danger' }}">
                                    {{ $tx->transaction_type === 'Restock' ? '+' : '-' }}{{ number_format($tx->quantity, 2) }}
                                </span>
                                <span class="text-muted small ms-1">{{ $tx->item->unit ?? '' }}</span>
                            </td>
                            <td>{{ $tx->remarks ?? 'N/A' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-journals fs-1 d-block mb-2 text-warning"></i>
                                No stock log history yet. Adjust stock to see updates.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ADD NEW ITEM MODAL -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="{{ route('admin.inventory.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-circle text-warning me-2"></i>Add New Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Item Name</label>
                        <input type="text" name="item_name" class="form-control rounded-3" placeholder="e.g. Basmati Rice, Desi Ghee, Jasmine Flowers" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category</label>
                        <select name="category" class="form-select rounded-3" required>
                            <option value="Pooja Items" selected>Pooja Items</option>
                            <option value="Prasadam Ingredients">Prasadam Ingredients</option>
                            <option value="Decoration">Decoration</option>
                            <option value="Maintenance & Utilities">Maintenance & Utilities</option>
                            <option value="Stationery">Stationery</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Initial Stock Level</label>
                            <input type="number" step="0.01" name="quantity" class="form-control rounded-3" value="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit of Measure</label>
                            <input type="text" name="unit" class="form-control rounded-3" placeholder="e.g. kg, liters, bags, pieces" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Minimum Threshold Alert</label>
                        <input type="number" step="0.01" name="minimum_threshold" class="form-control rounded-3" value="10.00" required>
                        <div class="form-text text-muted small">Receive warnings when stock drops below this value.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="background:#f0ece6; border:none; color:#1e1e2a;">Cancel</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold rounded-pill px-4" style="background: linear-gradient(135deg, #b8863a, #d4a05a); border:none;">Create Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
    $(document).ready(function() {
        console.log("Manage Inventory dashboard loaded");
    });
</script>
@endsection
