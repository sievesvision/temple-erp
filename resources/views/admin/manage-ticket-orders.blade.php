@extends('admin.layouts.app')

@section('title', 'Ticket Sales')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-receipt me-2 text-warning"></i>Ticket Sales</h1>
            <p class="text-muted mb-0">Every ticket order sold from the Ticket Kiosk.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-outline-secondary"><i class="bi bi-ticket-perforated me-1"></i>Manage Tickets</a>
            <a href="{{ route('admin.tickets.pos') }}" class="btn btn-success" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Open Ticket Kiosk</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="text-muted small text-uppercase fw-bold">Total Sold (Paid)</div>
            <div class="fs-3 fw-bold" style="font-family:'IBM Plex Mono','Inter',monospace;">{{ number_format($totalSold, 2) }}</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th class="text-end">Total</th>
                            <th>Payment</th>
                            <th>Txn ID</th>
                            <th>Sold By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td class="fw-semibold">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $order->customer_name ?: '—' }}</td>
                            <td class="small text-muted">
                                @foreach($order->items as $item)
                                    {{ $item->quantity }}x {{ $item->ticket_name }}@if(!$loop->last), @endif
                                @endforeach
                            </td>
                            <td class="text-end" style="font-family:'IBM Plex Mono','Inter',monospace;">{{ number_format($order->total_amount, 2) }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $order->payment_method }}</span></td>
                            <td class="small text-muted">{{ $order->transaction_id ?: '—' }}</td>
                            <td class="small">{{ $order->seller->name ?? '—' }}</td>
                            <td>{{ $order->order_date->format('d M Y') }}</td>
                            <td>
                                @php
                                    $statusColor = ['Paid' => 'success', 'Pending' => 'warning', 'Cancelled' => 'secondary'][$order->payment_status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $statusColor }}">{{ $order->payment_status }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.tickets.print', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reprint stubs"><i class="bi bi-printer"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center text-muted py-5">No ticket orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
