<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partial Pledge Payments: DonationPaymentService::recomputeParent() mirrors
 * payment_method='Split' onto the parent row once a pledge has 2+ distinct payment
 * methods recorded against it — missed when donations_without_logins.payment_method's
 * strict MySQL ENUM was last widened (Pledge/EFT Manual migrations). Without this, a
 * second installment paid by a different method than the first throws "Data truncated
 * for column 'payment_method'" and the whole payment is rolled back.
 * donations.payment_method is plain varchar — no equivalent migration needed there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal', 'EFT Manual', 'Split') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("UPDATE donations_without_logins SET payment_method = 'Cash' WHERE payment_method = 'Split'");
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal', 'EFT Manual') NULL DEFAULT NULL");
        }
    }
};
