<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingStatusLog;
use App\Models\Devotee;
use App\Models\Pooja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The one shared creation/cancellation path for every pooja-booking entry point (devotee
 * self-booking, public no-login booking, Pooja Portal, Ticket POS, Priest Portal) — see
 * the Pooja Bookings plan's Phase A. Existing callers (BookingController::storeBooking(),
 * StaffController::counterBookPooja()) keep their own duplicated insert logic for now and
 * are refactored onto this service as a separate, explicit step so each change stays small
 * and independently verifiable; new entry points (Phases C-F) call this from day one.
 */
class BookingService
{
    /**
     * Creator roles that have already vetted the booking at creation time (staff on a
     * till, an admin/committee member on the Pooja Portal, a priest booking their own
     * slot) — their bookings skip the Pending review step and start life Confirmed. A
     * Devotee or an unauthenticated public booker's booking always starts Pending.
     */
    private const CONFIRMED_ON_CREATE_ROLES = ['Priest', 'Admin', 'Committee', 'Ticket Controller', 'Staff'];

    /**
     * Matches an existing account by email or mobile first (same pattern
     * SystemUserController::store() already uses for "Add X" flows), so a repeat walk-in
     * or public booker is recognised rather than issued a second account. A brand-new
     * match gets a real User+Devotee and a password-setup link — replacing the old
     * offline_{mobile}_{uniqid}@templeconnect.com fake-email pattern everywhere this
     * service is used.
     */
    public function findOrCreateDevotee(string $name, string $mobile, string $email): Devotee
    {
        $user = User::where('email', $email)->orWhere('mobile', $mobile)->first();

        if (!$user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => Hash::make(Str::random(40)),
                'role' => 'Devotee',
                'status' => 'Active',
            ]);
            $user->email_verified_at = now();
            $user->save();

