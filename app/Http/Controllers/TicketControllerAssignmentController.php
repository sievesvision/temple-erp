<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use App\Services\AccountSetupService;
use App\Services\AuditLogService;
use App\Services\TicketControllerLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Assigns/removes the "Ticket Controller" role and its view/entry/admin level
 * (ticket_controllers table — see TicketControllerLevel). A flat grant, unlike
 * event_coordinators: one row per user, never scoped to anything, since Tickets is a
 * standalone module with nothing to scope a level to. Mirrors EventCoordinatorController's
 * shape (existing-or-new-user add, level update, lock/unlock, reset link, remove) minus the
 * per-event id everywhere.
 */
class TicketControllerAssignmentController extends Controller
{
    /**
     * Whether the current user may manage Ticket Controller assignments at all — the system
     * Admin, anyone RolePermission grants 'tickets' edit to (e.g. Committee), or a Ticket
     * Controller already at 'admin' level.
     */
    private function canManageControllers(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $activeRole = session('active_role', $user->role ?? null);

        if ($activeRole === 'Admin' || RolePermission::can($activeRole, 'tickets', 'edit')) {
            return true;
        }

        if ($activeRole === 'Ticket Controller') {
            return TicketControllerLevel::atLeast(TicketControllerLevel::of($user->id), 'admin');
        }

        return false;
    }

    private function isSystemAdmin(): bool
    {
        $user = Auth::user();
        return $user && session('active_role', $user->role ?? null) === 'Admin';
    }

