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

class StaffController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        // Fetch staff profile details
        $staff = DB::table('staff')
            ->where('user_id', $user->id)
            ->first();

        // Dummy/Simulated Tasks for the Checklist ERP module
        $tasks = [
            ['id' => 1, 'task' => 'Clean temple sanctorum (Garbhagriha)', 'status' => 'Completed'],
            ['id' => 2, 'task' => 'Check inventory stock of flowers & oil', 'status' => 'In Progress'],
            ['id' => 3, 'task' => 'Assist priests during Abhisheka timing', 'status' => 'Pending'],
            ['id' => 4, 'task' => 'Prepare prasadam distribution counter', 'status' => 'Pending']
        ];

        // Dummy/Simulated Inventory database
        $inventory = [
            ['item' => 'Pooja Oil', 'quantity' => '120 Liters', 'status' => 'In Stock'],
            ['item' => 'Coconuts', 'quantity' => '250 Pcs', 'status' => 'In Stock'],
            ['item' => 'Camphor', 'quantity' => '5 Kgs', 'status' => 'Low Stock'],
            ['item' => 'Sandalwood Paste', 'quantity' => '0.5 Kgs', 'status' => 'Reorder']
        ];

        // Upcoming Events
        $events = DB::table('events')
            ->where('status', 'Upcoming')
            ->orderBy('event_date', 'asc')
            ->get();

        // Attendance stats
        $today = date('Y-m-d');
        $todayAttendanceRows = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->get();

        $checkInRow = $todayAttendanceRows->first(function($row) { return !is_null($row->check_in_time); });
        $checkOutRow = $todayAttendanceRows->first(function($row) { return !is_null($row->check_out_time); });

        $hasCheckedIn = $checkInRow ? true : false;
        $hasCheckedOut = $checkOutRow ? true : false;

        $attendanceStatus = 'Not Checked In';
        if ($hasCheckedIn) {
            $attendanceStatus = $hasCheckedOut ? 'Checked Out' : 'Checked In';
        }

        $checkinTime = $checkInRow ? date('h:i A', strtotime($checkInRow->check_in_time)) : 'N/A';
        $checkoutTime = $checkOutRow ? date('h:i A', strtotime($checkOutRow->check_out_time)) : 'N/A';

        // Calculate total worked minutes today
        $totalWorkedMinutes = $todayAttendanceRows->sum('session_minutes');

        // Check if there is an active session
        $activeSession = $todayAttendanceRows->whereNotNull('online_time')->whereNull('offline_time')->first();
        $isOnline = $activeSession ? true : false;
        $lastOnlineTimeMs = null;
        if ($activeSession) {
            $activeSecs = time() - strtotime($activeSession->online_time);
            $activeMinutes = round($activeSecs / 60);
            if ($activeMinutes < 0) {
                $activeMinutes = 0;
            }
            $totalWorkedMinutes += $activeMinutes;
            $lastOnlineTimeMs = strtotime($activeSession->online_time) * 1000; // milliseconds for JS
        }

        $onlineHoursToday = round($totalWorkedMinutes / 60, 2);

        $penaltyToday = 0;
        if ($hasCheckedOut) {
            $penaltyToday = DB::table('penalties')
                ->where('user_id', $user->id)
                ->where('date', $today)
                ->value('penalty_amount') ?? 0;
        }

        // Calculate monthly hours from all sessions in current month
        $firstOfMonth = date('Y-m-01');
        $lastOfMonth = date('Y-m-t');
        $monthlyMinutes = DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$firstOfMonth, $lastOfMonth])
            ->sum('session_minutes');
        
        $monthlyHours = round($monthlyMinutes / 60, 2);

        // Fetch last online and last offline times
        $lastOnlineRow = $todayAttendanceRows->whereNotNull('online_time')->sortByDesc('online_time')->first();
        $lastOfflineRow = $todayAttendanceRows->whereNotNull('offline_time')->sortByDesc('offline_time')->first();
        
        $lastOnlineTime = $lastOnlineRow ? date('h:i A', strtotime($lastOnlineRow->online_time)) : '--:--';
        $lastOfflineTime = $lastOfflineRow ? date('h:i A', strtotime($lastOfflineRow->offline_time)) : '--:--';
        $totalOnlineSessions = $todayAttendanceRows->whereNotNull('online_time')->count();
        
        $hoursPart = str_pad(floor($totalWorkedMinutes / 60), 2, '0', STR_PAD_LEFT);
        $minutesPart = str_pad($totalWorkedMinutes % 60, 2, '0', STR_PAD_LEFT);
        $workedHoursToday = "{$hoursPart} Hours {$minutesPart} Minutes";

        // Fetch wallet transactions, penalties and salary payouts
        $walletTxns = DB::table('staff_wallet_transactions')
            ->where('staff_id', $staff->staff_id)
            ->orderBy('created_at', 'desc')
            ->get();

        $penalties = DB::table('penalties')
            ->where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->get();

        $salaryPayouts = DB::table('salary_payouts')
            ->where('user_id', $user->id)
            ->orderBy('salary_month', 'desc')
            ->get();

        return view('staff.dashboard', compact(
            'user',
            'staff',
            'tasks',
            'inventory',
            'events',
            'attendanceStatus',
            'checkinTime',
            'checkoutTime',
            'onlineHoursToday',
            'penaltyToday',
            'monthlyHours',
            'walletTxns',
            'penalties',
            'salaryPayouts',
            'hasCheckedIn',
            'hasCheckedOut',
            'totalWorkedMinutes',
            'isOnline',
            'lastOnlineTimeMs',
            'lastOnlineTime',
            'lastOfflineTime',
            'totalOnlineSessions',
            'workedHoursToday'
        ));
    }

    // ============================================
    // ADMIN STAFF CRUD OPERATIONS
    // ============================================

    public function manageStaff(Request $request)
    {
        $status = $request->get('verification_status');

        $query = DB::table('staff')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->select('staff.*', 'users.name', 'users.email', 'users.mobile', 'users.email_verified_at');

        if ($status === 'Verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($status === 'Unverified') {
            $query->whereNull('users.email_verified_at');
        }

        $staffList = $query->get();

        return view('admin.manage-staff', compact('staffList', 'status'));
    }

    public function addStaffPage()
    {
        return view('admin.add-staff');
    }

    public function storeStaff(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|max:15|unique:users,mobile',
            'designation' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'employment_status' => 'required|in:Active,On Leave,Inactive',
            'joining_date' => 'required|date',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'address' => 'nullable|string',
            'account_holder_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'branch_name' => 'nullable|string|max:100',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($existingUser) {
                $existingStaff = DB::table('staff')->where('user_id', $existingUser->id)->first();
                if ($existingStaff) {
                    DB::rollBack();
                    return redirect()->back()->with('error', 'User is already registered as a Staff member.')->withInput();
                }

                DB::table('users')->where('id', $existingUser->id)->update([
                    'role' => 'Staff',
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
                    'password' => Hash::make($password),
                    'role' => 'Staff',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::table('staff')->insert([
                'user_id' => $userId,
                'gender' => $request->gender,
                'dob' => $request->dob,
                'designation' => $request->designation,
                'salary' => $request->salary,
                'employment_status' => $request->employment_status,
                'current_status' => 'Offline',
                'joining_date' => $request->joining_date,
                'address' => $request->address,
                'account_holder_name' => $request->account_holder_name,
                'account_number' => $request->account_number,
                'ifsc_code' => $request->ifsc_code,
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            AuditLogService::log("Created Staff User ID: {$userId}");
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
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Staff', $request->email, $password));
                } catch (\Exception $e) {
                    // Ignore mail errors
                }
            }

            $msg = $existingUser ? 'User promoted to Staff successfully!' : 'Staff Added Successfully!';

            if ($flashPassword) {
                return redirect()->route('admin.staff.index')
                    ->with('success', $msg)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Staff'
                    ]);
            } else {
                return redirect()->route('admin.staff.index')->with('success', $msg);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to add staff: ' . $e->getMessage())->withInput();
        }
    }

    public function updateStaff(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'designation' => 'required|string|max:100',
            'salary' => 'required|numeric|min:0',
            'employment_status' => 'required|in:Active,On Leave,Inactive',
            'joining_date' => 'required|date',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'dob' => 'nullable|date',
            'address' => 'nullable|string',
            'account_holder_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'branch_name' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $staff = DB::table('staff')->where('staff_id', $id)->first();
            if (!$staff) {
                return redirect()->back()->with('error', 'Staff member not found.');
            }

            // Verify unique email/mobile among other users
            $dupEmail = DB::table('users')->where('email', $request->email)->where('id', '!=', $staff->user_id)->first();
            if ($dupEmail) {
                return redirect()->back()->with('error', 'Email address is already in use.')->withInput();
            }

            $dupMobile = DB::table('users')->where('mobile', $request->mobile)->where('id', '!=', $staff->user_id)->first();
            if ($dupMobile) {
                return redirect()->back()->with('error', 'Mobile number is already in use.')->withInput();
            }

            DB::table('staff')->where('staff_id', $id)->update([
                'gender' => $request->gender,
                'dob' => $request->dob,
                'designation' => $request->designation,
                'salary' => $request->salary,
                'employment_status' => $request->employment_status,
                'joining_date' => $request->joining_date,
                'address' => $request->address,
                'account_holder_name' => $request->account_holder_name,
                'account_number' => $request->account_number,
                'ifsc_code' => $request->ifsc_code,
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'updated_at' => now()
            ]);

            DB::table('users')->where('id', $staff->user_id)->update([
                'name' => $request->name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'updated_at' => now()
            ]);

            AuditLogService::log("Updated Staff User ID: {$staff->user_id}");
            DB::commit();

            return redirect()->route('admin.staff.index')->with('success', 'Staff Member Updated Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update staff: ' . $e->getMessage())->withInput();
        }
    }

    public function deleteStaff($id)
    {
        DB::beginTransaction();
        try {
            $staff = DB::table('staff')->where('staff_id', $id)->first();
            if (!$staff) {
                return redirect()->back()->with('error', 'Staff member not found.');
            }

            DB::table('staff')->where('staff_id', $id)->delete();
            DB::table('users')->where('id', $staff->user_id)->delete();

            AuditLogService::log("Deleted Staff User ID: {$staff->user_id}");
            DB::commit();

            return redirect()->route('admin.staff.index')->with('success', 'Staff Member Deleted Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to delete staff: ' . $e->getMessage());
        }
    }

    public function toggleOnlineStatus(Request $request)
    {
        $user = Auth::user();
        $staff = DB::table('staff')->where('user_id', $user->id)->first();
        if (!$staff) {
            return response()->json(['success' => false, 'message' => 'Staff record not found.'], 404);
        }

        $today = date('Y-m-d');

        // Check if user has an attendance record for today and has not checked out yet
        $checkInRec = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->whereNotNull('check_in_time')
            ->first();

        if (!$checkInRec) {
            return response()->json(['success' => false, 'message' => 'Please clock in (Mark Present) first before toggling status.'], 422);
        }

        if ($checkInRec->check_out_time) {
            return response()->json(['success' => false, 'message' => 'You have already ended work for today. Cannot go online again.'], 422);
        }

        // Determine if they are currently online
        $activeSession = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->whereNotNull('online_time')
            ->whereNull('offline_time')
            ->first();

        $isOnline = $activeSession ? true : false;
        $newStatus = $isOnline ? 'Offline' : 'Online';

        DB::beginTransaction();
        try {
            if ($newStatus === 'Online') {
                // Going Online -> Start new session row
                DB::table('attendances')->insert([
                    'user_id' => $user->id,
                    'date' => $today,
                    'check_in_time' => $checkInRec->check_in_time,
                    'online_time' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Going Offline -> End active session row
                $sessionMins = round((time() - strtotime($activeSession->online_time)) / 60);
                if ($sessionMins < 0) {
                    $sessionMins = 0;
                }
                DB::table('attendances')
                    ->where('attendance_id', $activeSession->attendance_id)
                    ->update([
                        'offline_time' => now(),
                        'session_minutes' => $sessionMins,
                        'updated_at' => now()
                    ]);

                // Calculate updated worked minutes today
                $totalMinutes = DB::table('attendances')
                    ->where('user_id', $user->id)
                    ->where('date', $today)
                    ->sum('session_minutes');

                // Update worked_minutes on all today's rows
                DB::table('attendances')
                    ->where('user_id', $user->id)
                    ->where('date', $today)
                    ->update(['worked_minutes' => $totalMinutes]);
            }

            // Update current_status of staff
            DB::table('staff')->where('staff_id', $staff->staff_id)->update([
                'current_status' => $newStatus,
                'updated_at' => now()
            ]);

            DB::commit();
            return response()->json(['success' => true, 'status' => $newStatus]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function markPresent(Request $request)
    {
        $user = Auth::user();
        $staff = DB::table('staff')->where('user_id', $user->id)->first();
        if (!$staff) {
            return redirect()->back()->with('error', 'Staff record not found.');
        }

        $today = date('Y-m-d');
        $existing = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', 'Attendance already marked for today.');
        }

        DB::beginTransaction();
        try {
            // Create first check-in record (starts as offline)
            DB::table('attendances')->insert([
                'user_id' => $user->id,
                'date' => $today,
                'check_in_time' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('staff')->where('staff_id', $staff->staff_id)->update([
                'current_status' => 'Offline',
                'updated_at' => now()
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Checked in successfully! Status set to Offline.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to mark present: ' . $e->getMessage());
        }
    }

    public function endWork(Request $request)
    {
        $user = Auth::user();
        $staff = DB::table('staff')->where('user_id', $user->id)->first();
        if (!$staff) {
            return redirect()->back()->with('error', 'Staff record not found.');
        }

        $today = date('Y-m-d');
        $checkInRec = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->whereNotNull('check_in_time')
            ->first();

        if (!$checkInRec) {
            return redirect()->back()->with('error', 'You must check-in/mark present first before ending work.');
        }

        // Check if already checked out
        if ($checkInRec->check_out_time) {
            return redirect()->back()->with('error', 'You have already ended work for today.');
        }

        DB::beginTransaction();
        try {
            // Find active session
            $activeSession = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('date', $today)
                ->whereNotNull('online_time')
                ->whereNull('offline_time')
                ->first();

            $now = now();

            if ($activeSession) {
                // Automatically end active session
                $sessionMins = round((time() - strtotime($activeSession->online_time)) / 60);
                if ($sessionMins < 0) {
                    $sessionMins = 0;
                }
                DB::table('attendances')
                    ->where('attendance_id', $activeSession->attendance_id)
                    ->update([
                        'offline_time' => $now,
                        'session_minutes' => $sessionMins,
                        'updated_at' => $now
                    ]);
            }

            // Calculate total worked minutes today
            $totalMinutes = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('date', $today)
                ->sum('session_minutes');

            // Set check-out time on all today's rows
            DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('date', $today)
                ->update([
                    'check_out_time' => $now,
                    'worked_minutes' => $totalMinutes,
                    'updated_at' => $now
                ]);

            // Set current_status of staff to Offline
            DB::table('staff')->where('staff_id', $staff->staff_id)->update([
                'current_status' => 'Offline',
                'updated_at' => now()
            ]);

            $workedHours = round($totalMinutes / 60, 2);
            $msg = 'Work ended successfully for today. Total hours worked: ' . $workedHours . ' hrs.';

            if ($workedHours < 10) {
                $missingHours = 10 - $workedHours;
                $hourlyPenalty = DB::table('settings')->where('key', 'hourly_penalty_amount')->value('value') ?? 100.00;
                $penaltyAmount = round($missingHours * $hourlyPenalty, 2);

                DB::table('penalties')->insert([
                    'user_id' => $user->id,
                    'date' => $today,
                    'missing_hours' => $missingHours,
                    'penalty_amount' => $penaltyAmount,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                DB::table('staff')->where('staff_id', $staff->staff_id)->decrement('wallet_balance', $penaltyAmount);

                DB::table('staff_wallet_transactions')->insert([
                    'staff_id' => $staff->staff_id,
                    'amount' => $penaltyAmount,
                    'transaction_type' => 'Debit',
                    'remarks' => "Penalty for missing shift hours (worked {$workedHours}h of 10h. Missing {$missingHours}h)",
                    'created_at' => now()
                ]);

                $msg .= ' Penalty of ₹' . $penaltyAmount . ' applied for short shift.';
            }

            DB::commit();
            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to end work: ' . $e->getMessage());
        }
    }

    /**
     * Counter: Process offline walk-in pooja booking.
     */
    public function counterBookPooja(Request $request)
    {
        $request->validate([
            'pooja_id' => 'required|exists:poojas,pooja_id',
            'devotee_name' => 'required|string|max:255',
            'mobile' => 'required|digits:10',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required'
        ]);

        $poojaId = $request->pooja_id;
        $devoteeName = $request->devotee_name;
        $mobile = $request->mobile;
        $date = $request->booking_date;
        $time = $request->booking_time;

        $pooja = DB::table('poojas')->where('pooja_id', $poojaId)->first();
        if (!$pooja || $pooja->status !== 'Active') {
            return response()->json(['success' => false, 'message' => 'Selected pooja is not active or not found.'], 422);
        }

        DB::beginTransaction();
        try {
            // Find or dynamically create user/devotee records to prevent dashboard query join failures
            $user = \App\Models\User::where('mobile', $mobile)->first();
            if (!$user) {
                $user = \App\Models\User::create([
                    'name' => $devoteeName,
                    'email' => 'offline_' . $mobile . '_' . uniqid() . '@templeconnect.com',
                    'mobile' => $mobile,
                    'password' => Hash::make('offline123'),
                    'role' => 'Devotee',
                    'status' => 'Active'
                ]);
                $user->email_verified_at = now();
                $user->save();
            }

            $devotee = \App\Models\Devotee::where('user_id', $user->id)->first();
            if (!$devotee) {
                $devotee = \App\Models\Devotee::create([
                    'user_id' => $user->id,
                    'address' => 'Offline Counter walk-in',
                    'gothra' => 'Not Specified',
                    'nakshatra' => 'Not Specified',
                    'gender' => 'Male',
                    'dob' => '2000-01-01',
                    'verified' => 1
                ]);
            }

            // Auto assign workloads
            $bookingController = new \App\Http\Controllers\BookingController();
            $assignedPriestId = $bookingController->autoAssignPriest($poojaId, $date, $time);

            if (!$assignedPriestId) {
                return response()->json([
                    'success' => false,
                    'message' => "No active/available priests are available for {$pooja->pooja_name} on {$date} at {$time}. All priest slots are full."
                ], 422);
            }

            $amount = $pooja->pooja_fee;
            $totalAmount = $amount; // No discounts apply for offline counter bookings

            // Create Pooja Booking
            $bookingId = DB::table('pooja_bookings')->insertGetId([
                'devotee_id' => $devotee->devotee_id,
                'pooja_id' => $poojaId,
                'priest_id' => $assignedPriestId,
                'booking_date' => $date,
                'booking_time' => $time,
                'booking_type' => 'Offline',
                'amount' => $amount,
                'discount_amount' => 0.00,
                'shipping_charge' => 0.00,
                'total_amount' => $totalAmount,
                'payment_method' => 'Cash',
                'payment_status' => 'Paid',
                'booking_status' => 'Confirmed',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Status Log
            DB::table('booking_status_logs')->insert([
                'booking_id' => $bookingId,
                'status_from' => null,
                'status_to' => 'Confirmed',
                'changed_by' => Auth::id(),
                'remarks' => 'Offline booking recorded at staff counter.',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Payment Record
            DB::table('booking_payments')->insert([
                'booking_id' => $bookingId,
                'payment_method' => 'Cash',
                'transaction_id' => 'OFFLINE-' . strtoupper(uniqid()),
                'amount' => $totalAmount,
                'status' => 'Paid',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Fetch priest name for printing
            $priestName = DB::table('users')
                ->where('id', DB::table('priests')->where('priest_id', $assignedPriestId)->value('user_id'))
                ->value('name');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Offline Pooja Booking registered successfully.',
                'booking_details' => [
                    'booking_id' => $bookingId,
                    'pooja_name' => $pooja->pooja_name,
                    'devotee_name' => $devoteeName,
                    'mobile' => $mobile,
                    'date' => $date,
                    'time' => $time,
                    'priest_name' => $priestName,
                    'amount' => number_format($totalAmount, 2)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process counter booking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Counter: Record offline walk-in donation.
     */
    public function counterRecordDonation(Request $request)
    {
        $request->validate([
            'donor_name' => 'required|string|max:255',
            'mobile' => 'required|digits:10',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100'
        ]);

        try {
            $donationId = DB::table('donations_without_logins')->insertGetId([
                'donor_name' => $request->donor_name,
                'email' => null,
                'mobile' => $request->mobile,
                'amount' => $request->amount,
                'purpose' => $request->purpose,
                'purpose_details' => 'Recorded at Staff Counter',
                'payment_method' => 'Cash',
                'transaction_id' => 'OFFLINE-DON-' . strtoupper(uniqid()),
                'donation_date' => date('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Offline donation recorded successfully.',
                'donation_details' => [
                    'donation_id' => $donationId,
                    'donor_name' => $request->donor_name,
                    'mobile' => $request->mobile,
                    'amount' => number_format($request->amount, 2),
                    'purpose' => $request->purpose,
                    'date' => date('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record counter donation: ' . $e->getMessage()
            ], 500);
        }
    }
}
