<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets an audit log entry be tied to a specific event, so the Event Console can show a
     * coordinator only the log entries relevant to their event instead of every admin action
     * system-wide. Nullable and unindexed-by-FK like the existing performed_by column — most
     * log entries (user management, system settings) have no event context at all.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('performed_by');
                $table->index('event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
