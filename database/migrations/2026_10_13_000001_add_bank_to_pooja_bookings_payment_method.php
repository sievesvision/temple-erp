<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pooja_bookings.payment_method was ENUM('UPI','Razorpay','Cash','Counter') — no value
     * for "Bank Transfer", which the Pooja Bookings plan's public booking page (Phase C)
     * and booking-confirmation email (Phase B) both offer as a payment option alongside
     * Cash. Without this, BookingService::create() throws a truncation error on MySQL the
     * moment anyone picks Bank Transfer (sqlite, used by the test suite, doesn't enforce
     * the enum at all, which is why this wasn't caught by tests). 'Card' isn't added
     * separately — "pay at temple" already covers cash or card in person under 'Cash',
     * matching how StaffController's counter booking already uses it.
     */
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN payment_method ENUM('UPI', 'Razorpay', 'Cash', 'Counter', 'Bank') NULL");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE pooja_bookings MODIFY COLUMN payment_method ENUM('UPI', 'Razorpay', 'Cash', 'Counter') NULL");
        }
    }
};
