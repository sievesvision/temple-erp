<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grants Committee full access to the new "poojas" resource (the Pooja Catalog CRUD —
 * name/description/category/fee/duration, distinct from "bookings" which manages
 * individual booking records), matching the same default grant it already has for
 * Donations/Bookings/Events/Tickets (see 2026_08_26_020000_add_committee_role_permissions.php
 * and 2026_10_01_000001_add_committee_tickets_permission.php) — this is the same kind of
 * day-to-day operational area, not something that should start locked out by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')->insertOrIgnore([[
            'role' => 'Committee',
            'resource' => 'poojas',
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_delete' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    public function down(): void
    {
        //
    }
};
