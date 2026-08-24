<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Services\AuditLogService;
use App\Services\NotificationService;

class BookingController extends Controller
{
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

                // Priest Assignment
                $assignedPriestId = null;

                if ($priestOption === 'preferred' && $prefPriestId) {
                    // Check availability of preferred priest
                    if (!$this->isPriestAvailable($prefPriestId, $poojaId, $date, $time)) {
                        $priestRecord = DB::table('priests')->where('user_id', Auth::id())->first();
                        if ($priestRecord && $priestRecord->priest_id == $prefPriestId) {
                            throw new \Exception("No eligible priest available for selected slot.");
                        }
                        throw new \Exception("Selected priest is not available for {$pooja->pooja_name} on {$date} at {$time}.");
                    }
                    $assignedPriestId = $prefPriestId;
                } else {
                    // Auto assign least busy priest
                    $assignedPriestId = $this->autoAssignPriest($poojaId, $date, $time);
                    if (!$assignedPriestId) {
                        $priestRecord = DB::table('priests')->where('user_id', Auth::id())->first();
                        if ($priestRecord) {
                            throw new \Exception("No eligible priest available for selected slot.");
                        }
                        throw new \Exception("No active priests are available for {$pooja->pooja_name} on {$date} at {$time}. All priest slots are full.");
                    }
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
            ->join('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->join('users as priest_user', 'priests.user_id', '=', 'priest_user.id')
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
        $receipt .= "Pooja Name   : " . $booking->pooja_name . "\n";
        $receipt .= "Pooja Mode   : " . $booking->booking_type . "\n";
        if ($booking->booking_type === 'Online') {
            $receipt .= "Delivery Addr: " . $booking->delivery_address . "\n";
        }
        $receipt .= "Assigned Priest: " . $booking->priest_name . "\n";
        $receipt .= "--------------------------------------------------\n";
        $receipt .= "Pooja Base Price  : ₹" . number_format($booking->amount, 2) . "\n";
        $receipt .= "Membership Discount: -₹" . number_format($booking->discount_amount, 2) . "\n";
        if ($booking->booking_type === 'Online') {
            $receipt .= "Shipping Fee      : ₹" . number_format($booking->shipping_charge, 2) . "\n";
        }
        $receipt .= "--------------------------------------------------\n";
        $receipt .= "TOTAL PAID        : ₹" . number_format($booking->total_amount, 2) . "\n";
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
     * Admin: Manage Bookings Control Panel
     */
    public function manageBookings(Request $request)
    {
        $query = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->join('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->join('users as devotee_user', 'devotees.user_id', '=', 'devotee_user.id')
            ->join('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->join('users as priest_user', 'priests.user_id', '=', 'priest_user.id')
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

        // Utilization metrics
        $totalBookings = DB::table('pooja_bookings')->where('booking_status', '!=', 'Cancelled')->count();
        $totalRevenue = DB::table('pooja_bookings')->where('payment_status', 'Paid')->sum('total_amount');
        $totalDiscount = DB::table('pooja_bookings')->sum('discount_amount');

        return view('admin.manage-bookings', compact(
            'bookings',
            'priests',
            'leaves',
            'totalBookings',
            'totalRevenue',
            'totalDiscount'
        ));
    }

    /**
     * Admin Override Priest
     */
    public function overridePriest(Request $request, $id)
    {
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
     * Workload-Balanced Auto Priest Assignment
     */
    public function autoAssignPriest($poojaId, $date, $time)
    {
        $activePriestsQuery = DB::table('priests')
            ->whereIn('employment_status', ['Active', 'On Leave']);

        if (Auth::check()) {
            $activePriestsQuery->where('user_id', '!=', Auth::id());
        }

        $activePriests = $activePriestsQuery->get();

        $candidatePriests = [];

        foreach ($activePriests as $priest) {
            if ($this->isPriestAvailable($priest->priest_id, $poojaId, $date, $time)) {
                // Count how many bookings they have on that date
                $bookingsCount = DB::table('pooja_bookings')
                    ->where('priest_id', $priest->priest_id)
                    ->where('booking_date', $date)
                    ->where('booking_status', '!=', 'Cancelled')
                    ->count();

                $candidatePriests[] = [
                    'priest_id' => $priest->priest_id,
                    'bookings_count' => $bookingsCount
                ];
            }
        }

        if (count($candidatePriests) === 0) {
            return null;
        }

        // Sort candidates by workload ascending (workload balance)
        usort($candidatePriests, function($a, $b) {
            return $a['bookings_count'] <=> $b['bookings_count'];
        });

        return $candidatePriests[0]['priest_id'];
    }

    /**
     * Check if a priest is available based on overlap and capacity rules
     */
    public function isPriestAvailable($priestId, $poojaId, $date, $time)
    {
        $pooja = DB::table('poojas')->where('pooja_id', $poojaId)->first();
        if (!$pooja) return false;

        $priest = DB::table('priests')->where('priest_id', $priestId)->first();
        if (!$priest || !in_array($priest->employment_status, ['Active', 'On Leave'])) return false;

        // Check if the priest has an approved leave request covering the selected date
        $onLeave = DB::table('leave_requests')
            ->where('priest_id', $priestId)
            ->where('status', 'Approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
        if ($onLeave) return false;

        // Self Booking Prevention: Exclude logged-in priest from any booking check
        if (Auth::check()) {
            $priestRecord = DB::table('priests')->where('user_id', Auth::id())->first();
            if ($priestRecord && $priestRecord->priest_id == $priestId) {
                return false;
            }
        }

        $startTime = strtotime("$date $time");
        $poojaName = strtoupper($pooja->pooja_name);

        // Determine duration
        $durationMinutes = $pooja->duration_minutes ?? 30;
        if (str_contains($poojaName, 'ARCHANA')) {
            $durationMinutes = 15;
        } elseif (str_contains($poojaName, 'ABHISHEKA')) {
            $durationMinutes = 60;
        }
        $endTime = $startTime + ($durationMinutes * 60);

        // Get existing bookings
        $existingBookings = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->where('pooja_bookings.priest_id', $priestId)
            ->where('pooja_bookings.booking_date', $date)
            ->where('pooja_bookings.booking_status', '!=', 'Cancelled')
            ->select('pooja_bookings.*', 'poojas.pooja_name', 'poojas.duration_minutes')
            ->get();

        // Special Rules: Homa and Satyanarayana (Half-Day Block)
        $isHoma = str_contains($poojaName, 'HOMA');
        $isSatyanarayana = str_contains($poojaName, 'SATYANARAYANA');

        if ($isHoma || $isSatyanarayana) {
            $reqHour = (int)date('H', $startTime);
            $reqSlot = $reqHour < 14 ? 'Morning' : 'Afternoon';

            // Strict timeslot boundaries
            if ($reqSlot === 'Morning' && ($reqHour < 6 || $reqHour >= 14)) return false;
            if ($reqSlot === 'Afternoon' && ($reqHour < 14 || $reqHour >= 20)) return false;

            $homaCount = 0;
            $satyanarayanaCount = 0;

            foreach ($existingBookings as $b) {
                $bName = strtoupper($b->pooja_name);
                $bTime = strtotime("$date $b->booking_time");
                $bHour = (int)date('H', $bTime);
                $bSlot = $bHour < 14 ? 'Morning' : 'Afternoon';

                if ($bSlot === $reqSlot) {
                    return false; // Morning/Afternoon slot already taken by another puja
                }

                if (str_contains($bName, 'HOMA')) {
                    $homaCount++;
                }
                if (str_contains($bName, 'SATYANARAYANA')) {
                    $satyanarayanaCount++;
                }
            }

            if ($isHoma && $homaCount >= 2) return false;
            if ($isSatyanarayana && $satyanarayanaCount >= 2) return false;

            return true;
        }

        // General overlap rules
        $hourStartStr = date('H:00:00', $startTime);
        $hourEndStr = date('H:59:59', $startTime);
        $archanaCount = 0;

        foreach ($existingBookings as $b) {
            $bName = strtoupper($b->pooja_name);
            $bStartTime = strtotime("$date $b->booking_time");

            // Overlap with Half-day pujas
            if (str_contains($bName, 'HOMA') || str_contains($bName, 'SATYANARAYANA')) {
                $bHour = (int)date('H', $bStartTime);
                $bSlot = $bHour < 14 ? 'Morning' : 'Afternoon';
                $reqHour = (int)date('H', $startTime);
                $reqSlot = $reqHour < 14 ? 'Morning' : 'Afternoon';

                if ($bSlot === $reqSlot) {
                    return false; // Overlaps with half-day slots
                }
            }

            // Normal Puja overlap check
            $bDuration = $b->duration_minutes ?? 30;
            if (str_contains($bName, 'ARCHANA')) $bDuration = 15;
            elseif (str_contains($bName, 'ABHISHEKA')) $bDuration = 60;

            $bEndTime = $bStartTime + ($bDuration * 60);

            if (($startTime >= $bStartTime && $startTime < $bEndTime) ||
                ($endTime > $bStartTime && $endTime <= $bEndTime) ||
                ($bStartTime >= $startTime && $bStartTime < $endTime)) {
                return false; // Collision
            }

            // Archana Hourly Limit check
            if (str_contains($poojaName, 'ARCHANA') && str_contains($bName, 'ARCHANA')) {
                if ($b->booking_time >= $hourStartStr && $b->booking_time <= $hourEndStr) {
                    $archanaCount++;
                }
            }
        }

        if (str_contains($poojaName, 'ARCHANA') && $archanaCount >= 4) {
            return false; // Max 4 Archana per hour
        }

        return true;
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
