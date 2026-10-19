<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Pledge has no payment method at all yet — see the sibling migrations on this same
     * column (add_eft_terminal_.../add_eft_manual_...) for why MySQL needs its own ALTER
     * (strict ENUM) while sqlite (tests) just needs the plain string column marked nullable.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal', 'EFT Manual') NULL DEFAULT NULL");
        } else {
            Schema::table('donations_without_logins', function (Blueprint $table) {
                $table->string('payment_method')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("UPDATE donations_without_logins SET payment_method = 'Cash' WHERE payment_method IS NULL");
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal', 'EFT Manual') NOT NULL");
        } else {
            Schema::table('donations_without_logins', function (Blueprint $table) {
                $table->string('payment_method')->nullable(false)->change();
            });
        }
    }
};
