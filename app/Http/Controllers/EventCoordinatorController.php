<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     * Grant an existing user Event Coordinator access to one specific event.
     */
    public function store(Request $request, $eventId)
    {
        if (!$this->requireAdmin()) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $event = Event::findOrFail($eventId);

        DB::table('event_coordinators')->insertOrIgnore([
            'user_id' => $validated['user_id'],
            'event_id' => $event->event_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Coordinator added for ' . $event->event_name . '.');
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
