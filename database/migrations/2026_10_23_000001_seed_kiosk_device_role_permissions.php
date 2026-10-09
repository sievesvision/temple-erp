<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only migration (mirrors 2026_08_26_000000_seed_default_settings.php's own precedent)
 * — grants the new 'Kiosk Device' role 'add' on tickets/donations, which is all three of
 * TicketController::canSellTickets()/DonationController::canRecordDonation()/
 * canUseEftTerminal() need (each checks RolePermission::can($activeRole, ..., 'add') first,
 * before any role-specific fallback — confirmed by reading all three method bodies). No
 * existing controller or gate method needs any code change for the kiosk's own service
 * account to pass these checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')->insert([
            ['role' => 'Kiosk Device', 'resource' => 'tickets', 'can_view' => true, 'can_add' => true, 'can_edit' => false, 'can_delete' => false, 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'Kiosk Device', 'resource' => 'donations', 'can_view' => true, 'can_add' => true, 'can_edit' => false, 'can_delete' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('role', 'Kiosk Device')->delete();
    }
};
