<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A JSON list of payment methods enabled for this specific event (Cash/UPI/Bank
     * Transfer/Cheque/Stripe), letting an event override the global donation settings —
     * e.g. disabling Stripe for one event even though it's globally enabled. Null means
     * "inherit whatever is configured globally" (the pre-existing behaviour).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'enabled_payment_methods')) {
                $table->text('enabled_payment_methods')->nullable()->after('gallery_images');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('enabled_payment_methods');
        });
    }
};
