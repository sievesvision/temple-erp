<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-event permission tier for an Event Coordinator: 'view' (read-only), 'entry'
     * (log/edit/approve donations, resend receipts), or 'admin' (full control, including
     * Settings and managing other coordinators for that event). Existing rows default to
     * 'admin' so nobody's access silently narrows the moment this column appears — every
     * coordinator assigned before this feature existed had full control of their event.
     */
    public function up(): void
    {
        Schema::table('event_coordinators', function (Blueprint $table) {
            if (!Schema::hasColumn('event_coordinators', 'level')) {
                $table->string('level', 20)->default('admin')->after('event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_coordinators', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
