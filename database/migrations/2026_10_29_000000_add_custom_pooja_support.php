<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the counter (Staff dashboard, Ticket POS, Pooja Portal) book something that isn't
 * in the pooja catalog — a one-off request with its own description and fee, entered at
 * booking time. pooja_bookings.pooja_id stays NOT NULL (every existing INNER JOIN on it —
 * BookingController::manageBookings()/downloadReceipt(), PriestController::dashboard(),
 * PriestAvailabilityService, etc. — would silently drop any row with a null FK), so a
 * custom booking still points at a real, permanent "Custom Pooja" catalog row; what makes
 * it "custom" is this new custom_pooja_label column overriding that row's generic name
 * for display, plus BookingService::create()'s $data['custom_fee'] overriding the
 * catalog's $0 placeholder fee. The anchor row's id is recorded in Settings
 * ('custom_pooja_id') rather than hardcoded or matched by name, so it keeps working even
 * if an admin renames it later via Manage Poojas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pooja_bookings', function (Blueprint $table) {
            $table->string('custom_pooja_label')->nullable()->after('pooja_id');
        });

        $customPoojaId = DB::table('poojas')->where('pooja_name', 'Custom Pooja')->value('pooja_id');
        if (!$customPoojaId) {
            $customPoojaId = DB::table('poojas')->insertGetId([
                'pooja_name' => 'Custom Pooja',
                'description' => "Used when a devotee requests something not in the catalog — the counter enters its own description and fee at booking time.",
                'category' => 'Custom',
                'pooja_fee' => 0,
                'online_allowed' => false,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'custom_pooja_id'],
            ['value' => (string) $customPoojaId, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('pooja_bookings', function (Blueprint $table) {
            $table->dropColumn('custom_pooja_label');
        });

        DB::table('settings')->where('key', 'custom_pooja_id')->delete();
        // The anchor Pooja row itself is left in place — it may already be referenced by
        // real bookings by the time this would ever be rolled back.
    }
};
