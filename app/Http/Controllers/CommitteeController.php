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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'committee', 'view')) {
            abort(403, 'Unauthorized access.');
        }
        $canAdd = RolePermission::can(session('active_role', $user->role), 'committee', 'add');
        $canEdit = RolePermission::can(session('active_role', $user->role), 'committee', 'edit');
        $canDelete = RolePermission::can(session('active_role', $user->role), 'committee', 'delete');

        // Inner join, not filtered by users.role — Committee membership is a grant (a
        // committees row), so this lists everyone who holds it, whether or not it's
        // currently their primary role.
        $committeeList = DB::table('users')
            ->join('committees', 'committees.user_id', '=', 'users.id')
            ->select('users.*', 'committees.position')
            ->orderBy('users.name')
            ->get();

        return view('admin.manage-committee', compact('committeeList', 'canAdd', 'canEdit', 'canDelete'));
    }

    public function addCommitteePage()
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'committee', 'add')) {
            abort(403, 'Unauthorized access.');
        }
        return view('admin.add-committee');
    }

    public function storeCommittee(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'committee', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            // Deliberately no unique:users rule on email/mobile — a duplicate is the signal
            // to grant an existing user this role too (see the $existingUser branch below),
            // not an error. Uniqueness among *Committee members specifically* is checked
            // there instead. (Mirrors Priest/Trustee/Staff/Accountant's Add pages.)
            'email' => 'required|email',
            'mobile' => 'required|string|max:15',
            'position' => 'required|string|max:100',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($existingUser) {
                $existingCommittee = DB::table('committees')->where('user_id', $existingUser->id)->first();
                if ($existingCommittee) {
                    DB::rollBack();
                    return redirect()->back()->with('error', 'User is already registered as a Committee member.')->withInput();
                }

                // Grant Committee access without touching their existing role/status/identity
                // — they keep whichever role brought them here and simply gain another.
                $userId = $existingUser->id;
            } else {
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
            }

            DB::table('committees')->insert([
                'user_id' => $userId,
                'position' => $request->position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLogService::log("Created Committee User: {$request->email}");
            DB::commit();

            // An existing user's real password is never touched (see the $existingUser
            // branch above) — $password here was only ever generated for a brand-new
            // account, so it must never be emailed or flashed for a granted-role user; that
            // would show a "password" that was never actually saved as theirs.
            if ($existingUser) {
                return redirect()->route('admin.committee.index')
                    ->with('success', "{$request->name} has been granted Committee access — they can log in choosing the Committee role with their existing password.");
            }

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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'committee', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'status' => 'required|in:Active,Inactive',
            'position' => 'required|string|max:100',
        ]);

        // Committee membership is a grant (a committees row), decoupled from users.role.
        $member = DB::table('users')
            ->join('committees', 'committees.user_id', '=', 'users.id')
            ->where('users.id', $id)
            ->select('users.*')
            ->first();
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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'committee', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // Committee membership is a grant (a committees row), decoupled from users.role —
        // this user may hold Committee as a secondary role while primarily something else.
        $isCommitteeMember = DB::table('committees')->where('user_id', $id)->exists();
        if (!$isCommitteeMember) {
            return redirect()->back()->with('error', 'Committee member not found.');
        }

        // Revokes the Committee grant — deletes the whole account only if this was their
        // only role, otherwise just removes the committees row and reassigns their primary
        // role if Committee was it (see RoleGrantService::revoke()).
        \App\Services\RoleGrantService::revoke($id, 'Committee');

        AuditLogService::log("Deleted Committee User ID: {$id}");

        return redirect()->route('admin.committee.index')->with('success', 'Committee Member Deleted Successfully');
    }
}
