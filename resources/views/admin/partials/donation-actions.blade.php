{{-- Action buttons for one donation row — shared by donation-row.blade.php and the
     per-event pivoted table. Expects $row plus the parent view's $canEditDonation,
     $canDeleteDonation. --}}
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
    @if($canEditDonation && $row->email && $row->payment_status === 'Paid')
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
    @if($canEditDonation && $row->email && $row->payment_status === 'Paid')
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
