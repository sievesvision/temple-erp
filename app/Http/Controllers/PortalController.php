<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A single card-based hub listing every "portal" in the system — each open event's own
 * portal (admin.events.portal) plus the Pooja Portal (ticket sales and, via its own
 * sidebar pane, pooja bookings — admin.tickets.index). Clicking a card just opens that
 * portal's existing, unmodified admin view.
 */
class PortalController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        $events = DB::table('events')->orderByDesc('event_date')->select('event_id', 'event_name', 'event_date', 'status', 'slug')->get();

        return view('admin.portals', compact('events'));
    }
}
