<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds the "system_notification_email" setting — every outgoing email is BCC'd to this
     * address (see AppServiceProvider's MessageSending listener) so admins can track what's
     * been sent. Defaults to admin@hasq.org.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'system_notification_email',
            'value' => 'admin@hasq.org',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
