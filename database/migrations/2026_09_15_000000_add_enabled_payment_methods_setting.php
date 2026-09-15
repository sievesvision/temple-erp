<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds the "enabled_payment_methods" setting (JSON array) that controls which payment
     * methods appear in the "Log Devotee/Guest Donation" forms — UPI is excluded by default,
     * only Cash/Bank Transfer/Cheque show up out of the box.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'enabled_payment_methods',
            'value' => json_encode(['Cash', 'Bank Transfer', 'Cheque']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
