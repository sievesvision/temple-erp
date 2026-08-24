<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash') NOT NULL");
        } else {
            Schema::table('donations_without_logins', function (Blueprint $table) {
                $table->string('payment_method')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI') NOT NULL");
        }
    }
};
