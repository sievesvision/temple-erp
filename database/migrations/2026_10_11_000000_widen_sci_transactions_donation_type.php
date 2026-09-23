<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sci_transactions.donation_type was mistakenly created as enum('devotee','guest') — but
 * linkly_transactions' own equivalent column (a plain string) already proves a third value
 * is needed: 'ticket_order', for an SCI transaction that pays for a ticket sale rather than
 * a donation (see DonationController::createDonationIfApprovedPurchaseUnrecorded()'s own
 * 'ticket_order' donation_type). Widened to match that column's actual shape (a plain
 * nullable string, not a closed enum) before Phase 2 ever writes to it — the table has no
 * rows in production yet, so a drop-and-recreate is safe and avoids needing doctrine/dbal
 * (required for Schema::table()->change(), not otherwise a dependency of this project).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sci_transactions', function (Blueprint $table) {
            $table->dropColumn('donation_type');
        });

        Schema::table('sci_transactions', function (Blueprint $table) {
            $table->string('donation_type', 20)->nullable()->after('sci_version');
        });
    }

    public function down(): void
    {
        Schema::table('sci_transactions', function (Blueprint $table) {
            $table->dropColumn('donation_type');
        });

        Schema::table('sci_transactions', function (Blueprint $table) {
            $table->enum('donation_type', ['devotee', 'guest'])->nullable()->after('sci_version');
        });
    }
};
