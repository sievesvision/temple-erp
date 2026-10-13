<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pooja_bookings.payment_method was ENUM('UPI', 'Razorpay', 'Cash', 'Counter', 'Bank')
 * (see 2026_10_13_000001_add_bank_to_pooja_bookings_payment_method.php, which fixed the
 * same gap for 'Bank') — no value for 'EFT Terminal', which a booking's new terminal-card
 * payment option (Ticket POS, same EFT Terminal flow tickets already use) needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN payment_method ENUM('UPI', 'Razorpay', 'Cash', 'Counter', 'Bank', 'EFT Terminal') NULL");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN payment_method ENUM('UPI', 'Razorpay', 'Cash', 'Counter', 'Bank') NULL");
        }
    }
};
