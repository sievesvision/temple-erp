<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors linkly_transactions.original_transaction_id — a refund row points back to the
 * purchase it's refunding, purely for our own bookkeeping (duplicate-refund prevention,
 * linking the donation's payment_status back to its refund). mx51's own Create Transaction -
 * Refund endpoint takes no such reference at all (confirmed against their Postman
 * collection — refund_details carries only refund_amount): SCI has no server-side concept of
 * "this refund belongs to that purchase", so this is entirely our own audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sci_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('sci_transactions', 'original_transaction_id')) {
                $table->unsignedBigInteger('original_transaction_id')->nullable()->after('donation_id');
                $table->foreign('original_transaction_id')->references('id')->on('sci_transactions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sci_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('sci_transactions', 'original_transaction_id')) {
                $table->dropForeign(['original_transaction_id']);
                $table->dropColumn('original_transaction_id');
            }
        });
    }
};
