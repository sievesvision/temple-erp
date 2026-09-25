<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the single-PIN-per-user model (`pos_pin`/`pos_pin_set_at`) with a short
     * admin-assigned `username` plus a per-user, per-destination lockout — see
     * `create_kiosk_pins_table` for where the actual PINs move to. A single ambiguous PIN
     * can't be safely carried over onto "which destination did this belong to", so any
     * PIN set under the old system is dropped here; affected accounts need a username
     * assigned and fresh PIN(s) set per counter afterwards.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 6)->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'kiosk_pin_failed_attempts')) {
                $table->unsignedTinyInteger('kiosk_pin_failed_attempts')->default(0)->after('password');
            }
            if (!Schema::hasColumn('users', 'kiosk_pin_locked_at')) {
                $table->timestamp('kiosk_pin_locked_at')->nullable()->after('kiosk_pin_failed_attempts');
            }
        });

        if (Schema::hasColumn('users', 'pos_pin') || Schema::hasColumn('users', 'pos_pin_set_at')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = array_filter(['pos_pin', 'pos_pin_set_at'], fn ($c) => Schema::hasColumn('users', $c));
                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'kiosk_pin_failed_attempts', 'kiosk_pin_locked_at']);
            $table->string('pos_pin')->nullable()->after('password');
            $table->timestamp('pos_pin_set_at')->nullable()->after('pos_pin');
        });
    }
};
