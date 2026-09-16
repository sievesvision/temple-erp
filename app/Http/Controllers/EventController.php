<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventDonationOption;
use App\Models\Setting;
use App\Services\EventCoordinatorLevel;
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
        // This event's own payment-method override (if set) replaces the global Stripe
        // toggle entirely for its public donation page — same source of truth the console's
        // Quick Entry already uses via Event::paymentMethodsOverride().
        $paymentMethodsOverride = $event->paymentMethodsOverride();
        $stripeEnabled = $paymentMethodsOverride !== null
            ? in_array('Stripe', $paymentMethodsOverride, true)
            : (bool) Setting::get('stripe_enabled', true);

        $raised = DB::table('donations_without_logins')->where('event_id', $event->event_id)->where('payment_status', 'Paid')->sum('amount')
            + DB::table('donations')->where('event_id', $event->event_id)->where('payment_status', 'Paid')->sum('amount');

        $donationOptions = $event->donationOptions;
        $requireDonorEmail = (bool) $event->require_donor_email;
        $requireDonorMobile = (bool) $event->require_donor_mobile;
        $isClosed = $event->isClosedForDonations();

        return view('frontend.event-donate', compact('event', 'temple', 'raised', 'donationOptions', 'stripeEnabled', 'requireDonorEmail', 'requireDonorMobile', 'isClosed'));
    }

    /**
     * Display the events management dashboard.
     */
    public function manageEvents(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'events', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        // Get filter parameter if any
        $statusFilter = $request->input('status');

        $query = Event::orderBy('event_date', 'asc')->orderBy('start_time', 'asc');

        if ($statusFilter && in_array($statusFilter, ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'])) {
            $query->where('status', $statusFilter);
        }

        $events = $query->get();

        // Every existing account, for the "Coordinators" assignment modal — any user
        // regardless of primary role can be granted Event Coordinator access.
        $allUsers = DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get();

        return view('admin.manage-events', compact('events', 'statusFilter', 'allUsers'));
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'events', 'add')) {
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
            'qr_code_image' => 'nullable|string|max:255',
            'coordinator_emails' => 'nullable|string|max:1000',
            'donation_account_name' => 'nullable|string|max:255',
            'donation_bank_name' => 'nullable|string|max:255',
            'donation_bsb' => 'nullable|string|max:20',
            'donation_account_number' => 'nullable|string|max:50',
            'donation_contact_email' => 'nullable|string|max:500',
        ]);
        $validated['show_donation_summary'] = $request->boolean('show_donation_summary');
        $validated['require_donor_email'] = $request->boolean('require_donor_email');
        $validated['require_donor_mobile'] = $request->boolean('require_donor_mobile');
        $validated['date_tbc'] = $request->boolean('date_tbc');
        $validated['slug'] = Event::resolveSlug($validated['slug'] ?? null, $validated['event_name'], $validated['event_date']);

        try {
            $event = Event::create($validated);
            $this->saveDonationOptions($event, $request);
            $this->saveContacts($event, $request);
            $this->saveGalleryImages($event, $request);
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
        $activeRole = session('active_role', $user->role ?? null);
        // Only an event-admin coordinator may edit Settings — event-entry/event-view cannot.
        $isEventAdminCoordinator = $user && $activeRole === 'Event Coordinator'
            && EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of((int) $id, $user->id), 'admin');

        if (!$user || !(RolePermission::can($activeRole, 'events', 'edit') || $isEventAdminCoordinator)) {
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
            'qr_code_image' => 'nullable|string|max:255',
            'coordinator_emails' => 'nullable|string|max:1000',
            'donation_account_name' => 'nullable|string|max:255',
            'donation_bank_name' => 'nullable|string|max:255',
            'donation_bsb' => 'nullable|string|max:20',
            'donation_account_number' => 'nullable|string|max:50',
            'donation_contact_email' => 'nullable|string|max:500',
        ]);
        $validated['show_donation_summary'] = $request->boolean('show_donation_summary');
        $validated['require_donor_email'] = $request->boolean('require_donor_email');
        $validated['require_donor_mobile'] = $request->boolean('require_donor_mobile');
        $validated['date_tbc'] = $request->boolean('date_tbc');

        // Per-event payment method override. The older Manage Events modal doesn't have
        // this field at all and never sends it — a save from there must leave whatever
        // override is already set untouched, not silently reset it to "use global" just
        // because the field was absent from that particular form.
        if ($request->has('use_global_payment_methods')) {
            if ($request->boolean('use_global_payment_methods')) {
                $validated['enabled_payment_methods'] = null;
            } else {
                $methods = array_values(array_filter((array) $request->input('enabled_payment_methods', [])));
                $validated['enabled_payment_methods'] = $methods ? json_encode($methods) : null;
            }
        }

        try {
            $event = Event::findOrFail($id);
            $validated['slug'] = Event::resolveSlug($validated['slug'] ?? null, $validated['event_name'], $validated['event_date'], $event->event_id);
            $event->update($validated);
            $this->saveDonationOptions($event, $request);
            $this->saveContacts($event, $request);
            $this->saveGalleryImages($event, $request);
            return redirect()->back()->with('success', 'Event details and schedule updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update event: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Replace an event's donation options from the dynamic add/delete rows the admin form
     * now renders (see event-donation-options-fields.blade.php) — one row per configured
     * option, keyed by a stable identifier (the option's own id for existing rows, or a
     * client-generated "new_N" key for rows added in the browser) rather than a fixed slot
     * number, so rows can be freely added/removed up to the 12-option cap enforced client-side.
     *
     * Updates existing options in place (matched by that row's option_id) rather than
     * deleting and recreating them. Recreating would assign new auto-increment ids on every
     * save — even one that doesn't touch this section at all — silently orphaning every
     * donation_selections row that references the old ids (their event_donation_option_id
     * gets nulled via the FK's nullOnDelete, and the per-option column/export/console
     * breakdown for that donation quietly goes blank).
     */
    private function saveDonationOptions(Event $event, Request $request): void
    {
        $labels = $request->input('option_label');

        // A request that doesn't include this section at all (e.g. a hand-built or partial
        // form submission) must leave existing options untouched rather than wiping them.
        if (!is_array($labels)) {
            return;
        }

        // The add/delete UI caps this at 12 client-side; enforce the same cap here too in
        // case that's ever bypassed.
        $labels = array_slice($labels, 0, 12, true);

        $seenIds = [];
        $sortOrder = 1;

        foreach ($labels as $key => $rawLabel) {
            $label = trim((string) $rawLabel);
            $existingId = $request->input("option_id.$key");

            if ($label === '') {
                if ($existingId) {
                    EventDonationOption::where('id', $existingId)->where('event_id', $event->event_id)->delete();
                }
                continue;
            }

            $amountRaw = $request->input("option_amount.$key");
            $amount = ($amountRaw === null || $amountRaw === '') ? null : (float) $amountRaw;
            $allowQuantity = $request->boolean("option_allow_qty.$key");

            $option = $existingId
                ? EventDonationOption::where('id', $existingId)->where('event_id', $event->event_id)->first()
                : null;

            if ($option) {
                $option->update(['label' => $label, 'amount' => $amount, 'allow_quantity' => $allowQuantity, 'sort_order' => $sortOrder]);
            } else {
                $option = EventDonationOption::create([
                    'event_id' => $event->event_id,
                    'label' => $label,
                    'amount' => $amount,
                    'allow_quantity' => $allowQuantity,
                    'sort_order' => $sortOrder,
                ]);
            }

            $seenIds[] = $option->id;
            $sortOrder++;
        }

        // Anything not resubmitted this time (the admin removed a row entirely rather than
        // just blanking it) is gone too — same orphaning behaviour as the blank-row case.
        $event->donationOptions()->whereNotIn('id', $seenIds ?: [0])->delete();
    }

    /**
     * Replace an event's public contact list from the fixed 8-slot admin form (name+phone).
     * Blank name rows are skipped; stored as JSON on the event's `contacts` column.
     */
    private function saveContacts(Event $event, Request $request): void
    {
        $names = $request->input('contact_name');

        // A request that doesn't include this section at all must leave existing contacts
        // untouched, same reasoning as saveDonationOptions().
        if (!is_array($names)) {
            return;
        }

        $contacts = [];
        foreach ($names as $key => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }
            $contacts[] = [
                'name' => $name,
                'phone' => trim((string) $request->input("contact_phone.$key", '')),
            ];
        }
        $event->update(['contacts' => $contacts ? json_encode($contacts) : null]);
    }

    /**
     * Replace an event's public image gallery from a fixed 6-slot admin form (plain
     * manually-typed paths, same convention as header_image/flyer_image/qr_code_image).
     * Blank rows are skipped; stored as JSON on the event's `gallery_images` column.
     */
    private function saveGalleryImages(Event $event, Request $request): void
    {
        if (!$request->has('gallery_image_1')) {
            return;
        }

        $images = [];
        for ($i = 1; $i <= 6; $i++) {
            $path = trim((string) $request->input("gallery_image_$i", ''));
            if ($path === '') {
                continue;
            }
            $images[] = $path;
        }
        $event->update(['gallery_images' => $images ? json_encode($images) : null]);
    }

    /**
     * Remove the specified event.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'events', 'delete')) {
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
