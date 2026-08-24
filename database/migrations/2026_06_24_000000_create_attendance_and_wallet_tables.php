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
        // 1. Add wallet_balance to staff table if it doesn't exist
        if (!Schema::hasColumn('staff', 'wallet_balance')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->decimal('wallet_balance', 10, 2)->default(0.00)->after('branch_name');
            });
        }

        // 2. Modify booking_status column of pooja_bookings table to support 'Assigned' and 'In Progress'
        // Using DB statement for safe enum modification in MySQL
        try {
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN booking_status ENUM('Pending', 'Confirmed', 'Assigned', 'In Progress', 'Completed', 'Cancelled') NULL DEFAULT 'Pending'");
        } catch (\Exception $e) {
            // Fallback for sqlite or testing database
            // (No-op or custom handling if needed, SQLite does not enforce ENUM strings natively like MySQL)
        }

        // 3. Create staff_attendance table
        if (!Schema::hasTable('staff_attendance')) {
            Schema::create('staff_attendance', function (Blueprint $table) {
                $table->id('attendance_id');
                $table->unsignedBigInteger('staff_id');
                $table->date('attendance_date');
                $table->enum('attendance_status', ['Present', 'Absent', 'Leave', 'Half Day'])->default('Present');
                $table->time('check_in_time')->nullable();
                $table->time('check_out_time')->nullable();
                $table->decimal('worked_hours', 4, 2)->default(0.00);
                $table->boolean('eligible_for_full_day')->default(0);
                $table->string('remarks')->nullable();
                $table->timestamps();

                $table->foreign('staff_id')->references('staff_id')->on('staff')->onDelete('cascade');
            });
        }

        // 4. Create staff_wallet_transactions table
        if (!Schema::hasTable('staff_wallet_transactions')) {
            Schema::create('staff_wallet_transactions', function (Blueprint $table) {
                $table->id('transaction_id');
                $table->unsignedBigInteger('staff_id');
                $table->decimal('amount', 10, 2);
                $table->enum('transaction_type', ['Credit', 'Debit'])->default('Credit');
                $table->string('remarks')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('staff_id')->references('staff_id')->on('staff')->onDelete('cascade');
            });
        }

        // 5. Create unified penalties table
        if (!Schema::hasTable('penalties')) {
            Schema::create('penalties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('date');
                $table->decimal('missing_hours', 4, 2);
                $table->decimal('penalty_amount', 10, 2);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // 6. Seed hourly penalty setting
        DB::table('settings')->updateOrInsert(
            ['key' => 'hourly_penalty_amount'],
            ['value' => '100.00', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalties');
        Schema::dropIfExists('staff_wallet_transactions');
        Schema::dropIfExists('staff_attendance');

        if (Schema::hasColumn('staff', 'wallet_balance')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }

        try {
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN booking_status ENUM('Pending', 'Confirmed', 'Completed', 'Cancelled') NULL DEFAULT 'Pending'");
        } catch (\Exception $e) {
            // SQLite fallback
        }

        DB::table('settings')->where('key', 'hourly_penalty_amount')->delete();
    }
};
