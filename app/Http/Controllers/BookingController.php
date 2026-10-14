<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\PriestAvailabilityService;
use App\Services\BookingNotificationService;
use App\Services\BookingService;
use App\Services\RecaptchaService;
use App\Models\Setting;
use App\Models\RolePermission;

class BookingController extends Controller
{
    /**
     * Public (no-login) booking page — a trimmed, single-pooja version of the devotee
     * wizard. See the Pooja Bookings plan's Phase C.
     */
    public function showPublicBookingPage()
    {
        $poojas = DB::table('poojas')->where('status', 'Active')->where('category', '!=', 'Custom')->orderBy('pooja_name')->get();

        return view('frontend.book-a-pooja', [
            'poojas' => $poojas,
            'temple' => Setting::templeBranding(),
        ]);
    }

    /**
     * Store a public (no-login) booking — Offline/temple-visit only, auto-assigned
     * priest, Bank Transfer or Cash at Temple. Devotee name/mobile/email are all
     * mandatory (unlike the optional-per-event donation form), and the booking always
     * starts Pending (see BookingService::create()'s role→status rule).
     */
    public function storePublicBooking(Request $request)
    {
        if (!RecaptchaService::verify($request->input('g-recaptcha-response'), $request->ip())) {
            return redirect()->back()->withErrors(['g-recaptcha-response' => 'Please complete the reCAPTCHA verification.'])->withInput();
        }

        $maxDate = date('Y-m-d', strtotime('+10 days'));
        $validated = $request->validate([
            'pooja_id' => 'required|exists:poojas,pooja_id',
            'booking_date' => 'required|date|after_or_equal:today|before_or_equal:' . $maxDate,
            'booking_time' => 'required',
            'devotee_name' => 'required|string|max:255',
            'devotee_mobile' => 'required|digits:10',
            'devotee_email' => 'required|email|max:255',
            'payment_method' => 'required|in:Bank,Cash',
        ]);

        try {
            $booking = (new BookingService())->create([
                'pooja_id' => $validated['pooja_id'],
                'booking_date' => $validated['booking_date'],
                'booking_time' => $validated['booking_time'],
                'booking_type' => 'Offline',
                'payment_method' => $validated['payment_method'],
                'devotee_name' => $validated['devotee_name'],
                'devotee_mobile' => $validated['devotee_mobile'],
                'devotee_email' => $validated['devotee_email'],
                'remarks' => 'Booked publicly, no login.',
            ], 'Public', null);

            return redirect()->back()->with('success_booking', "Thank you! Your pooja booking (#{$booking->booking_id}) has been recorded and is pending confirmation. We'll email you once it's confirmed, with payment details.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Shared "Create Booking" action for the Pooja Portal's Manage Bookings modal (Phase D)
     * and the Ticket POS "Book Pooja" modal (Phase E) — same form, same route, gated by
     * the Role Permissions grid ('bookings', 'add') rather than a fixed role list, same
     * pattern the page's own view-check already uses. Responds with JSON for an AJAX
     * caller (Ticket POS) or a redirect+flash for a plain form post (Pooja Portal modal).
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role) : null;
        if (!$user || !RolePermission::can($activeRole, 'bookings', 'add')) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $maxDate = date('Y-m-d', strtotime('+10 days'));
        $isCustom = $request->boolean('custom_pooja');

        $validated = $request->validate([
            'pooja_id' => $isCustom ? 'nullable' : 'required|exists:poojas,pooja_id',
            'custom_pooja_label' => 'required_if:custom_pooja,1|nullable|string|max:255',
            'custom_fee' => 'required_if:custom_pooja,1|nullable|numeric|min:0',
            'booking_date' => 'required|date|after_or_equal:today|before_or_equal:' . $maxDate,
            'booking_time' => 'required',
            'devotee_name' => 'required|string|max:255',
            'devotee_mobile' => 'required|digits:10',
            'devotee_email' => 'required|email|max:255',
            'priest_option' => 'nullable|in:auto,preferred',
            'preferred_priest_id' => 'required_if:priest_option,preferred|nullable|exists:priests,priest_id',
            'payment_method' => 'required|in:Cash,Counter,Bank',
            'participant_details' => 'nullable|string|max:2000',
        ]);

        // Cash/Counter means the devotee paid right there at the counter — mark it Paid
        // immediately rather than leaving it Pending (which previously meant every POS/
        // Portal booking sat unpaid until someone manually flipped it later). Bank Transfer
        // is the one method that's genuinely still pending until the transfer is confirmed.
        $paymentStatus = in_array($validated['payment_method'], ['Cash', 'Counter'], true) ? 'Paid' : 'Pending';

        $poojaId = $isCustom ? (int) Setting::get('custom_pooja_id') : $validated['pooja_id'];
        if ($isCustom && !$poojaId) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Custom pooja is not available right now — please contact an admin.'], 422);
            }

            return redirect()->back()->with('error', 'Custom pooja is not available right now — please contact an admin.')->withInput();
        }

