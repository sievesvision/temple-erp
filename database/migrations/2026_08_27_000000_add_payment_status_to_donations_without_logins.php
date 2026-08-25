<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('donations_without_logins', function (Blueprint $table) {
            // Existing Bank/Cash donations were always treated as settled the moment they were
            // recorded, so default to 'Paid' to preserve that behaviour. Only the new Stripe
            // checkout flow uses 'Pending' while the donor is on Stripe's page, then flips to
            // 'Paid' (success) or 'Cancelled'/'Failed' once the outcome is known.
            $table->string('payment_status')->default('Paid')->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
