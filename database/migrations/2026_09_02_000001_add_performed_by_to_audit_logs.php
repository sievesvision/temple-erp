<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AuditLogService has inserted 'performed_by' and 'ip_address' since it was written, but the
 * audit_logs table (created back in 2026_06_21_000000_create_core_domain_tables) only ever
 * had 'user_id' — every audit log call has been throwing "Unknown column 'performed_by'"
 * and, wherever that call wasn't wrapped in its own try/catch, taking the whole request down
 * with it (e.g. adding a Committee member). This brings the schema in line with the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('performed_by')->nullable()->after('action');
            $table->string('ip_address', 45)->nullable()->after('performed_by');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['performed_by', 'ip_address']);
        });
    }
};
