<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;
use App\Models\Setting;


class DevoteeController extends Controller
{

public function dashboard()
{
    $user = Auth::user();


    $devotee = DB::table('devotees')
        ->where('user_id',$user->id)
        ->first();


    if(!$devotee)
{
    return view('devotee.dashboard',[
        'user' => $user,
        'devotee' => null,
        'membership' => null,
        'poojaCount' => 0,
        'upcomingPoojas' => 0,
        'totalDonation' => 0,
        'recentDonations' => collect(),
        'recentBookings' => collect(),
        'events' => DB::table('events')
                        ->where('status','Upcoming')
                        ->limit(3)
                        ->get()
    ]);
}


    $this->initializeDevoteeMembershipDates($devotee);

    $membership = DB::table('memberships')
        ->where('membership_id',$devotee->membership_id)
        ->first();



    $poojaCount = DB::table('pooja_bookings')
        ->where('devotee_id',$devotee->devotee_id)
        ->count();



    $upcomingPoojas = DB::table('pooja_bookings')
        ->where('devotee_id',$devotee->devotee_id)
        ->where('booking_date','>=',date('Y-m-d'))
        ->count();



    $totalDonation = DB::table('donations')
        ->where('devotee_id',$devotee->devotee_id)
        ->sum('amount');



    $recentDonations = DB::table('donations')
        ->where('devotee_id',$devotee->devotee_id)
        ->orderBy('donation_date','desc')
        ->limit(3)
        ->get();



    $recentBookings = DB::table('pooja_bookings')
        ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
        ->leftJoin('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
        ->leftJoin('users as priest_user', 'priests.user_id', '=', 'priest_user.id')
        ->where('pooja_bookings.devotee_id', $devotee->devotee_id)
        ->select(
            'pooja_bookings.booking_id',
            'poojas.pooja_name',
            'pooja_bookings.booking_date',
            'pooja_bookings.booking_time',
            'pooja_bookings.booking_type',
            'pooja_bookings.payment_status',
            'pooja_bookings.booking_status',
            'priest_user.name as priest_name'
        )
        ->orderBy('pooja_bookings.booking_date', 'desc')
        ->orderBy('pooja_bookings.booking_time', 'desc')
        ->get();



    $events = DB::table('events')
        ->where('status','Upcoming')
        ->limit(3)
        ->get();



    $daysRemaining = null;
    if ($devotee && $devotee->membership_id && $devotee->membership_end_date) {
        $endDate = strtotime($devotee->membership_end_date);
        $diff = $endDate - strtotime(date('Y-m-d'));
        $daysRemaining = max(0, intval(round($diff / (60 * 60 * 24))));
    }

    return view(
        'devotee.dashboard',
        compact(
            'user',
            'devotee',
            'membership',
            'poojaCount',
            'upcomingPoojas',
            'totalDonation',
            'recentDonations',
            'recentBookings',
            'events',
            'daysRemaining'
        )
    );
}

    // ============================================
    // ADMIN DEVOTEE CRUD OPERATIONS
    // ============================================

    public function manageDevotees(Request $request)
    {
        $status = $request->get('verification_status');

        $query = DB::table('devotees')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->leftJoin('memberships', 'devotees.membership_id', '=', 'memberships.membership_id')
            ->select(
                'devotees.*',
                'users.name',
                'users.email',
                'users.mobile',
                'users.email_verified_at',
                'memberships.membership_name'
            );

        if ($status === 'Verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($status === 'Unverified') {
            $query->whereNull('users.email_verified_at');
        }

        $devotees = $query->get();

        $memberships = DB::table('memberships')->where('status', 'Active')->get();

        return view('admin.manage-devotees', compact('devotees', 'memberships', 'status'));
    }

    public function addDevoteePage()
    {
        $memberships = DB::table('memberships')->where('status', 'Active')->get();
        return view('admin.add-devotee', compact('memberships'));
    }

    public function storeDevotee(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|max:15|unique:users,mobile',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'gothra' => 'nullable|string|max:100',
            'nakshatra' => 'nullable|string|max:100',
            'address' => 'nullable|string',
            'membership_id' => 'nullable|exists:memberships,membership_id',
            'verified' => 'required|boolean',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();

        try {
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($existingUser) {
                $existingDevotee = DB::table('devotees')
                    ->where('user_id', $existingUser->id)
                    ->first();

                if ($existingDevotee) {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'This user is already registered as a Devotee.')
                        ->withInput();
                }

                DB::table('users')
                    ->where('id', $existingUser->id)
                    ->update([
                        'role' => 'Devotee',
                        'status' => 'Active',
                        'email_verified_at' => now(),
                        'updated_at' => now()
                    ]);

                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'role' => 'Devotee',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Membership dates calculation
            $membershipStartDate = null;
            $membershipEndDate = null;
            if ($request->membership_id) {
                $membership = DB::table('memberships')->where('membership_id', $request->membership_id)->first();
                if ($membership) {
                    $membershipStartDate = date('Y-m-d');
                    $months = $membership->duration_months ?? 1;
                    $membershipEndDate = date('Y-m-d', strtotime("+$months months"));
                }
            }

            DB::table('devotees')->insert([
                'user_id' => $userId,
                'gothra' => $request->gothra,
                'nakshatra' => $request->nakshatra,
                'gender' => $request->gender,
                'dob' => $request->dob,
                'address' => $request->address,
                'membership_id' => $request->membership_id,
                'membership_start_date' => $membershipStartDate,
                'membership_end_date' => $membershipEndDate,
                'verified' => $request->verified,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            // Handling System Mode
            $systemMode = Setting::get('system_mode', 'Testing Mode');
            $emailHandling = Setting::get('testing_email_handling', 'Do Not Send Emails');

            $sendEmail = false;
            $flashPassword = false;

            if ($systemMode === 'Testing Mode') {
                $flashPassword = true;
                if ($emailHandling === 'Send Emails') {
                    $sendEmail = true;
                }
            } else {
                $sendEmail = true;
            }

            if ($sendEmail) {
                try {
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Devotee', $request->email, $password));
                } catch (\Exception $e) {
                    // Ignore mail errors
                }
            }

            $message = $existingUser ? 'User promoted to Devotee successfully!' : 'Devotee Added Successfully!';

            if ($flashPassword) {
                return redirect()->route('admin.devotees.index')
                    ->with('success', $message)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Devotee'
                    ]);
            } else {
                return redirect()->route('admin.devotees.index')->with('success', $message);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to add devotee: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function updateDevotee(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'gothra' => 'nullable|string|max:100',
            'nakshatra' => 'nullable|string|max:100',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'address' => 'nullable|string',
            'membership_id' => 'nullable|exists:memberships,membership_id',
            'verified' => 'required|boolean',
        ]);

        DB::beginTransaction();

        try {
            $devotee = DB::table('devotees')->where('devotee_id', $id)->first();
            if (!$devotee) {
                return redirect()->back()->with('error', 'Devotee not found.');
            }

            // Verify unique email/mobile among other users
            $dupEmail = DB::table('users')->where('email', $request->email)->where('id', '!=', $devotee->user_id)->first();
            if ($dupEmail) {
                return redirect()->back()->with('error', 'Email address is already in use.')->withInput();
            }

            $dupMobile = DB::table('users')->where('mobile', $request->mobile)->where('id', '!=', $devotee->user_id)->first();
            if ($dupMobile) {
                return redirect()->back()->with('error', 'Mobile number is already in use.')->withInput();
            }

            $membershipStartDate = $devotee->membership_start_date;
            $membershipEndDate = $devotee->membership_end_date;

            if ($request->membership_id != $devotee->membership_id) {
                if ($request->membership_id) {
                    $membership = DB::table('memberships')->where('membership_id', $request->membership_id)->first();
                    if ($membership) {
                        $membershipStartDate = date('Y-m-d');
                        $months = $membership->duration_months ?? 1;
                        $membershipEndDate = date('Y-m-d', strtotime("+$months months"));
                    }
                } else {
                    $membershipStartDate = null;
                    $membershipEndDate = null;
                }
            }

            DB::table('devotees')
                ->where('devotee_id', $id)
                ->update([
                    'gothra' => $request->gothra,
                    'nakshatra' => $request->nakshatra,
                    'gender' => $request->gender,
                    'dob' => $request->dob,
                    'address' => $request->address,
                    'membership_id' => $request->membership_id,
                    'membership_start_date' => $membershipStartDate,
                    'membership_end_date' => $membershipEndDate,
                    'verified' => $request->verified,
                    'updated_at' => now()
                ]);

            DB::table('users')
                ->where('id', $devotee->user_id)
                ->update([
                    'name' => $request->name,
                    'mobile' => $request->mobile,
                    'email' => $request->email,
                    'updated_at' => now()
                ]);

            DB::commit();

            return redirect()->route('admin.devotees.index')
                ->with('success', 'Devotee Updated Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update devotee: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function deleteDevotee($id)
    {
        DB::beginTransaction();

        try {
            $devotee = DB::table('devotees')
                ->where('devotee_id', $id)
                ->first();

            if (!$devotee) {
                return redirect()->back()->with('error', 'Devotee not found.');
            }

            DB::table('devotees')
                ->where('devotee_id', $id)
                ->delete();

            DB::table('users')
                ->where('id', $devotee->user_id)
                ->delete();

            DB::commit();

            return redirect()->route('admin.devotees.index')
                ->with('success', 'Devotee Deleted Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to delete devotee: ' . $e->getMessage());
        }
    }

    private function initializeDevoteeMembershipDates($devotee)
    {
        if ($devotee && $devotee->membership_id && !$devotee->membership_end_date) {
            $membership = DB::table('memberships')
                ->where('membership_id', $devotee->membership_id)
                ->first();
            $months = $membership ? ($membership->duration_months ?? 1) : 1;
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+$months months"));
            DB::table('devotees')
                ->where('devotee_id', $devotee->devotee_id)
                ->update([
                    'membership_start_date' => $startDate,
                    'membership_end_date' => $endDate,
                    'updated_at' => now()
                ]);
            $devotee->membership_start_date = $startDate;
            $devotee->membership_end_date = $endDate;
        }
    }

    private function getMembershipLevel($membershipId)
    {
        if ($membershipId == 1) return 1; // Silver
        if ($membershipId == 2) return 2; // Gold
        if ($membershipId == 3) return 3; // Platinum
        return 0;
    }

    public function showPaymentPage(Request $request)
    {
        $user = Auth::user();
        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        if (!$devotee) {
            return redirect()->route('devotee.dashboard')->with('error', 'Devotee profile not found.');
        }

        $this->initializeDevoteeMembershipDates($devotee);

        $type = $request->get('type');
        $amount = 0;
        $title = '';
        $remarks = '';

        if ($type === 'pooja') {
            $bookingIds = explode(',', $request->get('booking_ids', ''));
            $bookings = DB::table('pooja_bookings')
                ->whereIn('booking_id', $bookingIds)
                ->where('devotee_id', $devotee->devotee_id)
                ->get();

            if ($bookings->isEmpty()) {
                return redirect()->route('devotee.dashboard')->with('error', 'No valid pending bookings found.');
            }

            $amount = $bookings->sum('total_amount');
            $title = 'Pooja Booking Payment';
            $remarks = 'Pooja Booking #' . implode(', #', $bookingIds);
        } elseif ($type === 'donation') {
            $amount = floatval($request->get('amount', 0));
            $purpose = $request->get('purpose', 'General Temple Fund');
            if ($amount <= 0) {
                return redirect()->route('devotee.dashboard')->with('error', 'Invalid donation amount.');
            }
            $title = 'Temple Donation';
            $remarks = 'Donation for ' . $purpose;
        } elseif ($type === 'membership') {
            $membershipId = $request->get('membership_id');
            $membershipPlan = DB::table('memberships')->where('membership_id', $membershipId)->first();
            if (!$membershipPlan) {
                return redirect()->route('devotee.dashboard')->with('error', 'Invalid membership plan.');
            }

            // Check active membership compatibility
            $daysRemaining = null;
            if ($devotee->membership_id && $devotee->membership_end_date) {
                $endDate = strtotime($devotee->membership_end_date);
                $diff = $endDate - strtotime(date('Y-m-d'));
                $daysRemaining = max(0, intval(round($diff / (60 * 60 * 24))));
            }

            $activeLevel = 0;
            if ($devotee->membership_id && $daysRemaining > 0) {
                $activeLevel = $this->getMembershipLevel($devotee->membership_id);
            }

            $newLevel = $this->getMembershipLevel($membershipId);

            if ($activeLevel > 0 && $newLevel <= $activeLevel) {
                return redirect()->route('devotee.dashboard')->with('error', 'You cannot purchase a plan lower than or equal to your current active tier until it expires.');
            }

            $amount = $membershipPlan->membership_fee;
            $title = $membershipPlan->membership_name . ' Membership Subscription';
            $remarks = $membershipPlan->membership_name . ' subscription';
        } else {
            return redirect()->route('devotee.dashboard')->with('error', 'Invalid payment type.');
        }

        // Generate dynamic UPI payment URL
        $upiString = "upi://pay?pa=rohandevadigapithrodi-1@oksbi&pn=" . urlencode("Shree Mandir") . "&am=" . number_format($amount, 2, '.', '') . "&cu=INR&tn=" . urlencode($remarks);
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($upiString);

        return view('devotee.payment', compact('user', 'devotee', 'type', 'amount', 'title', 'remarks', 'qrCodeUrl', 'request'));
    }

    public function processPayment(Request $request)
    {
        $user = Auth::user();
        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        if (!$devotee) {
            return redirect()->route('devotee.dashboard')->with('error', 'Devotee profile not found.');
        }

        $this->initializeDevoteeMembershipDates($devotee);

        $type = $request->input('type');
        
        DB::beginTransaction();
        try {
            if ($type === 'pooja') {
                $bookingIds = explode(',', $request->input('booking_ids', ''));
                $bookings = DB::table('pooja_bookings')
                    ->whereIn('booking_id', $bookingIds)
                    ->where('devotee_id', $devotee->devotee_id)
                    ->get();

                if ($bookings->isEmpty()) {
                    return redirect()->route('devotee.dashboard')->with('error', 'No bookings found.');
                }

                // Update booking status and payment status
                DB::table('pooja_bookings')
                    ->whereIn('booking_id', $bookingIds)
                    ->update([
                        'payment_status' => 'Paid',
                        'booking_status' => 'Confirmed',
                        'updated_at' => now()
                    ]);

                // Update booking payments status
                DB::table('booking_payments')
                    ->whereIn('booking_id', $bookingIds)
                    ->update([
                        'status' => 'Paid',
                        'updated_at' => now()
                    ]);

                // Write to status logs for each booking
                foreach ($bookingIds as $bId) {
                    $b = $bookings->firstWhere('booking_id', intval($bId));
                    DB::table('booking_status_logs')->insert([
                        'booking_id' => $bId,
                        'status_from' => $b ? $b->booking_status : 'Pending',
                        'status_to' => 'Confirmed',
                        'changed_by' => $user->id,
                        'remarks' => 'Payment completed via UPI QR code. Status auto-confirmed.',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                DB::commit();
                return redirect()->route('devotee.dashboard')
                    ->with('success', 'Payment successful! Your bookings have been paid and confirmed.');
            } elseif ($type === 'donation') {
                $amount = floatval($request->input('amount'));
                $purpose = $request->input('purpose');
                
                DB::table('donations')->insert([
                    'devotee_id' => $devotee->devotee_id,
                    'amount' => $amount,
                    'payment_mode' => 'UPI',
                    'transaction_id' => 'TXN' . strtoupper(uniqid()),
                    'remarks' => 'Donation for ' . $purpose . ' (UPI QR)',
                    'donation_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                DB::commit();
                return redirect()->route('devotee.dashboard')
                    ->with('success', 'Thank you! Your donation of ₹' . number_format($amount, 2) . ' has been received successfully.');
            } elseif ($type === 'membership') {
                $membershipId = $request->input('membership_id');
                $membershipPlan = DB::table('memberships')->where('membership_id', $membershipId)->first();
                if (!$membershipPlan) {
                    return redirect()->route('devotee.dashboard')->with('error', 'Invalid membership plan.');
                }

                // Check active membership
                $daysRemaining = null;
                if ($devotee->membership_id && $devotee->membership_end_date) {
                    $endDate = strtotime($devotee->membership_end_date);
                    $diff = $endDate - strtotime(date('Y-m-d'));
                    $daysRemaining = max(0, intval(round($diff / (60 * 60 * 24))));
                }

                $activeLevel = 0;
                if ($devotee->membership_id && $daysRemaining > 0) {
                    $activeLevel = $this->getMembershipLevel($devotee->membership_id);
                }

                $newLevel = $this->getMembershipLevel($membershipId);

                // If user has an active membership and tries to downgrade or buy same tier
                if ($activeLevel > 0 && $newLevel <= $activeLevel) {
                    return redirect()->route('devotee.dashboard')->with('error', 'You cannot purchase a plan lower than or equal to your current active tier until it expires.');
                }

                // Calculate dates
                $months = $membershipPlan->duration_months ?? 1;
                $startDate = date('Y-m-d');
                
                // If it is an upgrade (lower to higher level)
                if ($activeLevel > 0 && $newLevel > $activeLevel) {
                    $endDate = date('Y-m-d', strtotime("+$months months +5 days"));
                } else {
                    $endDate = date('Y-m-d', strtotime("+$months months"));
                }

                DB::table('devotees')
                    ->where('devotee_id', $devotee->devotee_id)
                    ->update([
                        'membership_id' => $membershipId,
                        'membership_start_date' => $startDate,
                        'membership_end_date' => $endDate,
                        'updated_at' => now()
                    ]);

                DB::commit();

                $bonusMsg = ($activeLevel > 0 && $newLevel > $activeLevel) ? " with a bonus of 5 extra days!" : ".";
                return redirect()->route('devotee.dashboard')
                    ->with('success', 'Subscription successful! You are now a ' . $membershipPlan->membership_name . ' member' . $bonusMsg);
            } else {
                return redirect()->route('devotee.dashboard')->with('error', 'Invalid payment type.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('devotee.dashboard')->with('error', 'Payment failed: ' . $e->getMessage());
        }
    }
}