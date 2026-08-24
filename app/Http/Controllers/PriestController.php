<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;
use App\Models\Setting;

class PriestController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $priest = DB::table('priests')
            ->where('user_id', $user->id)
            ->first();

        if (!$priest) {
            return abort(403, 'Unauthorized. No associated priest record found.');
        }

        // Today's bookings
        $todayBookings = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->join('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->where('pooja_bookings.priest_id', $priest->priest_id)
            ->where('pooja_bookings.booking_date', date('Y-m-d'))
            ->where('pooja_bookings.booking_status', '!=', 'Cancelled')
            ->select('pooja_bookings.*', 'poojas.pooja_name', 'users.name as devotee_name', 'users.mobile as devotee_mobile')
            ->orderBy('pooja_bookings.booking_time', 'asc')
            ->get();

        // Upcoming bookings
        $upcomingBookings = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->join('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->where('pooja_bookings.priest_id', $priest->priest_id)
            ->where('pooja_bookings.booking_date', '>', date('Y-m-d'))
            ->where('pooja_bookings.booking_status', '!=', 'Cancelled')
            ->select('pooja_bookings.*', 'poojas.pooja_name', 'users.name as devotee_name', 'users.mobile as devotee_mobile')
            ->orderBy('pooja_bookings.booking_date', 'asc')
            ->orderBy('pooja_bookings.booking_time', 'asc')
            ->limit(10)
            ->get();

        // Completed bookings
        $completedBookings = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->where('pooja_bookings.priest_id', $priest->priest_id)
            ->where('pooja_bookings.booking_status', 'Completed')
            ->select('pooja_bookings.*', 'poojas.pooja_name')
            ->get();

        // Workload counts
        $todayCount = $todayBookings->count();
        $monthlyCount = DB::table('pooja_bookings')
            ->where('priest_id', $priest->priest_id)
            ->whereYear('booking_date', date('Y'))
            ->whereMonth('booking_date', date('m'))
            ->where('booking_status', '!=', 'Cancelled')
            ->count();

        // Total completed earnings
        $totalEarnings = $completedBookings->sum('amount');

        // -----------------------------------------------------
        // Calculate Today's Online Hours and Penalty based on Attendance
        // -----------------------------------------------------
        $today = date('Y-m-d');
        $todayAttendanceRows = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('date', $today)
            ->get();

        $checkInRow = $todayAttendanceRows->first(function($row) { return !is_null($row->check_in_time); });
        $checkOutRow = $todayAttendanceRows->first(function($row) { return !is_null($row->check_out_time); });

        $hasCheckedIn = $checkInRow ? true : false;
        $hasCheckedOut = $checkOutRow ? true : false;

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

        // Calculate monthly hours from all sessions in the current month
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
        $walletTxns = DB::table('priest_wallet_transactions')
            ->where('priest_id', $priest->priest_id)
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

        // Fetch leave requests for this priest
        $leaveRequests = DB::table('leave_requests')
            ->where('priest_id', $priest->priest_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('priest.dashboard', compact(
            'user',
            'priest',
            'todayBookings',
            'upcomingBookings',
            'completedBookings',
            'todayCount',
            'monthlyCount',
            'totalEarnings',
            'onlineHoursToday',
            'penaltyToday',
            'leaveRequests',
            'monthlyHours',
            'walletTxns',
            'penalties',
            'salaryPayouts',
            'hasCheckedIn',
            'hasCheckedOut',
            'checkinTime',
            'checkoutTime',
            'totalWorkedMinutes',
            'isOnline',
            'lastOnlineTimeMs',
            'lastOnlineTime',
            'lastOfflineTime',
            'totalOnlineSessions',
            'workedHoursToday'
        ));
    }

    public function managePriests(Request $request)
    {
        $status = $request->get('verification_status');

        $query = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->select(
                'priests.*',
                'users.name',
                'users.email',
                'users.mobile',
                'users.email_verified_at'
            );

        if ($status === 'Verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($status === 'Unverified') {
            $query->whereNull('users.email_verified_at');
        }

        $priests = $query->get();

        return view('admin.manage-priests', compact('priests', 'status'));
    }

    public function addPriestPage()
    {
        return view('admin.add-priest');
    }

    public function viewPriest($id)
    {
        $priest = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->where('priests.priest_id', $id) // Fixed: Added table name
            ->select(
                'priests.*',
                'users.name',
                'users.email',
                'users.mobile'
            )
            ->first();

        return view('admin.view-priest', compact('priest'));
    }

    public function editPriest($id)
    {
        $priest = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->where('priests.priest_id', $id) // Fixed: Added table name
            ->select(
                'priests.*',
                'users.name',
                'users.email',
                'users.mobile'
            )
            ->first();

        return view('admin.edit-priest', compact('priest'));
    }

    public function updatePriest(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'specialization' => 'nullable|string',
            'salary' => 'required|numeric|min:0',
            'employment_status' => 'required|string',
            'current_status' => 'required|string',
            'joining_date' => 'required|date',
            'address' => 'nullable|string',
            'account_holder_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'ifsc_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string',
            'branch_name' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $priest = DB::table('priests')->where('priest_id', $id)->first();
            if (!$priest) {
                return redirect()->back()->with('error', 'Priest not found.');
            }

            // Verify unique email/mobile among other users
            $dupEmail = DB::table('users')->where('email', $request->email)->where('id', '!=', $priest->user_id)->first();
            if ($dupEmail) {
                return redirect()->back()->with('error', 'Email address is already in use.')->withInput();
            }

            $dupMobile = DB::table('users')->where('mobile', $request->mobile)->where('id', '!=', $priest->user_id)->first();
            if ($dupMobile) {
                return redirect()->back()->with('error', 'Mobile number is already in use.')->withInput();
            }

            DB::table('priests')
                ->where('priest_id', $id)
                ->update([
                    'specialization' => $request->specialization,
                    'monthly_salary' => $request->salary,
                    'employment_status' => $request->employment_status,
                    'current_status' => $request->current_status,
                    'joining_date' => $request->joining_date,
                    'address' => $request->address,
                    'account_holder_name' => $request->account_holder_name,
                    'account_number' => $request->account_number,
                    'ifsc_code' => $request->ifsc_code,
                    'bank_name' => $request->bank_name,
                    'branch_name' => $request->branch_name,
                    'updated_at' => now()
                ]);

            DB::table('users')
                ->where('id', $priest->user_id)
                ->update([
                    'name' => $request->name,
                    'mobile' => $request->mobile,
                    'email' => $request->email,
                    'updated_at' => now()
                ]);

            DB::commit();

            return redirect()->route('admin.priests.index')
                ->with('success', 'Priest Updated Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to update priest: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function deletePriest($id)
    {
        DB::beginTransaction();

        try {
            $priest = DB::table('priests')
                ->where('priest_id', $id)
                ->first();

            if (!$priest) {
                return redirect()->back()->with('error', 'Priest not found.');
            }

            // Delete priest
            DB::table('priests')
                ->where('priest_id', $id)
                ->delete();

            // Delete user
            DB::table('users')
                ->where('id', $priest->user_id)
                ->delete();

            DB::commit();

            // ========== FIXED: Use named route ==========
            return redirect()->route('admin.priests.index')
                ->with('success', 'Priest Deleted Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $msg = $e->getMessage();
            if (str_contains($msg, 'foreign key constraint fails') || str_contains($msg, 'Integrity constraint violation')) {
                $msg = 'This priest has historical pooja bookings, leave requests, or chats and cannot be deleted. Please suspend or set their employment status to "Inactive" instead.';
            } else {
                $msg = 'Failed to delete priest: ' . $msg;
            }
            return redirect()->back()
                ->with('error', $msg);
        }
    }    public function storePriest(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|max:15|unique:users,mobile',
            'gender' => 'nullable|string',
            'dob' => 'nullable|date',
            'experience_years' => 'nullable|integer|min:0|max:50',
            'qualification' => 'nullable|string',
            'emergency_contact' => 'nullable|string|max:15',
            'specialization' => 'required|string',
            'employment_status' => 'nullable|string',
            'current_status' => 'nullable|string',
            'joining_date' => 'required|date',
            'address' => 'nullable|string',
            'monthly_salary' => 'required|numeric|min:0',
            'account_holder_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'ifsc_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string',
            'branch_name' => 'nullable|string',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();

        try {
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($existingUser) {
                $existingPriest = DB::table('priests')
                    ->where('user_id', $existingUser->id)
                    ->first();

                if ($existingPriest) {
                    DB::rollBack();
                    return redirect()->back()
                        ->with('error', 'This user is already registered as a Priest.')
                        ->withInput();
                }

                DB::table('users')
                    ->where('id', $existingUser->id)
                    ->update([
                        'role' => 'Priest',
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
                    'role' => 'Priest',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $maxPriestId = DB::table('priests')->max('priest_id');
            $nextNum = 1;
            if ($maxPriestId) {
                $num = (int) filter_var($maxPriestId, FILTER_SANITIZE_NUMBER_INT);
                $nextNum = $num + 1;
            }
            $priestId = 'PRIEST' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            DB::table('priests')->insert([
                'user_id' => $userId,
                'priest_id' => $priestId,
                'gender' => $request->gender,
                'dob' => $request->dob,
                'experience_years' => $request->experience_years,
                'qualification' => $request->qualification,
                'emergency_contact' => $request->emergency_contact,
                'specialization' => $request->specialization,
                'employment_status' => $request->employment_status ?? 'Active',
                'current_status' => $request->current_status ?? 'Offline',
                'joining_date' => $request->joining_date,
                'address' => $request->address,
                'monthly_salary' => $request->monthly_salary,
                'account_holder_name' => $request->account_holder_name,
                'account_number' => $request->account_number,
                'ifsc_code' => $request->ifsc_code,
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
                'wallet_balance' => 0,
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
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Priest', $request->email, $password));
                } catch (\Exception $e) {
                    // Ignore mail errors
                }
            }

            $message = $existingUser ? 'User promoted to Priest successfully!' : 'Priest Added Successfully!';

            if ($flashPassword) {
                return redirect()->route('admin.priests.index')
                    ->with('success', $message)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Priest'
                    ]);
            } else {
                return redirect()->route('admin.priests.index')->with('success', $message);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to add priest: ' . $e->getMessage())
                ->withInput();
        }
    }  

    // ============================================
    // PRIEST PORTAL - ONLINE STATUS TOGGLE & ATTENDANCE
    // ============================================
    public function toggleOnlineStatus(Request $request)
    {
        $user = Auth::user();
        $priest = DB::table('priests')->where('user_id', $user->id)->first();
        if (!$priest) {
            return response()->json(['success' => false, 'message' => 'Priest not found.'], 404);
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

            // Update current_status of priest
            DB::table('priests')->where('priest_id', $priest->priest_id)->update([
                'current_status' => $newStatus,
                'updated_at' => now()
            ]);

            DB::table('priest_status_logs')->insert([
                'priest_id' => $priest->priest_id,
                'status' => $newStatus,
                'created_at' => now(),
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
        $priest = DB::table('priests')->where('user_id', $user->id)->first();
        if (!$priest) {
            return redirect()->back()->with('error', 'Priest not found.');
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

            DB::table('priests')->where('priest_id', $priest->priest_id)->update([
                'current_status' => 'Offline',
                'updated_at' => now()
            ]);

            DB::table('priest_status_logs')->insert([
                'priest_id' => $priest->priest_id,
                'status' => 'Offline',
                'created_at' => now(),
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
        $priest = DB::table('priests')->where('user_id', $user->id)->first();
        if (!$priest) {
            return redirect()->back()->with('error', 'Priest not found.');
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

            // Set current_status of priest to Offline
            DB::table('priests')->where('priest_id', $priest->priest_id)->update([
                'current_status' => 'Offline',
                'updated_at' => now()
            ]);

            DB::table('priest_status_logs')->insert([
                'priest_id' => $priest->priest_id,
                'status' => 'Offline',
                'created_at' => now(),
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

                DB::table('priests')->where('priest_id', $priest->priest_id)->decrement('wallet_balance', $penaltyAmount);

                DB::table('priest_wallet_transactions')->insert([
                    'priest_id' => $priest->priest_id,
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

    public function completePooja(Request $request, $id)
    {
        $user = Auth::user();
        $priest = DB::table('priests')->where('user_id', $user->id)->first();
        if (!$priest) {
            return redirect()->back()->with('error', 'Priest not found.');
        }

        $booking = DB::table('pooja_bookings')
            ->where('booking_id', $id)
            ->where('priest_id', $priest->priest_id)
            ->first();

        if (!$booking) {
            return redirect()->back()->with('error', 'Booking not found or not assigned to you.');
        }

        if ($booking->booking_status === 'Completed') {
            return redirect()->back()->with('error', 'Pooja is already marked as completed.');
        }

        // Double check to prevent duplicate credits in transaction log
        $alreadyCredited = DB::table('priest_wallet_transactions')
            ->where('booking_id', $id)
            ->where('transaction_type', 'Credit')
            ->exists();
        if ($alreadyCredited) {
            return redirect()->back()->with('error', 'Wallet credit has already been processed for this Pooja booking.');
        }

        $rewardAmount = round(0.25 * $booking->total_amount, 2);

        DB::beginTransaction();
        try {
            DB::table('pooja_bookings')
                ->where('booking_id', $id)
                ->update([
                    'booking_status' => 'Completed',
                    'updated_at' => now()
                ]);

            DB::table('priests')
                ->where('priest_id', $priest->priest_id)
                ->increment('wallet_balance', $rewardAmount);

            DB::table('priest_wallet_transactions')->insert([
                'priest_id' => $priest->priest_id,
                'booking_id' => $id,
                'amount' => $rewardAmount,
                'transaction_type' => 'Credit',
                'remarks' => "25% commission reward for completing Pooja booking #BK" . str_pad($id, 5, '0', STR_PAD_LEFT),
                'created_at' => now()
            ]);

            DB::table('booking_status_logs')->insert([
                'booking_id' => $id,
                'status_from' => $booking->booking_status,
                'status_to' => 'Completed',
                'changed_by' => $user->id,
                'remarks' => 'Marked as completed by Priest',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            return redirect()->back()->with('success', "Pooja marked as completed! ₹{$rewardAmount} credited to wallet.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to complete pooja: ' . $e->getMessage());
        }
    }

    public function requestLeave(Request $request)
    {
        $user = Auth::user();
        $priest = DB::table('priests')->where('user_id', $user->id)->first();
        if (!$priest) {
            return redirect()->back()->with('error', 'Priest not found.');
        }

        $minDate = date('Y-m-d', strtotime('+15 days'));
        $request->validate([
            'start_date' => 'required|date|after_or_equal:' . $minDate,
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500'
        ], [
            'start_date.after_or_equal' => 'Leave request must be submitted at least 15 days in advance (on or after ' . date('d M Y', strtotime($minDate)) . ').',
        ]);

        DB::table('leave_requests')->insert([
            'priest_id' => $priest->priest_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return redirect()->back()->with('success', 'Leave request submitted successfully for approval.');
    }

    public function updateLeaveStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Approved,Rejected'
        ]);

        $leave = DB::table('leave_requests')->where('id', $id)->first();
        if (!$leave) {
            return redirect()->back()->with('error', 'Leave request not found.');
        }

        DB::beginTransaction();
        try {
            DB::table('leave_requests')->where('id', $id)->update([
                'status' => $request->status,
                'updated_at' => now()
            ]);

            if ($request->status === 'Approved') {
                $priest = DB::table('priests')->where('priest_id', $leave->priest_id)->first();
                if ($priest) {
                    DB::table('priests')->where('priest_id', $priest->priest_id)->update([
                        'employment_status' => 'On Leave'
                    ]);
                }
            }

            DB::commit();
            return redirect()->back()->with('success', 'Leave request status updated to ' . $request->status);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }
}