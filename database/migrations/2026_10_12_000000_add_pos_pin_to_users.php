<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A kiosk-only account (pos-level Event Coordinator, view/entry-level Ticket Controller)
     * may set an optional 6-digit PIN as a faster alternative to email+password on a counter
     * terminal — hashed exactly like `password` (see User::casts()), never stored plain. Only
     * the account holder can set/change it, and only by re-proving their real password in the
     * same request (see AuthController::updateKioskPinSettings()) — this column alone never
     * grants that ability. `pos_pin_set_at` is purely informational, shown on the settings
     * page so the holder can see when it was last changed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pos_pin')) {
                $table->string('pos_pin')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'pos_pin_set_at')) {
                $table->timestamp('pos_pin_set_at')->nullable()->after('pos_pin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pos_pin', 'pos_pin_set_at']);
        });
    }
};
