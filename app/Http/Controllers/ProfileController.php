<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLogService;

class ProfileController extends Controller
{
    /**
     * Handle user profile update for any logged in role.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Session expired. Please login.');
        }

        // Validate basic fields
        $rules = [
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|max:15|unique:users,mobile,' . $user->id,
        ];

        // Add validations based on role
        if ($user->role === 'Devotee') {
            $rules['gender'] = 'nullable|string|in:Male,Female,Other';
            $rules['dob'] = 'nullable|date';
            $rules['gothra'] = 'nullable|string|max:100';
            $rules['nakshatra'] = 'nullable|string|max:100';
            $rules['address'] = 'nullable|string';
        } elseif ($user->role === 'Priest') {
            $rules['gender'] = 'nullable|string|in:Male,Female,Other';
            $rules['dob'] = 'nullable|date';
            $rules['address'] = 'nullable|string';
            $rules['specialization'] = 'nullable|string|max:255';
            $rules['emergency_contact'] = 'nullable|string|max:20';
            $rules['account_holder_name'] = 'nullable|string|max:100';
            $rules['account_number'] = 'nullable|string|max:50';
            $rules['bank_name'] = 'nullable|string|max:100';
            $rules['ifsc_code'] = 'nullable|string|max:20';
            $rules['branch_name'] = 'nullable|string|max:100';
        } elseif ($user->role === 'Trustee') {
            $rules['gender'] = 'nullable|string|in:Male,Female,Other';
            $rules['dob'] = 'nullable|date';
            $rules['address'] = 'nullable|string';
        } elseif ($user->role === 'Staff') {
            $rules['gender'] = 'nullable|string|in:Male,Female,Other';
            $rules['dob'] = 'nullable|date';
            $rules['address'] = 'nullable|string';
            $rules['account_holder_name'] = 'nullable|string|max:100';
            $rules['account_number'] = 'nullable|string|max:50';
            $rules['bank_name'] = 'nullable|string|max:100';
            $rules['ifsc_code'] = 'nullable|string|max:20';
            $rules['branch_name'] = 'nullable|string|max:100';
        } elseif ($user->role === 'Accountant') {
            $rules['gender'] = 'nullable|string|in:Male,Female,Other';
            $rules['dob'] = 'nullable|date';
            $rules['address'] = 'nullable|string';
            $rules['account_holder_name'] = 'nullable|string|max:100';
            $rules['account_number'] = 'nullable|string|max:50';
            $rules['bank_name'] = 'nullable|string|max:100';
            $rules['ifsc_code'] = 'nullable|string|max:20';
            $rules['branch_name'] = 'nullable|string|max:100';
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            // 1. Update Core User Details
            DB::table('users')->where('id', $user->id)->update([
                'name' => $request->name,
                'mobile' => $request->mobile,
                'updated_at' => now()
            ]);

            // 2. Update Role Specific Details
            if ($user->role === 'Devotee') {
                DB::table('devotees')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'gender' => $request->gender,
                        'dob' => $request->dob,
                        'gothra' => $request->gothra,
                        'nakshatra' => $request->nakshatra,
                        'address' => $request->address,
                        'updated_at' => now()
                    ]
                );
            } elseif ($user->role === 'Priest') {
                DB::table('priests')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'gender' => $request->gender,
                        'dob' => $request->dob,
                        'address' => $request->address,
                        'specialization' => $request->specialization,
                        'emergency_contact' => $request->emergency_contact,
                        'account_holder_name' => $request->account_holder_name,
                        'account_number' => $request->account_number,
                        'bank_name' => $request->bank_name,
                        'ifsc_code' => $request->ifsc_code,
                        'branch_name' => $request->branch_name,
                        'updated_at' => now()
                    ]
                );
            } elseif ($user->role === 'Trustee') {
                DB::table('trustees')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'gender' => $request->gender,
                        'dob' => $request->dob,
                        'address' => $request->address,
                        'updated_at' => now()
                    ]
                );
            } elseif ($user->role === 'Staff') {
                DB::table('staff')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'gender' => $request->gender,
                        'dob' => $request->dob,
                        'address' => $request->address,
                        'account_holder_name' => $request->account_holder_name,
                        'account_number' => $request->account_number,
                        'bank_name' => $request->bank_name,
                        'ifsc_code' => $request->ifsc_code,
                        'branch_name' => $request->branch_name,
                        'updated_at' => now()
                    ]
                );
            } elseif ($user->role === 'Accountant') {
                DB::table('accountants')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'gender' => $request->gender,
                        'dob' => $request->dob,
                        'address' => $request->address,
                        'account_holder_name' => $request->account_holder_name,
                        'account_number' => $request->account_number,
                        'bank_name' => $request->bank_name,
                        'ifsc_code' => $request->ifsc_code,
                        'branch_name' => $request->branch_name,
                        'updated_at' => now()
                    ]
                );
            }

            AuditLogService::log("Updated profile details.");
            DB::commit();

            return redirect()->back()->with('success', 'Profile details updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update profile: ' . $e->getMessage())->withInput();
        }
    }
}
