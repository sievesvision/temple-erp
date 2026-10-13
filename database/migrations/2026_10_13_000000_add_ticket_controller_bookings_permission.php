<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ticket Controller never got any role_permissions rows at all (it's granted via the
     * ticket_controllers pivot table, not RolePermission::roles()'s seeded list) — meaning
     * RolePermission::can('Ticket Controller', 'bookings', ...) was false for every action,
     * including view. That's dead-on-arrival for the Pooja Bookings plan's Phase D/E: a
     * Ticket Controller can already reach the Manage Bookings pane embedded in the Ticket
     * Console (route middleware allows it), but the page's own in-code permission check
     * would 403 them, and the new "Create Booking" action needs can_add too. Mirrors the
     * Committee seed migration's narrower, resource-scoped grant pattern — view + add only,
     * leaving edit/delete as an explicit admin decision later.
     */
    public function up(): void
    {
        DB::table('role_permissions')->insertOrIgnore([
            'role' => 'Ticket Controller',
            'resource' => 'bookings',
            'can_view' => true,
            'can_add' => true,
            'can_edit' => false,
            'can_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * No-op: mirrors the other role_permissions seed migrations' rollback policy — never
     * delete permission data on down(), since we can't tell what was already there.
     */
    public function down(): void
    {
        //
    }
};
