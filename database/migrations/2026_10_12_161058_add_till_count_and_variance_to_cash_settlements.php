<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Till-count reconciliation: till_count is what the operator physically counts in the
 * till at settlement time; variance is till_count minus the expected balance (opening +
 * cash_received); variance_note explains it when it's non-zero. closing_balance's meaning
 * changes from here on (see CashSettlementService::run()) — it's now till_count minus
 * amount_banked (what's actually left, by count), not the purely computed opening +
 * received - banked figure, so a shortage/overage never silently compounds into the next
 * period. Nullable throughout: existing settlements predate this and keep their old
 * computed closing_balance untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_settlements', function (Blueprint $table) {
            $table->decimal('till_count', 10, 2)->nullable()->after('amount_banked');
            $table->decimal('variance', 10, 2)->nullable()->after('closing_balance');
            $table->text('variance_note')->nullable()->after('variance');
        });
    }

    public function down(): void
    {
        Schema::table('cash_settlements', function (Blueprint $table) {
            $table->dropColumn(['till_count', 'variance', 'variance_note']);
        });
    }
};
