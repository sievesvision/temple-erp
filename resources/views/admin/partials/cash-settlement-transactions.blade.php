{{-- Extracted from cash-settlement-pane.blade.php so its own order relative to the
     Settlement form (below/after it normally, above/before it on Event POS's Settle Till
     view — see $settlementFirst there) can differ per host page without duplicating this
     whole block. Shares its including view's own variable scope (Blade's default
     @include behaviour) — $currentTransactions, $scope, $formId, $fmt, $baseUrl,
     $canEditCurrentTransactions all come from cash-settlement-pane.blade.php. --}}
<div class="cs-table-wrap mb-3">
    <div class="cs-table-head">
        <div class="fw-bold"><i class="bi bi-list-check me-1"></i>Current Period Transactions ({{ $currentTransactions->count() }})</div>
        @if($currentTransactions->isNotEmpty())
        <div class="cs-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="cs-search-input" id="csTxnSearch_{{ $formId }}" placeholder="Search by name or purpose...">
        </div>
        @endif
    </div>

    @if($currentTransactions->isEmpty())
        <div class="cs-empty-state">Nothing recorded in this period yet.</div>
    @else
    <div class="table-responsive">
        <table class="cs-table" id="csTxnTable_{{ $formId }}">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>{{ $scope === 'event' ? 'Donor' : 'Customer' }}</th>
                    <th>{{ $scope === 'event' ? 'Purpose' : 'Order' }}</th>
                    @if($scope === 'event')<th>Method</th><th>Status</th>@else<th>Method</th>@endif
                    <th class="text-end">Amount</th>
                    @if($canEditCurrentTransactions || $scope !== 'event')<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @if($scope === 'event')
                    @foreach($currentTransactions as $row)
                    @php
                        $rowKey = $row->donation_type . '-' . $row->id;
                        $isPending = $row->payment_status === 'Pending';
                        $canComplete = $canEditCurrentTransactions && $isPending;
                    @endphp
                    <tr class="cs-row" data-search="{{ strtolower($row->display_name . ' ' . $row->purpose) }}" data-edit-id="csEdit_{{ $rowKey }}">
                        <td>{{ \Illuminate\Support\Carbon::parse($row->donation_date)->format('d M Y') }}</td>
                        <td>{{ $row->display_name }}</td>
                        <td>{{ $row->purpose ?: '—' }}</td>
                        <td>{{ $row->payment_method }}</td>
                        <td><span class="cs-badge {{ $isPending ? 'pending' : 'paid' }}">{{ $isPending ? 'Pending' : 'Completed' }}</span></td>
                        <td class="text-end fw-bold">{{ $fmt($row->amount) }}</td>
                        @if($canEditCurrentTransactions)
                        <td class="text-end">
                            @if($canComplete && $row->donation_type === 'guest')
                                <a class="cs-complete-link" href="{{ $baseUrl }}?edit_id={{ $row->id }}"><i class="bi bi-arrow-right-circle-fill me-1"></i>Complete in POS</a>
                            @elseif($canComplete && $row->donation_type === 'devotee')
                                <button type="button" class="cs-icon-btn cs-edit-toggle" data-target="csEdit_{{ $rowKey }}" title="Edit"><i class="bi bi-pencil-fill"></i></button>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @if($canComplete && $row->donation_type === 'devotee')
                    <tr class="cs-edit-row" id="csEdit_{{ $rowKey }}" style="display:none;">
                        <td colspan="7">
                            <form action="{{ route('admin.donations.updateDevotee', $row->id) }}" method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                                @csrf
                                <input type="hidden" name="event_id" value="{{ $eventId }}">
                                <input type="hidden" name="payment_mode" value="{{ $row->payment_method }}">
                                <input type="hidden" name="payment_status" value="{{ $row->payment_status }}">
                                <input type="hidden" name="donation_date" value="{{ $row->donation_date }}">
                                <input type="hidden" name="transaction_id" value="{{ $row->transaction_id }}">
                                <input type="hidden" name="remarks" value="{{ $row->remarks }}">
                                <div class="text-muted small flex-shrink-0" style="min-width:140px;" title="Devotee name isn't editable here"><i class="bi bi-lock-fill me-1" style="font-size:0.7rem;"></i>{{ $row->display_name }}</div>
                                <div><input type="text" name="purpose" class="form-control form-control-sm" value="{{ $row->purpose }}" placeholder="Purpose" style="min-width:160px;"></div>
                                <div><input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" value="{{ $row->amount }}" placeholder="Amount" style="width:110px;" required></div>
                                <button type="submit" class="cs-icon-btn cs-save-btn" title="Save"><i class="bi bi-check-lg"></i></button>
                                <button type="button" class="cs-icon-btn cs-cancel-btn cs-edit-cancel" data-target="csEdit_{{ $rowKey }}" title="Cancel"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                @else
                    @foreach($currentTransactions as $row)
                    {{-- Clicking anywhere on the row (or the View icon) opens the order in
                         the popup below — a quick look without leaving the pane. The Print
                         icon is the one remaining direct link, for actually printing. --}}
                    @php
                        $orderItemsForModal = collect($row->items ?? [])->map(fn ($item) => [
                            'name' => $item->ticket_name,
                            'quantity' => $item->quantity,
                            'unitPrice' => $fmt($item->unit_price),
                            'lineTotal' => $fmt($item->line_total),
                        ]);
                    @endphp
                    <tr class="cs-row cs-row-clickable" data-search="{{ strtolower(($row->customer_name ?: 'walk-in') . ' order ' . $row->id) }}"
                        data-order-url="{{ route('admin.tickets.print', $row->id) }}" data-order-id="{{ $row->id }}"
                        data-order-date="{{ \Illuminate\Support\Carbon::parse($row->order_date)->format('d M Y') }}"
                        data-order-customer="{{ $row->customer_name ?: 'Walk-in' }}" data-order-method="{{ $row->payment_method }}"
                        data-order-total="{{ $fmt($row->total_amount) }}" data-order-items="{{ $orderItemsForModal->toJson() }}">
                        <td>{{ \Illuminate\Support\Carbon::parse($row->order_date)->format('d M Y') }}</td>
                        <td>{{ $row->customer_name ?: 'Walk-in' }}</td>
                        <td>Order #{{ $row->id }}</td>
                        <td>{{ $row->payment_method }}</td>
                        <td class="text-end fw-bold">{{ $fmt($row->total_amount) }}</td>
                        <td class="text-end">
                            <button type="button" class="cs-icon-btn me-1 cs-view-order-btn" title="View Order"><i class="bi bi-eye-fill"></i></button>
                            <a href="{{ route('admin.tickets.print', $row->id) }}" target="_blank" class="cs-icon-btn" title="Print Receipt"><i class="bi bi-printer-fill"></i></a>
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
    @endif
</div>
