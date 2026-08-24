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

class AccountantController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        // Income, Expenses, Payouts totals
        $totalDonations = DB::table('donations')->sum('amount') + DB::table('donations_without_logins')->sum('amount');
        $totalBookingsRevenue = DB::table('pooja_bookings')->where('payment_status', 'Paid')->sum('total_amount');
        $totalIncome = $totalDonations + $totalBookingsRevenue;
        
        $totalExpenses = DB::table('salary_payouts')->where('payment_status', 'Paid')->sum('total_paid');

        // Recent Transactions (Donations, Payouts)
        $donations = DB::table('donations')
            ->leftJoin('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users', 'devotees.user_id', '=', 'users.id')
            ->select('users.name as source', 'donations.amount', 'donations.donation_date as txn_date', DB::raw("'Donation' as type"))
            ->orderBy('donations.donation_date', 'desc')
            ->limit(10)
            ->get();

        $payouts = DB::table('salary_payouts')
            ->join('users', 'salary_payouts.user_id', '=', 'users.id')
            ->select('users.name as source', 'salary_payouts.total_paid as amount', 'salary_payouts.payment_date as txn_date', DB::raw("'Salary Payout' as type"))
            ->orderBy('salary_payouts.payment_date', 'desc')
            ->limit(10)
            ->get();

        // Invoices (Paid Bookings)
        $invoices = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->join('devotees', 'pooja_bookings.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->select('pooja_bookings.*', 'poojas.pooja_name', 'users.name as devotee_name')
            ->orderBy('pooja_bookings.created_at', 'desc')
            ->get();

        // All Payouts
        $salaryPayouts = DB::table('salary_payouts')
            ->join('users', 'salary_payouts.user_id', '=', 'users.id')
            ->select('salary_payouts.*', 'users.name', 'users.role')
            ->orderBy('salary_payouts.payment_date', 'desc')
            ->get();

        $accountant = DB::table('accountants')->where('user_id', $user->id)->first();

        // Accountant's own payout list
        $myPayouts = DB::table('salary_payouts')
            ->where('user_id', $user->id)
            ->orderBy('salary_month', 'desc')
            ->get();

        return view('accountant.dashboard', compact(
            'user',
            'totalIncome',
            'totalExpenses',
            'totalDonations',
            'donations',
            'payouts',
            'invoices',
            'salaryPayouts',
            'accountant',
            'myPayouts'
        ));
    }

    // ============================================
    // ADMIN ACCOUNTANT CRUD OPERATIONS
    // ============================================

    public function manageAccountants(Request $request)
    {
        $status = $request->get('verification_status');

        $query = DB::table('accountants')
            ->join('users', 'accountants.user_id', '=', 'users.id')
            ->select('accountants.*', 'users.name', 'users.email', 'users.mobile', 'users.email_verified_at');

        if ($status === 'Verified') {
            $query->whereNotNull('users.email_verified_at');
        } elseif ($status === 'Unverified') {
            $query->whereNull('users.email_verified_at');
        }

        $accountantList = $query->get();

        return view('admin.manage-accountants', compact('accountantList', 'status'));
    }

    public function addAccountantPage()
    {
        return view('admin.add-accountant');
    }

    public function storeAccountant(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|max:15|unique:users,mobile',
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
                $existingAccountant = DB::table('accountants')->where('user_id', $existingUser->id)->first();
                if ($existingAccountant) {
                    DB::rollBack();
                    return redirect()->back()->with('error', 'User is already registered as an Accountant.')->withInput();
                }

                DB::table('users')->where('id', $existingUser->id)->update([
                    'role' => 'Accountant',
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
                    'role' => 'Accountant',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::table('accountants')->insert([
                'user_id' => $userId,
                'gender' => $request->gender,
                'dob' => $request->dob,
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

            AuditLogService::log("Created Accountant User ID: {$userId}");
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
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Accountant', $request->email, $password));
                } catch (\Exception $e) {
                    // Log or handle mail error silently
                }
            }

            $msg = $existingUser ? 'User promoted to Accountant successfully!' : 'Accountant Added Successfully!';

            if ($flashPassword) {
                return redirect()->route('admin.accountants.index')
                    ->with('success', $msg)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Accountant'
                    ]);
            } else {
                return redirect()->route('admin.accountants.index')->with('success', $msg);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to add accountant: ' . $e->getMessage())->withInput();
        }
    }

    public function updateAccountant(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15',
            'email' => 'required|email',
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
            $accountant = DB::table('accountants')->where('accountant_id', $id)->first();
            if (!$accountant) {
                return redirect()->back()->with('error', 'Accountant not found.');
            }

            // Verify unique email/mobile among other users
            $dupEmail = DB::table('users')->where('email', $request->email)->where('id', '!=', $accountant->user_id)->first();
            if ($dupEmail) {
                return redirect()->back()->with('error', 'Email address is already in use.')->withInput();
            }

            $dupMobile = DB::table('users')->where('mobile', $request->mobile)->where('id', '!=', $accountant->user_id)->first();
            if ($dupMobile) {
                return redirect()->back()->with('error', 'Mobile number is already in use.')->withInput();
            }

            DB::table('accountants')->where('accountant_id', $id)->update([
                'gender' => $request->gender,
                'dob' => $request->dob,
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

            DB::table('users')->where('id', $accountant->user_id)->update([
                'name' => $request->name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'updated_at' => now()
            ]);

            AuditLogService::log("Updated Accountant User ID: {$accountant->user_id}");
            DB::commit();

            return redirect()->route('admin.accountants.index')->with('success', 'Accountant Updated Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update accountant: ' . $e->getMessage())->withInput();
        }
    }

    public function deleteAccountant($id)
    {
        DB::beginTransaction();
        try {
            $accountant = DB::table('accountants')->where('accountant_id', $id)->first();
            if (!$accountant) {
                return redirect()->back()->with('error', 'Accountant not found.');
            }

            DB::table('accountants')->where('accountant_id', $id)->delete();
            DB::table('users')->where('id', $accountant->user_id)->delete();

            AuditLogService::log("Deleted Accountant User ID: {$accountant->user_id}");
            DB::commit();

            return redirect()->route('admin.accountants.index')->with('success', 'Accountant Deleted Successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to delete accountant: ' . $e->getMessage());
        }
    }
}
