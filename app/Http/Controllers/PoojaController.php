<?php

namespace App\Http\Controllers;

use App\Models\Pooja;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The Pooja Catalog — what a devotee/staff/priest picks from when creating a booking
 * (devotee wizard, public booking page, Pooja Portal, Ticket POS, Priest Portal — see the
 * Pooja Bookings plan). Distinct from "bookings", which manages individual booking
 * records; this manages the catalog of pooja TYPES they're booked against (name,
 * description, category, fee, duration). Permission resource: 'poojas'. Mirrors
 * TicketController's Ticket CRUD shape.
 */
class PoojaController extends Controller
{
    /**
     * The fixed category list from the temple's own "Rates for Archanas and Pujas"
     * sheet — Archanas themselves are sold as Tickets (see the 'Archana' ticket
     * category), not booked here. Shown as datalist suggestions on the add/edit form,
     * same free-text-with-suggestions pattern Ticket's own category field already uses
     * (see ticket-console.blade.php) — an admin can still type any other value.
     */
    public const SUGGESTED_CATEGORIES = [
        'Sponsored Poojas',
        'Sponsored Abishekams',
        'Festivals',
        'Other Functions',
        'Functions Outside the Temple',
    ];

    public function manage(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'poojas', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $query = Pooja::query();
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('pooja_name', 'like', "%{$request->search}%")
                    ->orWhere('category', 'like', "%{$request->search}%");
            });
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $poojas = $query->orderBy('category')->orderBy('pooja_name')->get();
        $categories = Pooja::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $canAdd = RolePermission::can(session('active_role', $user->role), 'poojas', 'add');
        $canEdit = RolePermission::can(session('active_role', $user->role), 'poojas', 'edit');
        $canDelete = RolePermission::can(session('active_role', $user->role), 'poojas', 'delete');
        $temple = Setting::templeBranding();

        // Same ?embed=1 convention as Manage Bookings — the Pooja Portal embeds this page
        // in an iframe pane, and this flag tells the layout to drop its own sidebar/topbar.
        $embed = $request->boolean('embed');

        return view('admin.manage-poojas', compact(
            'poojas', 'categories', 'canAdd', 'canEdit', 'canDelete', 'temple', 'embed'
        ));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'poojas', 'add')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'pooja_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:100',
            'pooja_fee' => 'required|numeric|min:0',
            'duration' => 'nullable|string|max:50',
            'status' => 'required|in:Active,Inactive',
            'online_allowed' => 'nullable|boolean',
        ]);
        $validated['online_allowed'] = $request->boolean('online_allowed');

        Pooja::create($validated);
        AuditLogService::log("Created pooja catalog entry '{$validated['pooja_name']}' ({$validated['pooja_fee']})");

        return redirect()->back()->with('success', 'Pooja added to the catalog.');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'poojas', 'edit')) {
            abort(403, 'Unauthorized access.');
        }

        $pooja = Pooja::where('pooja_id', $id)->firstOrFail();
        $validated = $request->validate([
            'pooja_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:100',
            'pooja_fee' => 'required|numeric|min:0',
            'duration' => 'nullable|string|max:50',
            'status' => 'required|in:Active,Inactive',
            'online_allowed' => 'nullable|boolean',
        ]);
        $validated['online_allowed'] = $request->boolean('online_allowed');

        $pooja->update($validated);
        AuditLogService::log("Updated pooja catalog entry '{$pooja->pooja_name}'");

        return redirect()->back()->with('success', 'Pooja updated.');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'poojas', 'delete')) {
            abort(403, 'Unauthorized access.');
        }

        $pooja = Pooja::where('pooja_id', $id)->firstOrFail();

        // Same "deactivate instead of delete once it has history" rule as
        // TicketController::deleteTicket() — a past booking still needs its pooja row to
        // join against (BookingController::manageBookings() etc. all join on pooja_id).
        if (DB::table('pooja_bookings')->where('pooja_id', $id)->exists()) {
            $pooja->update(['status' => 'Inactive']);
            return redirect()->back()->with('success', 'This pooja has past bookings, so it was deactivated instead of deleted.');
        }

        $pooja->delete();
        AuditLogService::log("Deleted pooja catalog entry '{$pooja->pooja_name}'");

        return redirect()->back()->with('success', 'Pooja deleted.');
    }
}