        try {
            $booking = (new BookingService())->create([
                'pooja_id' => $poojaId,
                'custom_pooja_label' => $isCustom ? $validated['custom_pooja_label'] : null,
                'custom_fee' => $isCustom ? $validated['custom_fee'] : null,
                'booking_date' => $validated['booking_date'],
                'booking_time' => $validated['booking_time'],
                'booking_type' => 'Offline',
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'priest_option' => $validated['priest_option'] ?? 'auto',
                'preferred_priest_id' => $validated['preferred_priest_id'] ?? null,
                'devotee_name' => $validated['devotee_name'],
                'devotee_mobile' => $validated['devotee_mobile'],
                'devotee_email' => $validated['devotee_email'],
                'participant_details' => $validated['participant_details'] ?? null,
                'remarks' => "Booking created by {$activeRole} via the Pooja Portal/Ticket POS.",
            ], $activeRole, $user->id);

            $displayName = $isCustom ? $validated['custom_pooja_label'] : DB::table('poojas')->where('pooja_id', $poojaId)->value('pooja_name');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Booking #{$booking->booking_id} created and Confirmed.",
                    'booking' => [
                        'booking_id' => $booking->booking_id,
                        'pooja_name' => $displayName,
                        'devotee_name' => $validated['devotee_name'],
                        'booking_date' => $booking->booking_date,
                        'booking_time' => $booking->booking_time,
                        'total_amount' => number_format($booking->total_amount, 2),
                    ],
                ]);
            }

            return redirect()->back()->with('success', "Booking #{$booking->booking_id} created and Confirmed.");
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Server-side creation of a pooja booking once an EFT Terminal purchase for it is
     * confirmed approved — called from DonationController::
     * createDonationIfApprovedPurchaseUnrecorded(), the same "details captured in the
     * ledger row's meta at startEftCharge() time" pattern TicketController::
     * createOrderFromLedgerMeta() already uses for a ticket order paid the same way.
     * pooja_id in $meta is already resolved (including the Custom Pooja anchor id) by
     * startEftCharge() itself, so this never needs to re-resolve it.
     */
    public function createBookingFromLedgerMeta(array $meta, string $transactionId): ?int
    {
        if (empty($meta['pooja_id']) || empty($meta['booking_date']) || empty($meta['booking_time'])) {
            return null;
        }

        try {
            $booking = (new BookingService())->create([
                'pooja_id' => $meta['pooja_id'],
                'custom_pooja_label' => $meta['custom_pooja_label'] ?? null,
                'custom_fee' => $meta['custom_fee'] ?? null,
                'booking_date' => $meta['booking_date'],
                'booking_time' => $meta['booking_time'],
                'booking_type' => 'Offline',
                'payment_method' => 'EFT Terminal',
                'payment_status' => 'Paid',
                'priest_option' => $meta['priest_option'] ?? 'auto',
                'preferred_priest_id' => $meta['preferred_priest_id'] ?? null,
                'devotee_name' => $meta['devotee_name'] ?? '',
                'devotee_mobile' => $meta['devotee_mobile'] ?? '',
                'devotee_email' => $meta['devotee_email'] ?? '',
                'participant_details' => $meta['participant_details'] ?? null,
                'remarks' => "Booking created by {$meta['created_by_role']} via EFT Terminal (transaction {$transactionId}).",
            ], $meta['created_by_role'] ?? 'Staff', $meta['created_by_user_id'] ?? null);

            return $booking->booking_id;
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Devotee Book Pooja Page
     */
    public function bookPoojaPage()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Get devotee info
        $devotee = DB::table('devotees')
            ->where('user_id', $user->id)
            ->first();

        // Get active memberships
        $membership = null;
        if ($devotee && $devotee->membership_id) {
            $membership = DB::table('memberships')
                ->where('membership_id', $devotee->membership_id)
                ->first();
        }

        // Get active poojas
        $poojas = DB::table('poojas')
            ->where('status', 'Active')
            ->where('category', '!=', 'Custom')
            ->get();

        // Get active priests for Gold/Platinum preferred selection
        $priestQuery = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->whereIn('priests.employment_status', ['Active', 'On Leave'])
            ->select('priests.*', 'users.name');

        if (Auth::check()) {
            $priestQuery->where('users.id', '!=', Auth::id());
        }

        $priests = $priestQuery->get();

        return view('devotee.book_pooja', compact('user', 'devotee', 'membership', 'poojas', 'priests'));
    }

    /**
     * AJAX Check Availability for a Date, Time, and Pooja
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'pooja_id' => 'required|exists:poojas,pooja_id',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required'
        ]);

        $poojaId = $request->pooja_id;
        $date = $request->booking_date;
        $time = $request->booking_time;

        // Get active priests
        $priestsQuery = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->whereIn('priests.employment_status', ['Active', 'On Leave'])
            ->select('priests.*', 'users.name');

        if (Auth::check()) {
            $priestsQuery->where('users.id', '!=', Auth::id());
        }

        $activePriests = $priestsQuery->get();

        $availablePriests = [];

        foreach ($activePriests as $priest) {
            if ($this->isPriestAvailable($priest->priest_id, $poojaId, $date, $time)) {
                // Get workload count for sorting
                $workload = DB::table('pooja_bookings')
                    ->where('priest_id', $priest->priest_id)
                    ->where('booking_date', $date)
                    ->where('booking_status', '!=', 'Cancelled')
                    ->count();

                $availablePriests[] = [
                    'priest_id' => $priest->priest_id,
                    'name' => $priest->name,
                    'specialization' => $priest->specialization,
                    'experience' => $priest->experience_years ?? 0,
                    'workload' => $workload
                ];
            }
        }

        return response()->json([
            'available' => count($availablePriests) > 0,
            'priests' => $availablePriests
        ]);
    }

    /**
     * AJAX Check Date Status (Available, Limited, Fully Booked)
     */
    public function checkDateStatus(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        $start = strtotime($request->start_date);
        $end = strtotime($request->end_date);
        $data = [];

        // Check each date
        for ($current = $start; $current <= $end; $current = strtotime("+1 day", $current)) {
            $dateStr = date('Y-m-d', $current);
            $data[$dateStr] = $this->getDateAvailabilityStatus($dateStr);
        }

        // Find next available date if today is fully booked
        $nextAvailableDate = null;
        $todayStr = date('Y-m-d');
        if ($this->getDateAvailabilityStatus($todayStr) === 'Fully Booked') {
            for ($i = 1; $i <= 30; $i++) {
                $checkDate = date('Y-m-d', strtotime("+$i days"));
                if ($this->getDateAvailabilityStatus($checkDate) !== 'Fully Booked') {
                    $nextAvailableDate = $checkDate;
                    break;
                }
            }
        }

        return response()->json([
            'dates' => $data,
            'next_available_date' => $nextAvailableDate
        ]);
    }

    /**
     * Store Bookings (Support Multi-ritual booking)
     */
    public function storeBooking(Request $request)
    {
        $maxDate = date('Y-m-d', strtotime('+10 days'));
        $request->validate([
            'bookings' => 'required|array|min:1',
            'bookings.*.pooja_id' => 'required|exists:poojas,pooja_id',
            'bookings.*.booking_date' => 'required|date|after_or_equal:today|before_or_equal:' . $maxDate,
            'bookings.*.booking_time' => 'required',
            'bookings.*.booking_type' => 'required|in:Offline,Online',
            'bookings.*.delivery_address' => 'required_if:bookings.*.booking_type,Online|nullable|string',
            'bookings.*.priest_option' => 'required|in:auto,preferred',
            'bookings.*.preferred_priest_id' => 'required_if:bookings.*.priest_option,preferred|nullable|exists:priests,priest_id',
            'payment_method' => 'required|in:UPI,Razorpay,Cash,Counter'
        ]);

        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        if (!$devotee) {
            return redirect()->back()->with('error', 'Devotee profile not found.');
        }

        // Get membership discounts
        $discountPercentage = 0;
        if ($devotee->membership_id) {
            $membership = DB::table('memberships')->where('membership_id', $devotee->membership_id)->first();
            if ($membership && $membership->status === 'Active') {
                $discountPercentage = $membership->discount_percentage ?? 0;
            }
        }

        DB::beginTransaction();
        $createdBookings = [];

        try {
            foreach ($request->bookings as $item) {
                $poojaId = $item['pooja_id'];
                $date = $item['booking_date'];
                $time = $item['booking_time'];
                $bookingType = $item['booking_type'];
                $address = $item['delivery_address'] ?? null;
                $priestOption = $item['priest_option'];
                $prefPriestId = $item['preferred_priest_id'] ?? null;

                $pooja = DB::table('poojas')->where('pooja_id', $poojaId)->first();
                if (!$pooja || $pooja->status !== 'Active') {
                    throw new \Exception("Selected pooja is inactive or not found.");
                }

                // If online selected but not allowed
                if ($bookingType === 'Online' && !$pooja->online_allowed) {
                    throw new \Exception("Online bookings are not supported for {$pooja->pooja_name}.");
                }

                // Pricing calculations
                $amount = $pooja->pooja_fee;
                $discountAmount = round(($amount * $discountPercentage) / 100, 2);
                $shippingCharge = ($bookingType === 'Online') ? 200.00 : 0.00;
                $totalAmount = $amount - $discountAmount + $shippingCharge;

                // Priest Assignment — never mandatory. A preferred priest who turns out to
                // be unavailable, or no one at all on auto-assign, just leaves the booking
                // unassigned (priest_id null) rather than blocking it; an admin can assign
                // one later from Manage Bookings' "Reassign Priest" action.
                $assignedPriestId = null;

                if ($priestOption === 'preferred' && $prefPriestId) {
                    if ($this->isPriestAvailable($prefPriestId, $poojaId, $date, $time)) {
                        $assignedPriestId = $prefPriestId;
                    }
                } else {
                    $assignedPriestId = $this->autoAssignPriest($poojaId, $date, $time);
                }

                // Create Pooja Booking record
                $bookingId = DB::table('pooja_bookings')->insertGetId([
                    'devotee_id' => $devotee->devotee_id,
                    'pooja_id' => $poojaId,
                    'priest_id' => $assignedPriestId,
                    'booking_date' => $date,
                    'booking_time' => $time,
                    'booking_type' => $bookingType,
                    'delivery_address' => $address,
                    'shipping_charge' => $shippingCharge,
                    'amount' => $amount,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $totalAmount,
                    'payment_method' => $request->payment_method,
                    'payment_status' => 'Pending',
                    'booking_status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Create Status Log
                DB::table('booking_status_logs')->insert([
                    'booking_id' => $bookingId,
                    'status_from' => null,
                    'status_to' => 'Pending',
                    'changed_by' => $user->id,
                    'remarks' => 'Booking created by Devotee.',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Notify devotee
                NotificationService::notify($user->id, "Your pooja booking for {$pooja->pooja_name} on {$date} has been placed. Status: Pending.");

                // Notify admin
                NotificationService::notifyAdmin("New booking created by devotee (Booking ID #{$bookingId}).");

                // Audit log
                AuditLogService::log("Pooja booking created for Devotee ID {$devotee->devotee_id} (Booking ID #{$bookingId})");

                // Create Payment record
                DB::table('booking_payments')->insert([
                    'booking_id' => $bookingId,
                    'payment_method' => $request->payment_method,
                    'transaction_id' => 'TXN' . strtoupper(uniqid()),
                    'amount' => $totalAmount,
                    'status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $createdBookings[] = $bookingId;
            }

            DB::commit();

            // Email counterpart to the in-app notify() calls above — see the Pooja
            // Bookings plan's Phase B. Every booking created here starts Pending (see the
            // insert above), so only the "needs attention" email applies; the confirmation
            // email is sent later, once an admin/priest actually confirms it.
            foreach ($createdBookings as $createdBookingId) {
                $createdBooking = \App\Models\Booking::find($createdBookingId);
                if ($createdBooking) {
                    BookingNotificationService::notifyCreated($createdBooking, 'Devotee');
                }
            }

            if (in_array($request->payment_method, ['UPI', 'Razorpay'])) {
                return redirect()->route('devotee.payment', [
                    'type' => 'pooja',
                    'booking_ids' => implode(',', $createdBookings)
                ]);
            }

            return redirect()->route('devotee.dashboard')
                ->with('success', 'Bookings placed successfully! Payment status is Pending.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to place booking: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Download Receipt
     */
    public function downloadReceipt($id)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $booking = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->join('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->join('users as devotee_user', 'devotees.user_id', '=', 'devotee_user.id')
            // LEFT JOIN — priest assignment is optional, an inner join here would silently
            // make an unassigned booking's receipt unreachable (first() finds nothing).
            ->leftJoin('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->leftJoin('users as priest_user', 'priests.user_id', '=', 'priest_user.id')
            ->where('pooja_bookings.booking_id', $id)
            ->select(
                'pooja_bookings.*',
                'poojas.pooja_name',
                'devotee_user.name as devotee_name',
                'devotee_user.mobile as devotee_mobile',
                'priest_user.name as priest_name'
            )
            ->first();

        if (!$booking) {
            return redirect()->back()->with('error', 'Booking record not found.');
        }

        // Verify authorization (only admin or the devotee themselves can download)
        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        if ($user->role !== 'Admin' && (!$devotee || $devotee->devotee_id !== $booking->devotee_id)) {
            return abort(403, 'Unauthorized action.');
        }

        // Generate Text Receipt
        $currency = Setting::get('currency_code', 'AUD');
        $receipt = "==================================================\n";
        $receipt .= "            🛕 TEMPLE ERP POOJA RECEIPT           \n";
        $receipt .= "==================================================\n";
        $receipt .= "Receipt No   : REC" . str_pad($booking->booking_id, 6, '0', STR_PAD_LEFT) . "\n";
        $receipt .= "Booking Date : " . $booking->booking_date . "\n";
        $receipt .= "Booking Time : " . date('h:i A', strtotime($booking->booking_time)) . "\n";
        $receipt .= "Status       : " . $booking->booking_status . "\n";
        $receipt .= "--------------------------------------------------\n";
        $receipt .= "Devotee Name : " . $booking->devotee_name . "\n";
        $receipt .= "Mobile       : " . $booking->devotee_mobile . "\n";
        $receipt .= "Pooja Name   : " . ($booking->custom_pooja_label ?: $booking->pooja_name) . "\n";
        $receipt .= "Pooja Mode   : " . $booking->booking_type . "\n";
        if ($booking->booking_type === 'Online') {
            $receipt .= "Delivery Addr: " . $booking->delivery_address . "\n";
        }
        $receipt .= "Assigned Priest: " . ($booking->priest_name ?: 'Not yet assigned') . "\n";
        $receipt .= "--------------------------------------------------\n";
        $receipt .= "Pooja Base Price  : " . $currency . " " . number_format($booking->amount, 2) . "\n";
        $receipt .= "Membership Discount: -" . $currency . " " . number_format($booking->discount_amount, 2) . "\n";
        if ($booking->booking_type === 'Online') {
            $receipt .= "Shipping Fee      : " . $currency . " " . number_format($booking->shipping_charge, 2) . "\n";
        }
        $receipt .= "--------------------------------------------------\n";
        $receipt .= "TOTAL PAID        : " . $currency . " " . number_format($booking->total_amount, 2) . "\n";
        $receipt .= "Payment Method    : " . $booking->payment_method . "\n";
        $receipt .= "Payment Status    : " . $booking->payment_status . "\n";
        $receipt .= "==================================================\n";
        $receipt .= "          Thank you for your devotion!            \n";
        $receipt .= "==================================================\n";

        $filename = "Pooja_Receipt_" . $booking->booking_id . ".txt";

        return Response::make($receipt, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename=' . $filename
        ]);
    }

    /**
     * Devotee self-service cancel — scoped to their own booking, see the Pooja Bookings
     * plan's Phase A. The admin cancel path (updateStatus() below) stays unrestricted.
     */
    public function cancelBooking(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        $booking = \App\Models\Booking::where('booking_id', $id)->first();

        if (!$booking || !$devotee || $booking->devotee_id !== $devotee->devotee_id) {
            return redirect()->back()->with('error', 'Booking not found or not yours to cancel.');
        }

        try {
            (new \App\Services\BookingService())->cancel($booking, 'Devotee', $user->id, $request->input('remarks'));

            return redirect()->back()->with('success', "Booking #{$id} has been cancelled.");
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Admin "Confirm & Send Email" action on a Pending booking — sets it Confirmed and
     * emails the devotee the confirmation (pooja/date/time/priest/cost + both payment
     * options). See the Pooja Bookings plan's Phase B.
     */
    public function confirmAndNotify(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'bookings', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $booking = \App\Models\Booking::where('booking_id', $id)->first();
        if (!$booking) {
            return redirect()->back()->with('error', 'Booking not found.');
        }

        if ($booking->booking_status !== 'Pending') {
            return redirect()->back()->with('error', "Booking #{$id} is not Pending.");
        }

        DB::beginTransaction();
        try {
            $previousStatus = $booking->booking_status;
            $booking->booking_status = 'Confirmed';
            $booking->save();

            DB::table('booking_status_logs')->insert([
                'booking_id' => $id,
                'status_from' => $previousStatus,
                'status_to' => 'Confirmed',
                'changed_by' => $user->id,
                'remarks' => 'Confirmed by admin, confirmation email sent.',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            AuditLogService::log("Pooja booking #{$id} confirmed and notified by admin.");

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to confirm booking: ' . $e->getMessage());
        }

        BookingNotificationService::sendConfirmation($booking->fresh());

        return redirect()->back()->with('success', "Booking #{$id} confirmed and confirmation email sent.");
    }

    /**
     * Admin: Manage Bookings Control Panel
     */
    public function manageBookings(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'bookings', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $query = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->join('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->join('users as devotee_user', 'devotees.user_id', '=', 'devotee_user.id')
            // LEFT JOIN — priest assignment is optional (see BookingService::resolvePriest()),
            // an inner join here would silently drop every unassigned booking from this list.
            ->leftJoin('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->leftJoin('users as priest_user', 'priests.user_id', '=', 'priest_user.id')
            ->select(
                'pooja_bookings.*',
                'poojas.pooja_name',
                'devotee_user.name as devotee_name',
                'devotee_user.mobile as devotee_mobile',
                'priest_user.name as priest_name'
            );

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('devotee_user.name', 'like', "%$search%")
                  ->orWhere('devotee_user.mobile', 'like', "%$search%")
                  ->orWhere('poojas.pooja_name', 'like', "%$search%")
                  ->orWhere('priest_user.name', 'like', "%$search%");
            });
        }

        if ($request->filled('booking_status')) {
            $query->where('pooja_bookings.booking_status', $request->booking_status);
        }

        if ($request->filled('booking_type')) {
            $query->where('pooja_bookings.booking_type', $request->booking_type);
        }

        if ($request->filled('date')) {
            $query->where('pooja_bookings.booking_date', $request->date);
        }

        $bookings = $query->orderBy('pooja_bookings.booking_date', 'desc')
            ->orderBy('pooja_bookings.booking_time', 'asc')
            ->get();

        // Get active priests for manual overrides
        $priests = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->whereIn('priests.employment_status', ['Active', 'On Leave'])
            ->select('priests.*', 'users.name')
            ->get();

        // Get all approved leave requests
        $leaves = DB::table('leave_requests')
            ->where('status', 'Approved')
            ->get();

        // Active poojas for the "Create Booking" modal (Phase D) — the page never needed
        // this list before, since it only ever viewed/managed bookings already created
        // elsewhere. The 'Custom' anchor row is excluded — it's offered via its own
        // toggle in the modal, not as a $0 catalog pick.
        $poojas = DB::table('poojas')->where('status', 'Active')->where('category', '!=', 'Custom')->orderBy('pooja_name')->get();
        $canAddBookings = RolePermission::can($user ? session('active_role', $user->role) : null, 'bookings', 'add');

        // Utilization metrics
        $totalBookings = DB::table('pooja_bookings')->where('booking_status', '!=', 'Cancelled')->count();
        $totalRevenue = DB::table('pooja_bookings')->where('payment_status', 'Paid')->sum('total_amount');
        $totalDiscount = DB::table('pooja_bookings')->sum('discount_amount');

        // The Pooja Portal embeds this same page, unmodified, in an iframe pane — ?embed=1
        // just tells the layout to drop its own sidebar/topbar so it doesn't double up
        // inside the Portal's own chrome.
        $embed = $request->boolean('embed');

        return view('admin.manage-bookings', compact(
            'bookings',
            'priests',
            'poojas',
            'canAddBookings',
            'leaves',
            'totalBookings',
            'totalRevenue',
            'totalDiscount',
            'embed'
        ));
    }

    /**
     * Admin Override Priest
     */
    public function overridePriest(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'bookings', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'priest_id' => 'required|exists:priests,priest_id'
        ]);

        $booking = DB::table('pooja_bookings')->where('booking_id', $id)->first();
        if (!$booking) {
            return redirect()->back()->with('error', 'Booking not found.');
        }

        // Check if selected priest is active & available
        if (!$this->isPriestAvailable($request->priest_id, $booking->pooja_id, $booking->booking_date, $booking->booking_time)) {
            return redirect()->back()->with('error', 'Selected priest is not available or has schedule conflict.');
        }

        DB::beginTransaction();
        try {
            DB::table('pooja_bookings')
                ->where('booking_id', $id)
                ->update([
                    'priest_id' => $request->priest_id,
                    'updated_at' => now()
                ]);

            DB::table('booking_status_logs')->insert([
                'booking_id' => $id,
                'status_from' => $booking->booking_status,
                'status_to' => $booking->booking_status,
                'changed_by' => Auth::id(),
                'remarks' => "Priest manual override to Priest ID: {$request->priest_id} by Admin.",
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Notify devotee
            $devoteeUser = DB::table('devotees')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('devotees.devotee_id', $booking->devotee_id)
                ->select('users.id')
                ->first();
            if ($devoteeUser) {
                NotificationService::notify($devoteeUser->id, "A priest has been manually assigned/updated for your pooja booking #{$id}.");
            }
            
            // Notify new priest
            $priestUser = DB::table('priests')
                ->where('priest_id', $request->priest_id)
                ->first();
            if ($priestUser) {
                NotificationService::notify($priestUser->user_id, "New pooja assignment: Pooja booking #{$id} has been assigned to you.");
            }

            // Audit log
            AuditLogService::log("Admin overridden priest for Booking ID {$id} to Priest {$request->priest_id}");

            DB::commit();
            return redirect()->back()->with('success', 'Priest reassigned successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Reassignment failed: ' . $e->getMessage());
        }
    }

    /**
     * Admin Reschedule Booking
     */
    public function reschedule(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'bookings', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required'
        ]);

        $booking = DB::table('pooja_bookings')->where('booking_id', $id)->first();
        if (!$booking) {
            return redirect()->back()->with('error', 'Booking not found.');
        }

        // Check if current priest is available for the new slot
        $priestId = $booking->priest_id;
        $isAvailable = $this->isPriestAvailable($priestId, $booking->pooja_id, $request->booking_date, $request->booking_time);

        if (!$isAvailable) {
            // Auto-assign another available priest if current priest is busy
            $priestId = $this->autoAssignPriest($booking->pooja_id, $request->booking_date, $request->booking_time);
            if (!$priestId) {
                return redirect()->back()->with('error', 'No active priests are available for the selected slot.');
            }
        }

        DB::beginTransaction();
        try {
            DB::table('pooja_bookings')
                ->where('booking_id', $id)
                ->update([
                    'booking_date' => $request->booking_date,
                    'booking_time' => $request->booking_time,
                    'priest_id' => $priestId,
                    'updated_at' => now()
                ]);

            DB::table('booking_status_logs')->insert([
                'booking_id' => $id,
                'status_from' => $booking->booking_status,
                'status_to' => $booking->booking_status,
                'changed_by' => Auth::id(),
                'remarks' => "Booking rescheduled to {$request->booking_date} {$request->booking_time}.",
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Notify devotee
            $devoteeUser = DB::table('devotees')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('devotees.devotee_id', $booking->devotee_id)
                ->select('users.id')
                ->first();
            if ($devoteeUser) {
                NotificationService::notify($devoteeUser->id, "Your pooja booking #{$id} has been rescheduled to {$request->booking_date} at {$request->booking_time}.");
            }
            
            // Notify priest
            $priestUser = DB::table('priests')
                ->where('priest_id', $priestId)
                ->first();
            if ($priestUser) {
                NotificationService::notify($priestUser->user_id, "Pooja booking assignment #{$id} has been rescheduled to {$request->booking_date} at {$request->booking_time}.");
            }

            // Audit log
            AuditLogService::log("Admin rescheduled Booking ID {$id} to {$request->booking_date} {$request->booking_time}");

            DB::commit();
            return redirect()->back()->with('success', 'Booking rescheduled successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Rescheduling failed: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Update Booking & Payment Status
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'bookings', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'booking_status' => 'nullable|in:Pending,Confirmed,Completed,Cancelled',
            'payment_status' => 'nullable|in:Pending,Paid,Failed,Refunded',
            'remarks' => 'nullable|string'
        ]);

        $booking = DB::table('pooja_bookings')->where('booking_id', $id)->first();
        if (!$booking) {
            return redirect()->back()->with('error', 'Booking not found.');
        }

        DB::beginTransaction();
        try {
            $updateData = [];
            if ($request->filled('booking_status')) {
                $updateData['booking_status'] = $request->booking_status;
            }
            if ($request->filled('payment_status')) {
                $updateData['payment_status'] = $request->payment_status;
                
                // Update booking_payments status
                DB::table('booking_payments')
                    ->where('booking_id', $id)
                    ->update(['status' => $request->payment_status, 'updated_at' => now()]);
            }

            if (count($updateData) > 0) {
                $updateData['updated_at'] = now();
                DB::table('pooja_bookings')->where('booking_id', $id)->update($updateData);

                // Insert Log
                DB::table('booking_status_logs')->insert([
                    'booking_id' => $id,
                    'status_from' => $booking->booking_status,
                    'status_to' => $request->booking_status ?? $booking->booking_status,
                    'changed_by' => Auth::id(),
                    'remarks' => $request->remarks ?? 'Status updated by Admin.',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Notify devotee
                $devoteeUser = DB::table('devotees')
                    ->join('users', 'devotees.user_id', '=', 'users.id')
                    ->where('devotees.devotee_id', $booking->devotee_id)
                    ->select('users.id')
                    ->first();
                if ($devoteeUser) {
                    if ($request->filled('booking_status')) {
                        NotificationService::notify($devoteeUser->id, "Your pooja booking #{$id} status has been updated to {$request->booking_status}.");
                    }
                    if ($request->filled('payment_status')) {
                        NotificationService::notify($devoteeUser->id, "Your payment status for pooja booking #{$id} has been updated to {$request->payment_status}.");
                    }
                }
                
                // If payment is Paid
                if ($request->payment_status === 'Paid') {
                    NotificationService::notifyAdmin("Payment completed for Pooja Booking #{$id}.");
                }

                // If booking status is Cancelled, notify priest
                if ($request->booking_status === 'Cancelled' && $booking->priest_id) {
                    $priestUser = DB::table('priests')
                        ->where('priest_id', $booking->priest_id)
                        ->first();
                    if ($priestUser) {
                        NotificationService::notify($priestUser->user_id, "Pooja assignment #{$id} has been cancelled.");
                    }
                    NotificationService::notifyAdmin("Pooja Booking #{$id} has been cancelled.");
                }

                // Audit log
                AuditLogService::log("Admin updated status of Booking ID {$id} to status: {$request->booking_status}, payment: {$request->payment_status}");
            }

            DB::commit();
            return redirect()->back()->with('success', 'Booking status updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    // ============================================
    // PRIVATE HELPER UTILITIES
    // ============================================

    /**
     * Workload-Balanced Auto Priest Assignment — delegates to PriestAvailabilityService
     * (see the Pooja Bookings plan's Phase A), which also fixes a real bug this method
     * used to carry: the old inline isPriestAvailable() below selected poojas.duration_
     * minutes, a column that has never existed, so every availability check threw a SQL
     * error. Signature kept identical (3 args) since StaffController::counterBookPooja()
     * still calls this via `new BookingController()->autoAssignPriest(...)`.
     */
    public function autoAssignPriest($poojaId, $date, $time)
    {
        return PriestAvailabilityService::autoAssignPriest($poojaId, $date, $time, Auth::id());
    }

    /**
     * Check if a priest is available based on overlap and capacity rules — delegates to
     * PriestAvailabilityService; see autoAssignPriest()'s docblock above.
     */
    public function isPriestAvailable($priestId, $poojaId, $date, $time)
    {
        return PriestAvailabilityService::isPriestAvailable($priestId, $poojaId, $date, $time, Auth::id());
    }

    /**
     * Compute Overall Date Availability Status
     */
    private function getDateAvailabilityStatus($date)
    {
        $activePriests = DB::table('priests')->where('employment_status', 'Active')->count();
        if ($activePriests === 0) return 'Fully Booked';

        // Check slots: 9 AM, 10 AM, 11 AM, 12 PM, 2 PM, 3 PM, 4 PM, 5 PM
        $testSlots = ['09:00:00', '10:00:00', '11:00:00', '12:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00'];
        
        $totalPotentialSlots = $activePriests * count($testSlots);
        $totalBookedSlots = DB::table('pooja_bookings')
            ->where('booking_date', $date)
            ->where('booking_status', '!=', 'Cancelled')
            ->count();

        if ($totalBookedSlots >= $totalPotentialSlots) {
            return 'Fully Booked';
        } elseif ($totalBookedSlots > ($totalPotentialSlots * 0.7)) {
            return 'Limited';
        } else {
            return 'Available';
        }
    }
}
