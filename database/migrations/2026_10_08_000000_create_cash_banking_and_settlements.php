<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash reconciliation: a Cash donation/ticket sale accumulates as "cash on hand" until an
 * admin physically deposits it and logs that here (cash_bankings — always a single lump
 * sum, never split across donation options/ticket types, matching how cash is actually
 * taken to the bank in one trip). Running a Settlement (cash_settlements) locks in a
 * point-in-time snapshot of opening/received/banked/closing balances so the next
 * settlement's opening balance is simply this one's closing balance, rather than needing
 * to be recomputed from the full transaction history every time.
 *
 * Two independent scopes: one event's donations, or the ticket system as a whole (tickets
 * aren't tied to any one event, so their settlement is global — event_id stays null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bankings', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['event', 'tickets']);
            $table->foreignId('event_id')->nullable()->constrained('events', 'event_id')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('banked_date');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'event_id']);
        });

        Schema::create('cash_settlements', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['event', 'tickets']);
            $table->foreignId('event_id')->nullable()->constrained('events', 'event_id')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 10, 2);
            $table->decimal('cash_received', 10, 2);
            $table->decimal('amount_banked', 10, 2);
            $table->decimal('closing_balance', 10, 2);
            // Per donation-option (event scope) or per ticket-type (tickets scope) cash
            // figures, for visibility only — never subtracted from banking, since a banking
            // deposit is never split by category. Shape: [{label, cash_received,
            // cumulative_received}, ...].
            $table->json('breakdown');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_settlements');
        Schema::dropIfExists('cash_bankings');
    }
};
