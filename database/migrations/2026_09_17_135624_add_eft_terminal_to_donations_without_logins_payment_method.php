<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * donations_without_logins.payment_method is a strict MySQL ENUM ('Bank', 'UPI', 'Cash',
     * 'Stripe') — the app-level validation accepting a new method (EFT Terminal) isn't enough
     * on its own, the column itself rejects any value outside its enum list.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal') NOT NULL");
        } else {
            Schema::table('donations_without_logins', function (Blueprint $table) {
                $table->string('payment_method')->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe') NOT NULL");
        }
    }
};
