<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\AuditLogService;
use App\Mail\WelcomeMail;
use App\Models\Setting;
use App\Models\RolePermission;

class TrusteeController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $trustee = DB::table('trustees')->where('user_id', $user->id)->first();

        // Total numbers
        $totalDevotees = DB::table('devotees')->count();
        $totalPriests = DB::table('priests')->count();
        $totalBookings = DB::table('pooja_bookings')->count();
        $totalRevenue = DB::table('pooja_bookings')->where('payment_status', 'Paid')->sum('total_amount')
            + DB::table('donations')->where('payment_status', 'Paid')->sum('amount')
            + DB::table('donations_without_logins')->where('payment_status', 'Paid')->sum('amount');

        $today = date('Y-m-d');
        
        // Today's Poojas
        $todayPoojas = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->leftJoin('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users as devotee_users', 'devotees.user_id', '=', 'devotee_users.id')
            ->leftJoin('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->leftJoin('users as priest_users', 'priests.user_id', '=', 'priest_users.id')
            ->whereDate('pooja_bookings.booking_date', $today)
            ->select(
                'pooja_bookings.*',
                'poojas.pooja_name',
                'devotee_users.name as devotee_name',
                'devotee_users.email as devotee_email',
                'devotee_users.mobile as devotee_mobile',
                'priest_users.name as priest_name'
            )
            ->orderBy('pooja_bookings.booking_time', 'asc')
            ->get();

        // Upcoming Poojas
        $upcomingPoojas = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->leftJoin('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users as devotee_users', 'devotees.user_id', '=', 'devotee_users.id')
            ->leftJoin('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->leftJoin('users as priest_users', 'priests.user_id', '=', 'priest_users.id')
            ->whereDate('pooja_bookings.booking_date', '>', $today)
            ->select(
                'pooja_bookings.*',
                'poojas.pooja_name',
                'devotee_users.name as devotee_name',
                'devotee_users.email as devotee_email',
                'devotee_users.mobile as devotee_mobile',
                'priest_users.name as priest_name'
            )
            ->orderBy('pooja_bookings.booking_date', 'asc')
            ->orderBy('pooja_bookings.booking_time', 'asc')
            ->get();

        // All bookings for Calendar view
        $allBookings = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->leftJoin('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users as devotee_users', 'devotees.user_id', '=', 'devotee_users.id')
            ->leftJoin('priests', 'pooja_bookings.priest_id', '=', 'priests.priest_id')
            ->leftJoin('users as priest_users', 'priests.user_id', '=', 'priest_users.id')
            ->select(
                'pooja_bookings.*',
                'poojas.pooja_name',
                'devotee_users.name as devotee_name',
                'devotee_users.email as devotee_email',
                'devotee_users.mobile as devotee_mobile',
                'priest_users.name as priest_name'
            )
            ->get();

        // Upcoming Events
        $upcomingEvents = DB::table('events')
            ->where('status', 'Upcoming')
            ->orderBy('event_date', 'asc')
            ->get();

        // Audit Logs
        $auditLogs = DB::table('booking_status_logs')
            ->join('pooja_bookings', 'booking_status_logs.booking_id', '=', 'pooja_bookings.booking_id')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->select('booking_status_logs.*', 'poojas.pooja_name')
            ->orderBy('booking_status_logs.created_at', 'desc')
            ->limit(10)
            ->get();

        // Recent Donations
        $loggedDonations = DB::table('donations')
            ->leftJoin('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users', 'devotees.user_id', '=', 'users.id')
            ->select('users.name as donor_name', 'donations.amount', 'donations.donation_date', 'donations.created_at')
            ->orderBy('donations.donation_date', 'desc')
            ->orderBy('donations.created_at', 'desc')
            ->limit(5)
            ->get();

        $guestDonations = DB::table('donations_without_logins')
            ->select('donor_name', 'amount', 'donation_date', 'created_at')
            ->orderBy('donation_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentDonations = $loggedDonations->concat($guestDonations)
            ->sort(function ($a, $b) {
                $cmp = strcmp($b->donation_date, $a->donation_date);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcmp($b->created_at, $a->created_at);
            })
            ->take(5)
            ->values();

        // Recent Bookings
        $recentBookings = DB::table('pooja_bookings')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('trustee.dashboard', compact(
            'user',
            'trustee',
            'totalDevotees',
            'totalPriests',
            'totalBookings',
            'totalRevenue',
            'todayPoojas',
            'upcomingPoojas',
            'allBookings',
            'upcomingEvents',
            'auditLogs',
            'recentDonations',
            'recentBookings'
        ));
    }

    // ============================================
    // ADMIN TRUSTEE CRUD OPERATIONS
    // ============================================

    public function manageTrustees(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'trustees', 'view')) {
            abort(403, 'Unauthorized access.');
        }
        $canAdd = RolePermission::can(session('active_role', $user->role), 'trustees', 'add');
        $canEdit = RolePermission::can(session('active_role', $user->role), 'trustees', 'edit');
        $canDelete = RolePermission::can(session('active_role', $user->role), 'trustees', 'delete');

        $status = $request->get('verification_status');

        $query = DB::table('trustees')
            ->join('users', 'trustees.user_id', '=', 'users.id')
            ->select('trustees.*', 'users.name', 'users.email', 'users.mobile', 'users.email_verified_at');

        if ($status === 'Verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($status === 'Unverified') {
            $query->whereNull('users.email_verified_at');
        }

        $trustees = $query->get();

        return view('admin.manage-trustees', compact('trustees', 'status', 'canAdd', 'canEdit', 'canDelete'));
    }

    public function addTrusteePage()
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'trustees', 'add')) {
            abort(403, 'Unauthorized access.');
        }
        return view('admin.add-trustee');
    }

    public function storeTrustee(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'trustees', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            // Deliberately no unique:users rule on email/mobile — a duplicate is the signal
            // to grant an existing user this role too (see the $existingUser branch below),
            // not an error. Uniqueness among *Trustees specifically* is checked there instead.
            'email' => 'required|email',
            'mobile' => 'required|string|max:15',
            'designation' => 'required|string|max:100',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'address' => 'nullable|string',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($existingUser) {
                $existingTrustee = DB::table('trustees')->where('user_id', $existingUser->id)->first();
                if ($existingTrustee) {
                    DB::rollBack();
                    return redirect()->back()->with('error', 'User is already registered as a Trustee.')->withInput();
                }

                // Grant Trustee access without touching their existing role/status/identity —
                // they keep whichever role brought them here and simply gain another.
                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'password' => Hash::make($password),
                    'role' => 'Trustee',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::table('trustees')->insert([
                'user_id' => $userId,
                'gender' => $request->gender,
                'dob' => $request->dob,
                'designation' => $request->designation,
                'address' => $request->address,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            AuditLogService::log("Created Trustee User ID: {$userId}");
            DB::commit();

            // An existing user's real password is never touched (see the $existingUser
            // branch above) — $password here was only ever generated for a brand-new
            // account, so it must never be emailed or flashed for a granted-role user; that
            // would show a "password" that was never actually saved as theirs.
            if ($existingUser) {
                return redirect()->route('admin.trustees.index')
                    ->with('success', "{$request->name} has been granted Trustee access — they can log in choosing the Trustee role with their existing password.");
            }

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
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Trustee', $request->email, $password));
                } catch (\Exception $e) {
                    // Ignore mail errors
                }
            }

            $msg = 'Trustee Added Successfully!';

            if ($flashPassword) {
                return redirect()->route('admin.trustees.index')
                    ->with('success', $msg)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Trustee'
                    ]);
            } else {
                return redirect()->route('admin.trustees.index')->with('success', $msg);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to add trustee: ' . $e->getMessage())->withInput();
        }
    }

    public function updateTrustee(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'trustees', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'designation' => 'required|string|max:100',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'address' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $trustee = DB::table('trustees')->where('trustee_id', $id)->first();
            if (!$trustee) {
                return redirect()->back()->with('error', 'Trustee not found.');
            }

            // Verify unique email/mobile among other users
            $dupEmail = DB::table('users')->where('email', $request->email)->where('id', '!=', $trustee->user_id)->first();
            if ($dupEmail) {
                return redirect()->back()->with('error', 'Email address is already in use.')->withInput();
            }

            $dupMobile = DB::table('users')->where('mobile', $request->mobile)->where('id', '!=', $trustee->user_id)->first();
            if ($dupMobile) {
                return redirect()->back()->with('error', 'Mobile number is already in use.')->withInput();
            }

            DB::table('trustees')->where('trustee_id', $id)->update([
                'gender' => $request->gender,
                'dob' => $request->dob,
                'designation' => $request->designation,
                'address' => $request->address,
                'updated_at' => now()
            ]);

            DB::table('users')->where('id', $trustee->user_id)->update([
                'name' => $request->name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'updated_at' => now()
            ]);

            AuditLogService::log("Updated Trustee User ID: {$trustee->user_id}");
            DB::commit();

            return redirect()->route('admin.trustees.index')->with('success', 'Trustee Updated Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update trustee: ' . $e->getMessage())->withInput();
        }
    }

    public function deleteTrustee($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'trustees', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            $trustee = DB::table('trustees')->where('trustee_id', $id)->first();
            if (!$trustee) {
                return redirect()->back()->with('error', 'Trustee not found.');
            }

            // Revokes the Trustee grant — deletes the whole account only if this was their
            // only role, otherwise just removes the trustees row and reassigns their
            // primary role if Trustee was it (see RoleGrantService::revoke()).
            \App\Services\RoleGrantService::revoke($trustee->user_id, 'Trustee');

            AuditLogService::log("Deleted Trustee User ID: {$trustee->user_id}");

            return redirect()->route('admin.trustees.index')->with('success', 'Trustee Deleted Successfully');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete trustee: ' . $e->getMessage());
        }
    }

    // ============================================
    // ADMIN LEAVE REQUESTS MANAGEMENT
    // ============================================
    public function manageLeaves()
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'leaves', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $leaves = DB::table('leave_requests')
            ->join('priests', 'leave_requests.priest_id', '=', 'priests.priest_id')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->select('leave_requests.*', 'users.name as priest_name')
            ->orderBy('leave_requests.created_at', 'desc')
            ->get();

        return view('admin.manage-leaves', compact('leaves'));
    }
}
