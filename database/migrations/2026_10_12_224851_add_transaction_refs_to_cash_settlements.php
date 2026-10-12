<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each settlement remember exactly which transactions (and which CashBanking deposit
 * row, if any) it claimed, by id — see CashSettlementService's own docblock on why: a plain
 * `timestamp` column only has one-second resolution, not enough to reliably order two
 * settlements run the same day, or a transaction recorded moments after one was finalized.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_settlements', function (Blueprint $table) {
            $table->json('transaction_refs')->nullable()->after('breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('cash_settlements', function (Blueprint $table) {
            $table->dropColumn('transaction_refs');
        });
    }
};
