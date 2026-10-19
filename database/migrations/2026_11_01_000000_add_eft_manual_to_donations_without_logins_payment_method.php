<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * donations_without_logins.payment_method was ENUM('Bank','UPI','Cash','Stripe','EFT
 * Terminal') — no value for 'EFT Manual', the portal's own Add Transaction modal's new
 * option for recording a terminal-card payment that was taken on the physical device but
 * never went through the app's own real-time EFT Terminal charge flow (so there's no
 * sci_transactions/linkly_transactions row to link it to) — distinct from 'EFT Terminal',
 * which stays reserved for a payment this app itself drove end to end.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal', 'EFT Manual') NOT NULL");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE donations_without_logins MODIFY COLUMN payment_method ENUM('Bank', 'UPI', 'Cash', 'Stripe', 'EFT Terminal') NOT NULL");
        }
    }
};
