<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Kiosk" was always this app's own name for POS/counter login — a genuinely locked-down
     * kiosk device (a separate, future single-page app) doesn't exist yet, so that name is
     * freed up here rather than risk colliding with it later. Table/column rename only; no
     * data changes. See the matching rename of routes, the KioskPin model (now PosPin), and
     * every kiosk-named method/variable across the app.
     */
    public function up(): void
    {
        Schema::rename('kiosk_pins', 'pos_pins');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('kiosk_pin_failed_attempts', 'pos_pin_failed_attempts');
            $table->renameColumn('kiosk_pin_locked_at', 'pos_pin_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('pos_pin_failed_attempts', 'kiosk_pin_failed_attempts');
            $table->renameColumn('pos_pin_locked_at', 'kiosk_pin_locked_at');
        });

        Schema::rename('pos_pins', 'kiosk_pins');
    }
};
