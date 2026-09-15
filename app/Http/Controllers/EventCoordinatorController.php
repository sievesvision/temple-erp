<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\Event;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Assigns/removes the "Event Coordinator" role's per-event scope (event_coordinators
 * table). Deliberately separate from RoleGrantService — that service assumes one row per
 * user per role table, whereas a coordinator can hold many event assignments at once, and
 * removing one shouldn't touch the others (unlike every other role's all-or-nothing revoke).
 * Admin-only: this grants access to specific event data, not a general capability toggle.
 */
class EventCoordinatorController extends Controller
{
    private function requireAdmin()
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        return $user && $activeRole === 'Admin';
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

        return view('admin.event-coordinator-my-events', compact('events'));
    }

    /**
     * List coordinators currently assigned to an event, plus all users eligible to be
     * added (any existing account) — used to populate the "Coordinators" modal.
     */
    public function index($eventId)
    {
        if (!$this->requireAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $coordinators = DB::table('event_coordinators')
            ->join('users', 'event_coordinators.user_id', '=', 'users.id')
            ->where('event_coordinators.event_id', $eventId)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get();

        return response()->json(['coordinators' => $coordinators]);
    }

    /**
     * Grant Event Coordinator access to this event — either to an existing user (picked
     * from the dropdown) or a brand-new account (name/email/mobile), mirroring the
     * existing-or-new pattern every other "Add X" page uses (see CommitteeController::
     * storeCommittee() for the reference implementation this follows).
     */
    public function store(Request $request, $eventId)
    {
        if (!$this->requireAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $event = Event::findOrFail($eventId);

        // Existing-user path: just the dropdown selection, no new account involved.
        if ($request->filled('user_id')) {
            $validated = $request->validate(['user_id' => 'required|exists:users,id']);

            DB::table('event_coordinators')->insertOrIgnore([
                'user_id' => $validated['user_id'],
                'event_id' => $event->event_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Coordinator added for ' . $event->event_name . '.');
        }

        // New-user path: create the account (or grant an existing one matched by
        // email/mobile) then assign them as coordinator, same as Add Committee/Priest/etc.
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'mobile' => 'required|string|max:15',
        ]);

        $password = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

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
                return redirect()->back()->with('error', 'That person is already a coordinator for this event.')->withInput();
            }

            DB::table('event_coordinators')->insert([
                'user_id' => $userId,
                'event_id' => $event->event_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditLogService::log("Added Event Coordinator: {$request->email} for {$event->event_name}");
            DB::commit();

            if ($existingUser) {
                return redirect()->back()->with('success', "{$request->name} has been granted Event Coordinator access for {$event->event_name} — they can log in and switch to it from the topbar.");
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
                    Mail::to($request->email)->send(new WelcomeMail($request->name, 'Event Coordinator', $request->email, $password));
                } catch (\Exception $e) {
                    // Log or handle mail error silently
                }
            }

            if ($flashPassword) {
                return redirect()->back()
                    ->with('success', 'Event Coordinator Added Successfully!')
                    ->with('success_user_created', [
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => $password,
                        'role' => 'Event Coordinator',
                    ]);
            }

            return redirect()->back()->with('success', 'Event Coordinator Added Successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to add Event Coordinator: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Revoke one user's Event Coordinator access to one specific event — their access to
     * any other event they coordinate is untouched.
     */
    public function destroy($eventId, $userId)
    {
        if (!$this->requireAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        DB::table('event_coordinators')->where('event_id', $eventId)->where('user_id', $userId)->delete();

        return redirect()->back()->with('success', 'Coordinator access removed.');
    }
}
