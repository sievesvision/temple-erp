<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds the "stripe_mode" setting (test|live) that decides which key pair
     * StripeConfigService resolves — defaults to 'test' so no environment is
     * silently switched to live charges by this migration running.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'stripe_mode',
            'value' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
