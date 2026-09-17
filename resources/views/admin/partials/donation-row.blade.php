{{-- Single donation row, shared by the "All Donations" table and each per-event detail
     table on the Manage Donations page. Expects $row (a normalized devotee/guest donation)
     plus the parent view's $temple, $canEditDonation, $canDeleteDonation. --}}
<tr data-type="{{ $row->donation_type }}" data-status="{{ strtolower($row->payment_status) }}" data-method="{{ strtolower($row->payment_method) }}" data-date="{{ $row->donation_date }}">
    <td>
        @if($row->donation_type === 'devotee')
            <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill"><i class="bi bi-people-fill me-1"></i>Devotee</span>
        @else
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill"><i class="bi bi-person-heart me-1"></i>Guest</span>
        @endif
    </td>
    <td><strong>{{ $row->display_id }}</strong></td>
    <td><span class="fw-semibold text-dark">{{ $row->display_name }}</span></td>
    <td>
        <div class="small text-dark">{{ $row->mobile ?? 'No mobile' }}</div>
        <div class="small text-muted">{{ $row->email ?? 'No email' }}</div>
    </td>
    <td><span class="fw-bold text-success">{{ $temple['currency'] }} {{ number_format($row->amount, 2) }}</span></td>
    <td>{{ $purposeOverride ?? $row->display_purpose }}</td>
    <td><span class="badge bg-light text-dark border px-3 py-2 rounded-pill">{{ $row->payment_method }}</span></td>
    <td>
        <code class="small text-dark d-inline-block text-truncate" style="max-width: 110px;" title="{{ $row->transaction_id }}">{{ $row->transaction_id }}</code>
        @if(!empty($row->linkly_txn_ref))
        <div class="small text-muted text-truncate" style="max-width: 110px;" title="Linkly reference: {{ $row->linkly_txn_ref }}">Linkly: {{ $row->linkly_txn_ref }}</div>
        @endif
    </td>
    <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
    <td>
        @php
            $rowStatusColor = ['Paid' => 'success', 'Pending' => 'warning', 'Cancelled' => 'secondary', 'Failed' => 'danger'][$row->payment_status] ?? 'secondary';
        @endphp
        <span class="badge bg-{{ $rowStatusColor }} bg-opacity-10 text-{{ $rowStatusColor }} px-3 py-2 rounded-pill">{{ $row->payment_status }}</span>
    </td>
    <td class="text-end">
        @include('admin.partials.donation-actions', ['row' => $row])
    </td>
</tr>
