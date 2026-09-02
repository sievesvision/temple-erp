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

class CommitteeController extends Controller
{
    /**
     * Committee's own landing page — scoped to Donations, Pooja Bookings and Events,
     * which are the only areas they're allowed to manage.
     */
    public function dashboard()
    {
        $donationsTotal = DB::table('donations')->where('payment_status', 'Paid')->sum('amount') + DB::table('donations_without_logins')->where('payment_status', 'Paid')->sum('amount');
        $bookingsCount = DB::table('pooja_bookings')->count();
        $upcomingEventsCount = DB::table('events')->where('event_date', '>=', now()->toDateString())->count();

        return view('committee.dashboard', compact('donationsTotal', 'bookingsCount', 'upcomingEventsCount'));
    }

    // ============================================
    // ADMIN COMMITTEE MEMBER CRUD OPERATIONS
    // ============================================

    public function manageCommittee()
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'committee', 'view')) {
            abort(403, 'Unauthorized access.');
        }
        $canAdd = RolePermission::can($user->role, 'committee', 'add');
        $canEdit = RolePermission::can($user->role, 'committee', 'edit');
        $canDelete = RolePermission::can($user->role, 'committee', 'delete');

        $committeeList = DB::table('users')
            ->leftJoin('committees', 'committees.user_id', '=', 'users.id')
            ->where('users.role', 'Committee')
            ->select('users.*', 'committees.position')
            ->orderBy('users.name')
            ->get();

        return view('admin.manage-committee', compact('committeeList', 'canAdd', 'canEdit', 'canDelete'));
    }

    public function addCommitteePage()
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'committee', 'add')) {
            abort(403, 'Unauthorized access.');
        }
        return view('admin.add-committee');
    }

    public function storeCommittee(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'committee', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|max:15|unique:users,mobile',
            'position' => 'required|string|max:100',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make($password),
                'role' => 'Committee',
                'status' => 'Active',
                'must_change_password' => 1,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('committees')->insert([
                'user_id' => $userId,
                'position' => $request->position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLogService::log("Created Committee User: {$request->email}");
            DB::commit();

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
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Committee', $request->email, $password));
                } catch (\Exception $e) {
                    // Log or handle mail error silently
                }
            }

            if ($flashPassword) {
                return redirect()->route('admin.committee.index')
                    ->with('success', 'Committee Member Added Successfully!')
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Committee',
                    ]);
            }

            return redirect()->route('admin.committee.index')->with('success', 'Committee Member Added Successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to add committee member: ' . $e->getMessage())->withInput();
        }
    }

    public function updateCommittee(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'committee', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'status' => 'required|in:Active,Inactive',
            'position' => 'required|string|max:100',
        ]);

        $member = DB::table('users')->where('id', $id)->where('role', 'Committee')->first();
        if (!$member) {
            return redirect()->back()->with('error', 'Committee member not found.');
        }

        $dupEmail = DB::table('users')->where('email', $request->email)->where('id', '!=', $id)->first();
        if ($dupEmail) {
            return redirect()->back()->with('error', 'Email address is already in use.')->withInput();
        }

        $dupMobile = DB::table('users')->where('mobile', $request->mobile)->where('id', '!=', $id)->first();
        if ($dupMobile) {
            return redirect()->back()->with('error', 'Mobile number is already in use.')->withInput();
        }

        DB::table('users')->where('id', $id)->update([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'status' => $request->status,
            'updated_at' => now(),
        ]);

        if (DB::table('committees')->where('user_id', $id)->exists()) {
            DB::table('committees')->where('user_id', $id)->update([
                'position' => $request->position,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('committees')->insert([
                'user_id' => $id,
                'position' => $request->position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        AuditLogService::log("Updated Committee User ID: {$id}");

        return redirect()->route('admin.committee.index')->with('success', 'Committee Member Updated Successfully!');
    }

    public function deleteCommittee($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'committee', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $member = DB::table('users')->where('id', $id)->where('role', 'Committee')->first();
        if (!$member) {
            return redirect()->back()->with('error', 'Committee member not found.');
        }

        DB::table('users')->where('id', $id)->delete();

        AuditLogService::log("Deleted Committee User ID: {$id}");

        return redirect()->route('admin.committee.index')->with('success', 'Committee Member Deleted Successfully');
    }
}
