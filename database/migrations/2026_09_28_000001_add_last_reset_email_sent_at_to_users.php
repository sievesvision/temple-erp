<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When an admin (or an event-admin coordinator, for their own event's coordinators) last
     * triggered a password reset email for this account — shown on the account management
     * pages so it's clear whether/when a reset link was actually sent.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'last_reset_email_sent_at')) {
                $table->timestamp('last_reset_email_sent_at')->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_reset_email_sent_at');
        });
    }
};
