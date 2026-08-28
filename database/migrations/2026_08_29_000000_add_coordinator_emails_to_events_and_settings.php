<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds per-event coordinator emails (CC'd on that event's donation receipts) and a
     * general donation_coordinator_emails setting (CC'd on donations not tied to an event
     * with its own coordinators). Both are comma-separated email lists.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('events', 'coordinator_emails')) {
            Schema::table('events', function (Blueprint $table) {
                $table->text('coordinator_emails')->nullable()->after('location');
            });
        }

        DB::table('settings')->insertOrIgnore([
            'key' => 'donation_coordinator_emails',
            'value' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'coordinator_emails')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('coordinator_emails');
            });
        }
    }
};
