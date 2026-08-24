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
        Schema::table('donations', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('devotee_id');
            $table->text('remarks')->nullable()->after('payment_status');
            $table->foreign('event_id')->references('event_id')->on('events')->nullOnDelete();
        });

        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('id');
            $table->foreign('event_id')->references('event_id')->on('events')->nullOnDelete();
        });

        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe') NOT NULL");
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
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn(['event_id', 'remarks']);
        });

        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });

        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash') NOT NULL");
        }
    }
};
