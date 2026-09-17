<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

/**
 * A single cross-role view of every account in the system (Admin/Committee/Devotee/Priest/
 * Trustee/Staff/Accountant), their login/password history, and an admin-triggered password
 * reset — distinct from the per-role management pages (DevoteeController etc.), which each
 * only show their own role plus role-specific fields (position, designation, salary...).
 */
class SystemUserController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        $query = User::query()
            ->select('id', 'name', 'email', 'role', 'status', 'last_login_at', 'password_changed_at', 'created_at', 'two_factor_enabled');

        $roleFilter = $request->get('role');
        if ($roleFilter && in_array($roleFilter, RolePermission::roles(), true)) {
            // Match either the account's stored primary role, or a granted role via its
            // pivot table — a user whose primary role is Devotee but who also holds a
            // Committee/Event Coordinator/etc. grant should still show up under that filter.
            $grantTable = User::grantTables()[$roleFilter] ?? null;
            if ($grantTable) {
                $grantedUserIds = \Illuminate\Support\Facades\DB::table($grantTable)->pluck('user_id');
                $query->where(function ($q) use ($roleFilter, $grantedUserIds) {
                    $q->where('role', $roleFilter)->orWhereIn('id', $grantedUserIds);
                });
            } else {
                $query->where('role', $roleFilter);
            }
        }

        $search = $request->get('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate(25)->withQueryString();
        $roles = RolePermission::roles();

        return view('admin.system-users', compact('users', 'roles', 'roleFilter', 'search'));
    }

    public function sendResetLink(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        Password::sendResetLink(['email' => $targetUser->email]);

        AuditLogService::log("Sent password reset link to {$targetUser->email}");

        return redirect()->back()->with('success', "Reset link sent to {$targetUser->name}.");
    }

    /**
     * Admin-side per-user 2FA toggle — separate from the user's own self-service toggle on
     * their profile page (ProfileController::update()), so an admin can require or waive it
     * for any account regardless of what that user has chosen for themselves.
     */
    public function toggleTwoFactor(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $newState = !$targetUser->two_factor_enabled;
        $targetUser->update(['two_factor_enabled' => $newState]);

        AuditLogService::log(($newState ? 'Enabled' : 'Disabled') . " 2FA for: {$targetUser->email}");

        return redirect()->back()->with('success', "Two-factor authentication " . ($newState ? 'enabled' : 'disabled') . " for {$targetUser->name}.");
    }
}
