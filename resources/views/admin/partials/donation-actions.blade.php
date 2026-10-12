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
    <form action="{{ route('admin.donations.approveDevotee', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm that this payment was received? This marks the donation as paid and emails the receipt immediately.')">
        @csrf
        <button type="submit" class="btn-action-approve-prominent" title="Mark as received and email the receipt now">
            <i class="bi bi-check-circle-fill"></i> Confirm &amp; Send Receipt
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
    {{-- An EFT Terminal donation IS the terminal transaction record — editing/deleting it
         here would desync it from what the gateway actually charged. Refund (on the All
         Transactions list) is the only correct way to change one of these; Resend/View stay
         available above/below since they're read-only. A completed (Paid) donation of ANY
         method is locked the same way once it's been counted and (for most methods) a
         receipt has gone out — Resend is the only correction left; a genuine mistake past
         that point needs a Refund/reversal, not a silent edit or delete of settled money. --}}
    @if($canEditDonation && $row->payment_method !== 'EFT Terminal' && $row->payment_status !== 'Paid')
    <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editDevoteeDonationModal{{ $row->id }}">
        <i class="bi bi-pencil-square"></i> Edit
    </button>
    @endif
    @if($canDeleteDonation && $row->payment_method !== 'EFT Terminal' && $row->payment_status !== 'Paid')
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
    <form action="{{ route('admin.donations.approveGuest', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm that this payment was received? This marks the donation as paid and emails the receipt immediately.')">
        @csrf
        <button type="submit" class="btn-action-approve-prominent" title="Mark as received and email the receipt now">
            <i class="bi bi-check-circle-fill"></i> Confirm &amp; Send Receipt
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
    @if($canEditDonation && $row->payment_method !== 'EFT Terminal' && $row->payment_status !== 'Paid')
    <button type="button" class="btn-action-edit" data-bs-toggle="modal" data-bs-target="#editGuestDonationModal{{ $row->id }}">
        <i class="bi bi-pencil-square"></i> Edit
    </button>
    @endif
    @if($canDeleteDonation && $row->payment_method !== 'EFT Terminal' && $row->payment_status !== 'Paid')
    <form action="{{ route('admin.donations.deleteGuest', $row->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this donation record? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-action-delete">
            <i class="bi bi-trash"></i> Delete
        </button>
    </form>
    @endif
@endif
