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
            ->select('id', 'name', 'email', 'role', 'status', 'last_login_at', 'password_changed_at', 'created_at');

        $roleFilter = $request->get('role');
        if ($roleFilter && in_array($roleFilter, RolePermission::roles(), true)) {
            $query->where('role', $roleFilter);
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
}
