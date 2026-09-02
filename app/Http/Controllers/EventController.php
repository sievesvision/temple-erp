<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventDonationOption;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\RolePermission;

class EventController extends Controller
{
    /**
     * Public event page — event details plus a donation form scoped to this event.
     */
    public function showPublic($slug)
    {
        $event = Event::where('slug', $slug)->first();
        if (!$event) {
            abort(404);
        }

        $temple = Setting::templeBranding();
        $stripeEnabled = (bool) Setting::get('stripe_enabled', true);

        $raised = DB::table('donations_without_logins')->where('event_id', $event->event_id)->where('payment_status', 'Paid')->sum('amount')
            + DB::table('donations')->where('event_id', $event->event_id)->where('payment_status', 'Paid')->sum('amount');

        $donationOptions = $event->donationOptions;

        return view('frontend.event-donate', compact('event', 'temple', 'raised', 'donationOptions', 'stripeEnabled'));
    }

    /**
     * Display the events management dashboard.
     */
    public function manageEvents(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'events', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        // Get filter parameter if any
        $statusFilter = $request->input('status');

        $query = Event::orderBy('event_date', 'asc')->orderBy('start_time', 'asc');

        if ($statusFilter && in_array($statusFilter, ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'])) {
            $query->where('status', $statusFilter);
        }

        $events = $query->get();

        return view('admin.manage-events', compact('events', 'statusFilter'));
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'events', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'event_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[A-Za-z0-9\-\s]*$/',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => 'required|string|max:10',
            'end_time' => 'required|string|max:10',
            'location' => 'required|string|max:255',
            'status' => 'required|string|in:Upcoming,Ongoing,Completed,Cancelled',
            'header_image' => 'nullable|string|max:255',
            'flyer_image' => 'nullable|string|max:255',
            'coordinator_emails' => 'nullable|string|max:1000',
        ]);
        $validated['show_donation_summary'] = $request->boolean('show_donation_summary');
        $validated['slug'] = Event::resolveSlug($validated['slug'] ?? null, $validated['event_name'], $validated['event_date']);

        try {
            $event = Event::create($validated);
            $this->saveDonationOptions($event, $request);
            return redirect()->back()->with('success', 'Event scheduled and created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create event: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update the specified event.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'events', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'event_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|regex:/^[A-Za-z0-9\-\s]*$/',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => 'required|string|max:10',
            'end_time' => 'required|string|max:10',
            'location' => 'required|string|max:255',
            'status' => 'required|string|in:Upcoming,Ongoing,Completed,Cancelled',
            'header_image' => 'nullable|string|max:255',
            'flyer_image' => 'nullable|string|max:255',
            'coordinator_emails' => 'nullable|string|max:1000',
        ]);
        $validated['show_donation_summary'] = $request->boolean('show_donation_summary');

        try {
            $event = Event::findOrFail($id);
            $validated['slug'] = Event::resolveSlug($validated['slug'] ?? null, $validated['event_name'], $validated['event_date'], $event->event_id);
            $event->update($validated);
            $this->saveDonationOptions($event, $request);
            return redirect()->back()->with('success', 'Event details and schedule updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update event: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Replace an event's donation options from the fixed 5-slot admin form.
     * Blank label rows are skipped; a blank amount means "donor enters any amount".
     */
    private function saveDonationOptions(Event $event, Request $request): void
    {
        $event->donationOptions()->delete();

        for ($i = 1; $i <= 5; $i++) {
            $label = trim((string) $request->input("option_label_$i", ''));
            if ($label === '') {
                continue;
            }

            $amountRaw = $request->input("option_amount_$i");
            $amount = ($amountRaw === null || $amountRaw === '') ? null : (float) $amountRaw;

            EventDonationOption::create([
                'event_id' => $event->event_id,
                'label' => $label,
                'amount' => $amount,
                'allow_quantity' => $request->boolean("option_allow_qty_$i"),
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * Remove the specified event.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can($user->role, 'events', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            $event = Event::findOrFail($id);
            $event->delete();
            return redirect()->back()->with('success', 'Event deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete event: ' . $e->getMessage());
        }
    }
}
