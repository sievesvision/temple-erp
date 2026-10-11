<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use App\Services\AccountSetupService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
            ->select('id', 'name', 'email', 'username', 'role', 'status', 'last_login_at', 'password_changed_at', 'created_at', 'two_factor_enabled', 'pos_pin_locked_at');

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
        $creatableRoles = array_values(array_diff($roles, ['Event Coordinator', 'Ticket Controller', 'Kiosk Device']));

        return view('admin.system-users', compact('users', 'roles', 'creatableRoles', 'roleFilter', 'search'));
    }

    /**
     * One centralized "create a user of any role" path, alongside (not replacing) the
     * existing per-role Add pages (Committee/Priest/Trustee/Staff/Accountant each have
     * their own, with role-specific fields like position/designation/salary — those still
     * exist and still work). This covers the organisation-admin need to create any kind
     * of account from one screen, mirroring the exact same proven pattern every one of
     * those pages already uses: match an existing account by email/mobile first (grant
     * the role, leave everything else about them alone), else create a brand-new account
     * with an unusable random password and send a password-setup link — never a visible
     * plaintext password.
     *
     * Deliberately excludes 'Event Coordinator' and 'Ticket Controller' from the role
     * list: both need an event (or, for tickets, nothing) picked at grant time, which is
     * exactly what the "Event & Ticket Access" action (eventAccess()/the existing
     * EventCoordinatorController/TicketControllerAssignmentController routes) already
     * does — creating the account here and granting that access there keeps the two
     * concerns separate instead of duplicating event-selection logic in this form too.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $creatableRoles = array_values(array_diff(RolePermission::roles(), ['Event Coordinator', 'Ticket Controller', 'Kiosk Device']));

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'mobile' => 'required|string|max:15',
            'role' => 'required|in:' . implode(',', $creatableRoles),
        ]);

        DB::beginTransaction();
        try {
            $existingUser = DB::table('users')->where('email', $request->email)->orWhere('mobile', $request->mobile)->first();

            if ($existingUser) {
                if ($existingUser->role === $request->role || $this->userHoldsRole((int) $existingUser->id, $request->role)) {
                    DB::rollBack();
                    return redirect()->back()->with('error', $existingUser->name . ' already holds the ' . $request->role . ' role.')->withInput();
                }

                $userId = (int) $existingUser->id;
                $this->grantRoleTable($userId, $request->role);
                AuditLogService::log("Granted {$request->role} access to {$existingUser->email}.");
                DB::commit();

                return redirect()->route('admin.users.index')
                    ->with('success', "{$existingUser->name} has been granted {$request->role} access — they can log in choosing it with their existing password.");
            }

            $userId = DB::table('users')->insertGetId([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make(Str::random(40)),
                'role' => $request->role,
                'status' => 'Active',
                'must_change_password' => 1,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->grantRoleTable($userId, $request->role);
            AuditLogService::log("Created {$request->role} user: {$request->email}");
            DB::commit();

            $newUser = User::find($userId);
            $setup = AccountSetupService::sendPasswordSetupLink($newUser);
            $message = ucfirst($request->role) . ' created successfully.' . ($setup['emailed'] ? ' A password setup link has been emailed to them.' : '');

            if ($setup['show_link']) {
                return redirect()->route('admin.users.index')
                    ->with('success', $message)
                    ->with('success_user_created', [
                        'name' => $request->name, 'email' => $request->email,
                        'setup_url' => $setup['url'], 'role' => $request->role,
                    ]);
            }

            return redirect()->route('admin.users.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create user: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Whether a user already holds a given grantable role — checked against the role's
     * own grant table (Admin/Devotee have none, so a role match there is just the primary
     * role, already checked by the caller).
     */
    private function userHoldsRole(int $userId, string $role): bool
    {
        $table = User::grantTables()[$role] ?? null;

        return $table && DB::table($table)->where('user_id', $userId)->exists();
    }

    /**
     * Inserts the one grant-table row a role needs beyond users.role itself — Admin and
     * Devotee have no grant table (the users.role value alone is the grant). Every
     * NOT-NULL column besides user_id on every grant table already has a database
     * default except priests.priest_id, which (like every existing "Add Priest" flow)
     * is generated the same sequential 'PRIESTnnnn' way here.
     */
    private function grantRoleTable(int $userId, string $role): void
    {
        $table = User::grantTables()[$role] ?? null;
        if (!$table) {
            return;
        }

        $fields = ['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()];

        if ($role === 'Priest') {
            $maxPriestId = DB::table('priests')->max('priest_id');
            $nextNum = $maxPriestId ? ((int) filter_var($maxPriestId, FILTER_SANITIZE_NUMBER_INT)) + 1 : 1;
            $fields['priest_id'] = 'PRIEST' . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
        }

        DB::table($table)->insert($fields);
    }

    /**
     * Name/email/mobile and active/inactive status — a genuine gap today (only the
     * Committee edit form can deactivate an account at all, and nothing can edit an Event
     * Coordinator's or Ticket Controller's own details). Role itself is never editable
     * here — see the plan's "explicitly out of scope" note on why a generic role-transfer
     * tool isn't part of this pass.
     */
    public function update(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $targetUser->id,
            'mobile' => 'required|string|max:15|unique:users,mobile,' . $targetUser->id,
            'status' => 'required|in:Active,Inactive',
        ]);

        $targetUser->update([
            'name' => $request->name, 'email' => $request->email,
            'mobile' => $request->mobile, 'status' => $request->status,
        ]);

        AuditLogService::log("Updated account details for {$targetUser->email}.");

        return redirect()->back()->with('success', $targetUser->name . "'s details have been updated.");
    }

    /**
     * One-click activate/deactivate, for when there's nothing else to change — the Edit
     * modal's own status field covers the same thing when editing other details at the
     * same time. Deactivating blocks every login path (AuthController checks
     * status === 'Active' on both email and PIN login) without deleting anything.
     */
    public function toggleStatus(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $newStatus = $targetUser->status === 'Active' ? 'Inactive' : 'Active';
        $targetUser->update(['status' => $newStatus]);

        AuditLogService::log(($newStatus === 'Active' ? 'Activated' : 'Deactivated') . " account: {$targetUser->email}.");

        return redirect()->back()->with('success', $targetUser->name . ' is now ' . $newStatus . '.');
    }

    /**
     * Every event this user coordinates (any level) plus their ticket-controller grant,
     * if any — a query that doesn't exist anywhere else in the app (the closest,
     * myEvents(), only ever covers the currently logged-in user). Feeds the "Event &
     * Ticket Access" modal, which then adds/removes access by posting straight to the
     * existing, unchanged EventCoordinatorController/TicketControllerAssignmentController
     * routes with this user's id — no new assignment logic, just a user-centric view of it.
     */
    public function eventAccess(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        $events = DB::table('event_coordinators')
            ->join('events', 'event_coordinators.event_id', '=', 'events.event_id')
            ->where('event_coordinators.user_id', $targetUser->id)
            ->orderBy('events.event_date', 'desc')
            ->select('events.event_id', 'events.event_name', 'events.slug', 'event_coordinators.level')
            ->get();

        $ticketLevel = DB::table('ticket_controllers')->where('user_id', $targetUser->id)->value('level');

        $allEvents = DB::table('events')->orderBy('event_date', 'desc')->select('event_id', 'event_name')->get();

        return response()->json(['events' => $events, 'ticket_level' => $ticketLevel, 'all_events' => $allEvents]);
    }

    /**
     * Assigns (or clears) the short POS username a pos-level Event Coordinator / Ticket
     * Controller types alongside their PIN at /pos/login — deliberately admin-set, not
     * self-service, since it's the account's own public-facing counter identifier, separate
     * from the PIN itself.
     */
    public function setUsername(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'username' => 'nullable|alpha_num|min:6|max:10|unique:users,username,' . $targetUser->id,
        ]);

        $targetUser->update(['username' => $request->username ? strtolower($request->username) : null]);

        AuditLogService::log("Set POS username for {$targetUser->email} to " . ($request->username ?: '(cleared)') . '.');

        return redirect()->back()->with('success', 'POS username updated for ' . $targetUser->name . '.');
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
     * The per-account POS PIN login lockout (see AuthController::attemptPosPinLogin())
     * is normally cleared by that account's own next successful email/password login — this
     * is the "or by admin" escape hatch for when nobody's touched a real login yet (e.g.
     * mid-shift and the office needs that one counter's PIN access back immediately).
     */
    public function resetPosPinLockout(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $targetUser->update(['pos_pin_failed_attempts' => 0, 'pos_pin_locked_at' => null]);

        AuditLogService::log("Reset the POS PIN login lockout for {$targetUser->email}.");

        return redirect()->back()->with('success', 'POS PIN login has been re-enabled for ' . $targetUser->name . '.');
    }

    /**
     * Organisation Admin's "Initiate secure PIN resets" capability — same structural
     * guarantee as the Event Console's version of this action: the PIN is never typed or
     * viewed here, only the one-time link is triggered (see
     * AccountSetupService::sendPosPinSetupLink()).
     */
    public function sendPosPinSetupLink(Request $request, User $targetUser)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $setup = AccountSetupService::sendPosPinSetupLink($targetUser);
        AuditLogService::log("Sent POS PIN setup link to {$targetUser->email}.");

        if ($setup['show_link']) {
            return redirect()->back()
                ->with('success', 'PIN setup link generated for ' . $targetUser->name . '.')
                ->with('pos_pin_setup_link', ['name' => $targetUser->name, 'url' => $setup['url']]);
        }

        return redirect()->back()->with('success', 'PIN setup link emailed to ' . $targetUser->name . '.');
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
