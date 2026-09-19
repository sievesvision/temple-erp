<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grants Committee full access to the new "tickets" resource, matching the same default
 * grant it already has for Donations/Bookings/Events (see
 * 2026_08_26_020000_add_committee_role_permissions.php) — Tickets is an operational,
 * day-to-day area of the same kind, not something that should start locked out by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')->insertOrIgnore([[
            'role' => 'Committee',
            'resource' => 'tickets',
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
