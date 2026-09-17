<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountSetupService;
use App\Services\AuditLogService;
use App\Services\EventCoordinatorLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Assigns/removes the "Event Coordinator" role's per-event scope and level
 * (event_coordinators table: view/entry/admin — see EventCoordinatorLevel). Deliberately
 * separate from RoleGrantService — that service assumes one row per user per role table,
 * whereas a coordinator can hold many event assignments at once, and removing one shouldn't
 * touch the others (unlike every other role's all-or-nothing revoke).
 *
 * Two kinds of caller manage coordinators here: the system Admin (from Manage Events, or
 * this same console pane) can grant any level including 'admin'; an event-admin coordinator
 * can only manage entry/view coordinators for the one event they administer — they can
 * never grant or touch another 'admin'-level coordinator, including themselves.
 */
class EventCoordinatorController extends Controller
{
    private function isSystemAdmin(): bool
    {
        $user = Auth::user();
        return $user && session('active_role', $user->role ?? null) === 'Admin';
    }

    /**
     * Whether the current user may manage coordinators for this event at all — the system
     * Admin always can; an event-admin coordinator can too, but only for their own event.
     */
    private function canManageCoordinators($eventId): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        $user = Auth::user();
        $activeRole = $user ? session('active_role', $user->role ?? null) : null;
        if (!$user || $activeRole !== 'Event Coordinator') {
            return false;
        }

        return EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of((int) $eventId, $user->id), 'admin');
    }

    /**
     * The Event Coordinator's landing page — every event they're assigned to, each linking
     * into that event's console. Admin can also reach this (route is shared) to see the
     * page as a coordinator would, but has no real use for it since Manage Events already
     * shows everything.
     */
    public function myEvents()
    {
        $user = Auth::user();

        $events = DB::table('event_coordinators')
            ->join('events', 'event_coordinators.event_id', '=', 'events.event_id')
            ->where('event_coordinators.user_id', $user->id)
            ->select('events.*')
            ->orderBy('events.event_date', 'desc')
            ->get();

        $temple = Setting::templeBranding();

        return view('admin.event-coordinator-my-events', compact('events', 'temple'));
    }

    /**
     * List coordinators currently assigned to an event, plus their level — used to populate
     * the Manage Events "Coordinators" modal (Admin-only entry point).
     */
    public function index($eventId)
    {
        if (!$this->canManageCoordinators($eventId)) {
            abort(403, 'Unauthorized access.');
        }

        $coordinators = DB::table('event_coordinators')
            ->join('users', 'event_coordinators.user_id', '=', 'users.id')
            ->where('event_coordinators.event_id', $eventId)
            ->select('users.id', 'users.name', 'users.email', 'users.status', 'users.last_login_at', 'users.last_reset_email_sent_at', 'event_coordinators.level')
            ->orderBy('users.name')
            ->get();

        return response()->json(['coordinators' => $coordinators]);
    }

    /**
     * Redirect back to wherever the action was actually submitted from. A plain back() is
     * environment-dependent (Referer headers, session-tracked previous URL) and was observed
     * bouncing the console's own Coordinators pane out to the coordinator's landing page
     * instead of staying put — so the console pane's forms pin this explicitly via a hidden
     * "return_context" field; anywhere else (the Manage Events modal) that field is absent
     * and this falls back to the previous back() behaviour, unchanged.
     */
    private function redirectAfterAction(Request $request, $eventId)
    {
        if ($request->input('return_context') === 'console') {
            return redirect()->route('admin.events.console', $eventId);
        }

        return redirect()->back();
    }

    /**
     * Grant Event Coordinator access to this event — either to an existing user (picked
     * from the dropdown) or a brand-new account (name/email/mobile), mirroring the
     * existing-or-new pattern every other "Add X" page uses (see CommitteeController::
     * storeCommittee() for the reference implementation this follows).
     */
    public function store(Request $request, $eventId)
    {
        if (!$this->canManageCoordinators($eventId)) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Unauthorized access.');
        }

        $event = Event::findOrFail($eventId);
        $level = $this->resolveGrantableLevel($request->input('level', 'entry'));
        if ($level === null) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Only the system Admin can grant Event Admin access.');
        }

        // Existing-user path: just the dropdown selection, no new account involved.
        if ($request->filled('user_id')) {
            $validated = $request->validate(['user_id' => 'required|exists:users,id']);

            DB::table('event_coordinators')->insertOrIgnore([
                'user_id' => $validated['user_id'],
                'event_id' => $event->event_id,
                'level' => $level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->redirectAfterAction($request, $eventId)->with('success', 'Coordinator added for ' . $event->event_name . '.');
        }

        // New-user path: create the account (or grant an existing one matched by
        // email/mobile) then assign them as coordinator, same as Add Committee/Priest/etc.
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'mobile' => 'required|string|max:15',
        ]);

        // Never shown or emailed anywhere — the account is only reachable via the
        // password-setup link sent below (AccountSetupService), never this value.
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
                    'role' => 'Event Coordinator',
                    'status' => 'Active',
                    'must_change_password' => 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $alreadyCoordinator = DB::table('event_coordinators')->where('user_id', $userId)->where('event_id', $event->event_id)->exists();
            if ($alreadyCoordinator) {
                DB::rollBack();
                return $this->redirectAfterAction($request, $eventId)->with('error', 'That person is already a coordinator for this event.')->withInput();
            }

            DB::table('event_coordinators')->insert([
                'user_id' => $userId,
                'event_id' => $event->event_id,
                'level' => $level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLogService::log("Added Event Coordinator: {$request->email} for {$event->event_name}", null, $event->event_id);
            DB::commit();

            if ($existingUser) {
                return $this->redirectAfterAction($request, $eventId)->with('success', "{$request->name} has been granted Event Coordinator access for {$event->event_name} — they can log in and switch to it from the topbar.");
            }

            $setup = AccountSetupService::sendPasswordSetupLink(User::find($userId));
            $msg = 'Event Coordinator Added Successfully!' . ($setup['emailed'] ? ' A password setup link has been emailed to them.' : '');

            if ($setup['show_link']) {
                return $this->redirectAfterAction($request, $eventId)
                    ->with('success', $msg)
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'setup_url' => $setup['url'],
                        'role' => 'Event Coordinator',
                    ]);
            }

            return $this->redirectAfterAction($request, $eventId)->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Failed to add Event Coordinator: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Change an existing coordinator's level. The system Admin can set any level; an
     * event-admin coordinator can only move someone between entry/view, and can't touch a
     * coordinator who is currently 'admin' (including demoting themselves this way).
     */
    public function updateLevel(Request $request, $eventId, $userId)
    {
        if (!$this->canManageCoordinators($eventId)) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Unauthorized access.');
        }

        $newLevel = $this->resolveGrantableLevel($request->input('level'));
        if ($newLevel === null) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Only the system Admin can grant Event Admin access.');
        }

        if (!$this->isSystemAdmin()) {
            $currentLevel = DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)->value('level');
            if ($currentLevel === 'admin') {
                return $this->redirectAfterAction($request, $eventId)->with('error', 'Only the system Admin can change an Event Admin\'s access.');
            }
        }

        DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)
            ->update(['level' => $newLevel, 'updated_at' => now()]);

        return $this->redirectAfterAction($request, $eventId)->with('success', 'Coordinator access level updated.');
    }

    /**
     * Lock/unlock a coordinator's account (flips users.status Active <-> Inactive) — a locked
     * account is blocked at login entirely (see AuthController::login()), not just at this
     * event's console. Same admin-vs-event-admin protection as destroy()/updateLevel(): an
     * event-admin coordinator can't lock/unlock another 'admin'-level coordinator.
     */
    public function toggleLock(Request $request, $eventId, $userId)
    {
        if (!$this->canManageCoordinators($eventId)) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Unauthorized access.');
        }

        $coordinatorLevel = DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)->value('level');
        if ($coordinatorLevel === null) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'That person does not coordinate this event.');
        }
        if ($coordinatorLevel === 'admin' && !$this->isSystemAdmin()) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Only the system Admin can lock or unlock an Event Admin.');
        }

        $targetUser = User::findOrFail($userId);
        $newStatus = $targetUser->status === 'Active' ? 'Inactive' : 'Active';
        $targetUser->update(['status' => $newStatus]);

        AuditLogService::log(($newStatus === 'Active' ? 'Unlocked' : 'Locked') . " account: {$targetUser->email}", null, $eventId);

        return $this->redirectAfterAction($request, $eventId)->with('success', $targetUser->name . ($newStatus === 'Active' ? ' has been unlocked.' : ' has been locked out.'));
    }

    /**
     * Send a password reset link to one of this event's coordinators — mirrors
     * SystemUserController::sendResetLink() but scoped to coordinators the caller manages.
     */
    public function sendResetLink(Request $request, $eventId, $userId)
    {
        if (!$this->canManageCoordinators($eventId)) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Unauthorized access.');
        }

        $isCoordinator = DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)->exists();
        if (!$isCoordinator) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'That person does not coordinate this event.');
        }

        $targetUser = User::findOrFail($userId);
        Password::sendResetLink(['email' => $targetUser->email]);
        $targetUser->update(['last_reset_email_sent_at' => now()]);
        AuditLogService::log("Sent password reset link to {$targetUser->email}", null, $eventId);

        return $this->redirectAfterAction($request, $eventId)->with('success', "Reset link sent to {$targetUser->name}.");
    }

    /**
     * Revoke one user's Event Coordinator access to one specific event — their access to
     * any other event they coordinate is untouched. An event-admin coordinator can't remove
     * another 'admin'-level coordinator (including themselves) this way.
     */
    public function destroy(Request $request, $eventId, $userId)
    {
        if (!$this->canManageCoordinators($eventId)) {
            return $this->redirectAfterAction($request, $eventId)->with('error', 'Unauthorized access.');
        }

        if (!$this->isSystemAdmin()) {
            $targetLevel = DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)->value('level');
            if ($targetLevel === 'admin') {
                return $this->redirectAfterAction($request, $eventId)->with('error', 'Only the system Admin can remove an Event Admin.');
            }
        }

        DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)->delete();

        return $this->redirectAfterAction($request, $eventId)->with('success', 'Coordinator access removed.');
    }

    /**
     * Validates a requested level and enforces that only the system Admin may grant
     * 'admin' — an event-admin coordinator's request for 'admin' returns null (caller
     * turns that into an error) rather than silently downgrading it, so the mistake is
     * visible instead of quietly granting the wrong access.
     */
    private function resolveGrantableLevel(?string $requestedLevel): ?string
    {
        $level = EventCoordinatorLevel::isValid($requestedLevel) ? $requestedLevel : 'entry';

        if ($level === 'admin' && !$this->isSystemAdmin()) {
            return null;
        }

        return $level;
    }
}