            AccountSetupService::sendPasswordSetupLink($user);
        }

        $devotee = Devotee::where('user_id', $user->id)->first();
        if (!$devotee) {
            $devotee = Devotee::create([
                'user_id' => $user->id,
                'address' => 'Not Specified',
                'gothra' => 'Not Specified',
                'nakshatra' => 'Not Specified',
                'gender' => 'Not Specified',
                'dob' => null,
                'verified' => 1,
            ]);
        }

        return $devotee;
    }

    /**
     * Creates one pooja booking. $data keys: pooja_id, booking_date, booking_time,
     * booking_type (Offline|Online, default Offline), delivery_address (required if
     * Online), payment_method, priest_id (explicit assignment — e.g. a priest booking
     * their own slot) OR priest_option (auto|preferred) + preferred_priest_id, devotee_id
     * (already-known devotee, e.g. the logged-in devotee wizard) OR devotee_name +
     * devotee_mobile + devotee_email (routed through findOrCreateDevotee()), payment_status
     * (default Pending), remarks (optional, logged on the initial status row).
     *
     * $applyMembershipDiscount defaults to true (a devotee's own membership discount is
     * real and should apply everywhere they're recognised); StaffController's counter-
     * booking refactor passes false to keep its pre-existing "no discount for walk-ins"
     * behaviour unchanged.
     */
    public function create(array $data, string $createdByRole, ?int $createdByUserId, bool $applyMembershipDiscount = true): Booking
    {
        $pooja = Pooja::where('pooja_id', $data['pooja_id'])->first();
        if (!$pooja || $pooja->status !== 'Active') {
            throw new \RuntimeException('Selected pooja is inactive or not found.');
        }

        $bookingType = $data['booking_type'] ?? 'Offline';
        if ($bookingType === 'Online' && !$pooja->online_allowed) {
            throw new \RuntimeException("Online bookings are not supported for {$pooja->pooja_name}.");
        }

        $date = $data['booking_date'];
        $time = $data['booking_time'];

        if (!empty($data['devotee_id'])) {
            $devotee = Devotee::where('devotee_id', $data['devotee_id'])->first();
            if (!$devotee) {
                throw new \RuntimeException('Devotee profile not found.');
            }
        } else {
            $devotee = $this->findOrCreateDevotee($data['devotee_name'], $data['devotee_mobile'], $data['devotee_email']);
        }

        $assignedPriestId = $this->resolvePriest($data, $pooja->pooja_id, $date, $time);

        $discountPercentage = 0;
        if ($applyMembershipDiscount && $devotee->membership_id) {
            $membership = DB::table('memberships')->where('membership_id', $devotee->membership_id)->first();
            if ($membership && $membership->status === 'Active') {
                $discountPercentage = $membership->discount_percentage ?? 0;
            }
        }

        $amount = $pooja->pooja_fee;
        $discountAmount = round(($amount * $discountPercentage) / 100, 2);
        $shippingCharge = ($bookingType === 'Online') ? 200.00 : 0.00;
        $totalAmount = $amount - $discountAmount + $shippingCharge;

        $bookingStatus = in_array($createdByRole, self::CONFIRMED_ON_CREATE_ROLES, true) ? 'Confirmed' : 'Pending';
        $paymentMethod = $data['payment_method'] ?? 'Cash';
        $paymentStatus = $data['payment_status'] ?? 'Pending';

        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'devotee_id' => $devotee->devotee_id,
                'pooja_id' => $pooja->pooja_id,
                'priest_id' => $assignedPriestId,
                'booking_date' => $date,
                'booking_time' => $time,
                'booking_type' => $bookingType,
                'delivery_address' => $data['delivery_address'] ?? null,
                'shipping_charge' => $shippingCharge,
                'amount' => $amount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'booking_status' => $bookingStatus,
            ]);

            BookingStatusLog::create([
                'booking_id' => $booking->booking_id,
                'status_from' => null,
                'status_to' => $bookingStatus,
                'changed_by' => $createdByUserId,
                'remarks' => $data['remarks'] ?? "Booking created by {$createdByRole}.",
            ]);

            BookingPayment::create([
                'booking_id' => $booking->booking_id,
                'payment_method' => $paymentMethod,
                'transaction_id' => strtoupper($createdByRole === 'Devotee' || $createdByRole === 'Public' ? 'TXN' . uniqid() : 'OFFLINE-' . uniqid()),
                'amount' => $totalAmount,
                'status' => $paymentStatus,
            ]);

            $devoteeUserId = $devotee->user_id;
            NotificationService::notify($devoteeUserId, "Your pooja booking for {$pooja->pooja_name} on {$date} has been placed. Status: {$bookingStatus}.");
            NotificationService::notifyAdmin("New booking created by {$createdByRole} (Booking ID #{$booking->booking_id}).");
            if ($assignedPriestId) {
                $priestUserId = DB::table('priests')->where('priest_id', $assignedPriestId)->value('user_id');
                if ($priestUserId) {
                    NotificationService::notify($priestUserId, "You've been assigned pooja booking #{$booking->booking_id} ({$pooja->pooja_name}) on {$date} at {$time}.");
                }
            }

            AuditLogService::log("Pooja booking #{$booking->booking_id} created by {$createdByRole} for Devotee ID {$devotee->devotee_id}.", $createdByUserId);

            DB::commit();

            BookingNotificationService::notifyCreated($booking, $createdByRole);
            if ($bookingStatus === 'Confirmed') {
                BookingNotificationService::sendConfirmation($booking);
            }

            return $booking;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancels a booking already created via create() (or the pre-existing storeBooking()/
     * counterBookPooja() insert paths — this doesn't care who created it). Devotee/Priest
     * self-service callers must check ownership themselves before calling this; this
     * method only enforces the status-transition rule (can't cancel what's already
     * Completed or Cancelled).
     */
    public function cancel(Booking $booking, string $cancelledByRole, ?int $cancelledByUserId, ?string $remarks = null): Booking
    {
        if (in_array($booking->booking_status, ['Completed', 'Cancelled'], true)) {
            throw new \RuntimeException("Booking #{$booking->booking_id} is already {$booking->booking_status} and cannot be cancelled.");
        }

        $previousStatus = $booking->booking_status;

        DB::beginTransaction();
        try {
            $booking->booking_status = 'Cancelled';
            $booking->save();

            BookingStatusLog::create([
                'booking_id' => $booking->booking_id,
                'status_from' => $previousStatus,
                'status_to' => 'Cancelled',
                'changed_by' => $cancelledByUserId,
                'remarks' => $remarks ?? "Cancelled by {$cancelledByRole}.",
            ]);

            $devoteeUserId = DB::table('devotees')->where('devotee_id', $booking->devotee_id)->value('user_id');
            if ($devoteeUserId) {
                NotificationService::notify($devoteeUserId, "Your pooja booking #{$booking->booking_id} has been cancelled.");
            }
            if ($booking->priest_id) {
                $priestUserId = DB::table('priests')->where('priest_id', $booking->priest_id)->value('user_id');
                if ($priestUserId) {
                    NotificationService::notify($priestUserId, "Pooja assignment #{$booking->booking_id} has been cancelled.");
                }
            }
            NotificationService::notifyAdmin("Pooja Booking #{$booking->booking_id} has been cancelled by {$cancelledByRole}.");

            AuditLogService::log("Pooja booking #{$booking->booking_id} cancelled by {$cancelledByRole}.", $cancelledByUserId);

            DB::commit();

            return $booking;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Explicit priest_id wins (a priest booking their own slot); otherwise the same
     * preferred-vs-auto choice storeBooking() already offers the devotee wizard.
     */
    private function resolvePriest(array $data, $poojaId, $date, $time): string
    {
        if (!empty($data['priest_id'])) {
            if (!PriestAvailabilityService::isPriestAvailable($data['priest_id'], $poojaId, $date, $time)) {
                throw new \RuntimeException('Selected priest is not available for this slot.');
            }

            return $data['priest_id'];
        }

        $priestOption = $data['priest_option'] ?? 'auto';

        if ($priestOption === 'preferred' && !empty($data['preferred_priest_id'])) {
            if (!PriestAvailabilityService::isPriestAvailable($data['preferred_priest_id'], $poojaId, $date, $time)) {
                throw new \RuntimeException('Selected priest is not available for this slot.');
            }

            return $data['preferred_priest_id'];
        }

        $assigned = PriestAvailabilityService::autoAssignPriest($poojaId, $date, $time);
        if (!$assigned) {
            throw new \RuntimeException('No active priests are available for this pooja at the selected date/time. All priest slots are full.');
        }

        return $assigned;
    }
}
