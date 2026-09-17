<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Linkly Core Payments accreditation ledger — every Purchase, Refund and Logon this app
 * sends to Linkly Cloud, kept separate from the donations/donations_without_logins tables
 * (which only ever hold the resulting money record, not the payment-gateway trail). This is
 * what makes a transaction reference "locatable" for accreditation evidence (Linkly's own
 * Core Payments test script asks for a reference + timestamp per test), what a refund is
 * matched back to (original_transaction_id + the original pos_txn_ref sent as Linkly's PAD
 * "RFN" tag), and what prevents double-charging: client_ref is a browser-generated
 * idempotency key so a page refresh or a double-clicked Pay button reuses the same in-flight
 * Linkly session instead of starting a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkly_transactions', function (Blueprint $table) {
            $table->id();
            // Our own generated reference sent to Linkly as TxnRef — distinct from Linkly's
            // own session id (a Cloud routing concept, not a merchant transaction reference).
            $table->string('pos_txn_ref')->unique();
            // Browser-generated idempotency key (one per Pay-button click attempt), so a
            // refresh/retry with the same key resumes the existing session instead of
            // starting a new Linkly transaction. Null for refunds/logons, which are always
            // explicit single admin-initiated actions with no "did this already start?"
            // ambiguity to protect against.
            $table->string('client_ref')->nullable()->index();
            $table->string('linkly_session_id')->nullable()->index();
            $table->enum('txn_type', ['purchase', 'refund', 'logon']);
            $table->unsignedBigInteger('event_id')->nullable();
            // Which of the two donation-record tables (and which row) this transaction is
            // for, once that row exists — null for a refund/logon, and briefly null for a
            // purchase between "session started" and "Linkly approved it".
            $table->string('donation_type', 20)->nullable();
            $table->unsignedBigInteger('donation_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('status', 20)->default('initiated');
            $table->string('response_code', 10)->nullable();
            $table->string('response_text')->nullable();
            $table->string('auth_code', 20)->nullable();
            $table->string('rrn', 20)->nullable();
            // A refund row's link back to the purchase it refunds; never the other direction.
            $table->unsignedBigInteger('original_transaction_id')->nullable();
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->unsignedBigInteger('authorised_by')->nullable();
            // Non-sensitive extras only (e.g. last display text, card type) — never PAN/PIN/
            // track data. See LinklyEftService's own doc-comments for what is deliberately
            // never persisted.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('event_id')->on('events')->nullOnDelete();
            $table->foreign('original_transaction_id')->references('id')->on('linkly_transactions')->nullOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('authorised_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkly_transactions');
    }
};
