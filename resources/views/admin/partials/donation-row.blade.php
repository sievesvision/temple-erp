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
    <td><code class="small text-dark d-inline-block text-truncate" style="max-width: 110px;" title="{{ $row->transaction_id }}">{{ $row->transaction_id }}</code></td>
    <td>{{ date('d M Y', strtotime($row->donation_date)) }}</td>
    <td>
        @php
            $rowStatusColor = ['Paid' => 'success', 'Pending' => 'warning', 'Cancelled' => 'secondary', 'Failed' => 'danger'][$row->payment_status] ?? 'secondary';
        @endphp
        <span class="badge bg-{{ $rowStatusColor }} bg-opacity-10 text-{{ $rowStatusColor }} px-3 py-2 rounded-pill">{{ $row->payment_status }}</span>
    </td>
    <td class="text-end">
        @if($row->donation_type === 'devotee')
            @if($canEditDonation && $row->payment_method === 'Stripe' && in_array($row->payment_status, ['Pending', 'Cancelled']))
            <form action="{{ route('admin.donations.checkStripeStatus', ['type' => 'devotee', 'id' => $row->id]) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn-action-checkstatus" title="Ask Stripe for this session's real status">
                    <i class="bi bi-arrow-repeat"></i> Check Status
                </button>
            </form>
            @endif
            @if($canEditDonation && $row->payment_status === 'Pending' && in_array($row->payment_method, ['Bank Transfer', 'Bank', 'Cash']))
            <form action="{{ route('admin.donations.approveDevotee', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm that this payment was received and approve this donation?')">
                @csrf
                <button type="submit" class="btn-action-approve" title="Approve this donation as received">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
            </form>
            @endif
            @if($row->email && $row->payment_status === 'Paid')
            <form action="{{ route('admin.donations.resendReceipt', ['type' => 'devotee', 'id' => $row->id]) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn-action-resend" title="Resend receipt to {{ $row->email }}">
                    <i class="bi bi-envelope-arrow-up"></i> Resend
                </button>
            </form>
            @endif
            @if($canEditDonation)
            <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editDevoteeDonationModal{{ $row->id }}">
                <i class="bi bi-pencil-square"></i> Edit
            </button>
            @endif
            @if($canDeleteDonation)
            <form action="{{ route('admin.donations.deleteDevotee', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this donation record? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-action-delete">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
            @endif
        @else
            @if($canEditDonation && $row->payment_method === 'Stripe' && in_array($row->payment_status, ['Pending', 'Cancelled']))
            <form action="{{ route('admin.donations.checkStripeStatus', ['type' => 'guest', 'id' => $row->id]) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn-action-checkstatus" title="Ask Stripe for this session's real status">
                    <i class="bi bi-arrow-repeat"></i> Check Status
                </button>
            </form>
            @endif
            @if($canEditDonation && $row->payment_status === 'Pending' && in_array($row->payment_method, ['Bank', 'Cash']))
            <form action="{{ route('admin.donations.approveGuest', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm that this payment was received and approve this donation?')">
                @csrf
                <button type="submit" class="btn-action-approve" title="Approve this donation as received">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
            </form>
            @endif
            @if($row->email && $row->payment_status === 'Paid')
            <form action="{{ route('admin.donations.resendReceipt', ['type' => 'guest', 'id' => $row->id]) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn-action-resend" title="Resend receipt to {{ $row->email }}">
                    <i class="bi bi-envelope-arrow-up"></i> Resend
                </button>
            </form>
            @endif
            @if($canEditDonation)
            <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editGuestDonationModal{{ $row->id }}">
                <i class="bi bi-pencil-square"></i> Edit
            </button>
            @endif
            @if($canDeleteDonation)
            <form action="{{ route('admin.donations.deleteGuest', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this donation record? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-action-delete">
                    <i class="bi bi-trash"></i> Delete
                </button>
            </form>
            @endif
        @endif
    </td>
</tr>