    /**
     * Grant Ticket Controller access — either to an existing user (picked from the dropdown)
     * or a brand-new account (name/email/mobile), mirroring EventCoordinatorController::store().
     */
    public function store(Request $request)
    {
        if (!$this->canManageControllers()) {
            return redirect()->route('admin.tickets.index')->with('error', 'Unauthorized access.');
        }

        $level = $this->resolveGrantableLevel($request->input('level', 'entry'));
        if ($level === null) {
            return redirect()->route('admin.tickets.index')->with('error', 'Only the system Admin (or a Ticket Admin) can grant Ticket Admin access.');
        }

        if ($request->filled('user_id')) {
            $validated = $request->validate(['user_id' => 'required|exists:users,id']);

            DB::table('ticket_controllers')->updateOrInsert(
                ['user_id' => $validated['user_id']],
                ['level' => $level, 'updated_at' => now(), 'created_at' => now()]
            );

            return redirect()->route('admin.tickets.index')->with('success', 'Ticket Controller access granted.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'mobile' => 'required|string|max:15',
        ]);

        $password = Str::random(40);

        DB::beginTransaction();
        try {
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($existingUser) {
                $userId = $existingUser->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $request->name,
                    'email' => $request->email,
                    'mobile' => $request->mobile,
                    'password' => Hash::make($password),
                    'role' => 'Ticket Controller',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $alreadyController = DB::table('ticket_controllers')->where('user_id', $userId)->exists();
            if ($alreadyController) {
                DB::rollBack();
                return redirect()->route('admin.tickets.index')->with('error', 'That person already has Ticket Controller access.')->withInput();
            }

            DB::table('ticket_controllers')->insert([
                'user_id' => $userId,
                'level' => $level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLogService::log("Added Ticket Controller: {$request->email}");
            DB::commit();

            if ($existingUser) {
                return redirect()->route('admin.tickets.index')->with('success', "{$request->name} has been granted Ticket Controller access — they can log in and switch to it from the topbar.");
            }

            $setup = AccountSetupService::sendPasswordSetupLink(User::find($userId));
            $msg = 'Ticket Controller Added Successfully!' . ($setup['emailed'] ? ' A password setup link has been emailed to them.' : '');

            if ($setup['show_link']) {
                return redirect()->route('admin.tickets.index')
                    ->with('success', $msg)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'setup_url' => $setup['url'],
                        'role' => 'Ticket Controller',
                    ]);
            }

            return redirect()->route('admin.tickets.index')->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.tickets.index')->with('error', 'Failed to add Ticket Controller: ' . $e->getMessage())->withInput();
        }
    }

    public function updateLevel(Request $request, $userId)
    {
        if (!$this->canManageControllers()) {
            return redirect()->route('admin.tickets.index')->with('error', 'Unauthorized access.');
        }

        $newLevel = $this->resolveGrantableLevel($request->input('level'));
        if ($newLevel === null) {
            return redirect()->route('admin.tickets.index')->with('error', 'Only the system Admin (or a Ticket Admin) can grant Ticket Admin access.');
        }

        if (!$this->isSystemAdmin()) {
            $currentLevel = DB::table('ticket_controllers')->where('user_id', $userId)->value('level');
            if ($currentLevel === 'admin') {
                return redirect()->route('admin.tickets.index')->with('error', 'Only the system Admin can change a Ticket Admin\'s access.');
            }
        }

        DB::table('ticket_controllers')->where('user_id', $userId)->update(['level' => $newLevel, 'updated_at' => now()]);

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket Controller access level updated.');
    }

    public function toggleLock(Request $request, $userId)
    {
        if (!$this->canManageControllers()) {
            return redirect()->route('admin.tickets.index')->with('error', 'Unauthorized access.');
        }

        $level = DB::table('ticket_controllers')->where('user_id', $userId)->value('level');
        if ($level === null) {
            return redirect()->route('admin.tickets.index')->with('error', 'That person does not have Ticket Controller access.');
        }
        if ($level === 'admin' && !$this->isSystemAdmin()) {
            return redirect()->route('admin.tickets.index')->with('error', 'Only the system Admin can lock or unlock a Ticket Admin.');
        }

        $targetUser = User::findOrFail($userId);
        $newStatus = $targetUser->status === 'Active' ? 'Inactive' : 'Active';
        $targetUser->update(['status' => $newStatus]);

        AuditLogService::log(($newStatus === 'Active' ? 'Unlocked' : 'Locked') . " account: {$targetUser->email}");

        return redirect()->route('admin.tickets.index')->with('success', $targetUser->name . ($newStatus === 'Active' ? ' has been unlocked.' : ' has been locked out.'));
    }

    public function sendResetLink(Request $request, $userId)
    {
        if (!$this->canManageControllers()) {
            return redirect()->route('admin.tickets.index')->with('error', 'Unauthorized access.');
        }

        $isController = DB::table('ticket_controllers')->where('user_id', $userId)->exists();
        if (!$isController) {
            return redirect()->route('admin.tickets.index')->with('error', 'That person does not have Ticket Controller access.');
        }

        $targetUser = User::findOrFail($userId);
        Password::sendResetLink(['email' => $targetUser->email]);
        $targetUser->update(['last_reset_email_sent_at' => now()]);
        AuditLogService::log("Sent password reset link to {$targetUser->email}");

        return redirect()->route('admin.tickets.index')->with('success', "Reset link sent to {$targetUser->name}.");
    }

    public function destroy(Request $request, $userId)
    {
        if (!$this->canManageControllers()) {
            return redirect()->route('admin.tickets.index')->with('error', 'Unauthorized access.');
        }

        if (!$this->isSystemAdmin()) {
            $targetLevel = DB::table('ticket_controllers')->where('user_id', $userId)->value('level');
            if ($targetLevel === 'admin') {
                return redirect()->route('admin.tickets.index')->with('error', 'Only the system Admin can remove a Ticket Admin.');
            }
        }

        DB::table('ticket_controllers')->where('user_id', $userId)->delete();

        return redirect()->route('admin.tickets.index')->with('success', 'Ticket Controller access removed.');
    }

    private function resolveGrantableLevel(?string $requestedLevel): ?string
    {
        $level = TicketControllerLevel::isValid($requestedLevel) ? $requestedLevel : 'entry';

        if ($level === 'admin' && !$this->isSystemAdmin()) {
            return null;
        }

        return $level;
    }
}
