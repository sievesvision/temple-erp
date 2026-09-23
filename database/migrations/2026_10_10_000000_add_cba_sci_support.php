<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second card-payment provider — CBA Smart Terminal, via mx51's "Simple Cloud
 * Integration" (SCI) API — selectable per terminal in the same eft_terminals registry
 * built for Linkly. 'provider' distinguishes which protocol a given terminal speaks;
 * existing rows default to 'linkly' so nothing already paired is affected. The sci_*
 * columns hold exactly what mx51's pairing response returns (see CbaSciService::pair()) —
 * sci_signing_secret_part_b is the one genuinely secret value here (the merchant-held
 * Signing Secret Part A never touches the database at all, see config/services.php) and
 * gets Eloquent's 'encrypted' cast on the model, the first field-level encryption in this
 * codebase.
 *
 * sci_transactions is deliberately a separate table from linkly_transactions rather than a
 * bolted-on provider column there: SCI's lifecycle (PENDING/AWAITING_POS/FINALISED plus a
 * separate result_financial_status, and a pos_instructions Action Framework JSON blob
 * driving dynamic POS UI) has no Linkly analogue, so sharing one table would mean a wide
 * half-unused column set and an awkward status-vocabulary mapping either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eft_terminals', function (Blueprint $table) {
            if (!Schema::hasColumn('eft_terminals', 'provider')) {
                $table->string('provider', 20)->default('linkly')->after('label');
            }
            if (!Schema::hasColumn('eft_terminals', 'sci_pairing_id')) {
                $table->string('sci_pairing_id')->nullable();
                $table->string('sci_key_id')->nullable();
                $table->text('sci_signing_secret_part_b')->nullable();
                $table->string('sci_api_base_url')->nullable();
                $table->string('sci_confirmation_code')->nullable();
                $table->string('sci_tid')->nullable();
                $table->string('sci_pairing_nickname')->nullable();
                $table->string('sci_terminal_nickname')->nullable();
                $table->timestamp('sci_paired_at')->nullable();
            }
        });

        Schema::create('sci_transactions', function (Blueprint $table) {
            $table->id();
            // Our own idempotency reference, generated client-side before the first
            // createPurchase() call — mirrors linkly_transactions.client_ref's role.
            $table->string('client_ref')->unique();
            // mx51's own transaction id, assigned once createPurchase() succeeds.
            $table->string('sci_transaction_id')->nullable()->index();
            // The last-seen `data.version` from a poll response — the next poll's
            // min_version, per mx51's "never self-increment" rule (see CbaSciService::pollTransaction()).
            $table->unsignedInteger('sci_version')->default(0);
            $table->foreignId('event_id')->nullable()->constrained('events', 'event_id')->nullOnDelete();
            $table->foreignId('eft_terminal_id')->nullable()->constrained('eft_terminals')->nullOnDelete();
            $table->enum('donation_type', ['devotee', 'guest'])->nullable();
            $table->unsignedBigInteger('donation_id')->nullable();
            $table->enum('txn_type', ['purchase', 'refund'])->default('purchase');
            $table->decimal('amount', 10, 2);
            $table->string('currency_code', 10)->default('AUD');
            $table->string('status', 20)->default('PENDING');
            $table->string('result_financial_status', 20)->nullable();
            $table->json('result_amounts')->nullable();
            $table->json('result_card_details')->nullable();
            // The most recent Action Framework payload — kept so a page refresh mid-
            // transaction can redraw the same prompt instead of losing it entirely.
            $table->json('pos_instructions')->nullable();
            $table->string('message')->nullable();
            $table->text('merchant_receipt')->nullable();
            $table->text('customer_receipt')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sci_transactions');
        Schema::table('eft_terminals', function (Blueprint $table) {
            if (Schema::hasColumn('eft_terminals', 'sci_pairing_id')) {
                $table->dropColumn([
                    'sci_pairing_id', 'sci_key_id', 'sci_signing_secret_part_b', 'sci_api_base_url',
                    'sci_confirmation_code', 'sci_tid', 'sci_pairing_nickname', 'sci_terminal_nickname',
                    'sci_paired_at',
                ]);
            }
            if (Schema::hasColumn('eft_terminals', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
