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

class CommitteeController extends Controller
{
    /**
     * Committee's own landing page — scoped to Donations, Pooja Bookings and Events,
     * which are the only areas they're allowed to manage.
     */
    public function dashboard()
    {
        $donationsTotal = DB::table('donations')->sum('amount') + DB::table('donations_without_logins')->where('payment_status', 'Paid')->sum('amount');
        $bookingsCount = DB::table('pooja_bookings')->count();
        $upcomingEventsCount = DB::table('events')->where('event_date', '>=', now()->toDateString())->count();

        return view('committee.dashboard', compact('donationsTotal', 'bookingsCount', 'upcomingEventsCount'));
    }

    // ============================================
    // ADMIN COMMITTEE MEMBER CRUD OPERATIONS
    // ============================================

    public function manageCommittee()
    {
        $committeeList = DB::table('users')->where('role', 'Committee')->orderBy('name')->get();

        return view('admin.manage-committee', compact('committeeList'));
    }

    public function addCommitteePage()
    {
        return view('admin.add-committee');
    }

    public function storeCommittee(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|max:15|unique:users,mobile',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            DB::table('users')->insert([
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

            AuditLogService::log("Created Committee User: {$request->email}");

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
            return redirect()->back()->with('error', 'Failed to add committee member: ' . $e->getMessage())->withInput();
        }
    }

    public function updateCommittee(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
            'status' => 'required|in:Active,Inactive',
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

        AuditLogService::log("Updated Committee User ID: {$id}");

        return redirect()->route('admin.committee.index')->with('success', 'Committee Member Updated Successfully!');
    }

    public function deleteCommittee($id)
    {
        $member = DB::table('users')->where('id', $id)->where('role', 'Committee')->first();
        if (!$member) {
            return redirect()->back()->with('error', 'Committee member not found.');
        }

        DB::table('users')->where('id', $id)->delete();

        AuditLogService::log("Deleted Committee User ID: {$id}");

        return redirect()->route('admin.committee.index')->with('success', 'Committee Member Deleted Successfully');
    }
}
