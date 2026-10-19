<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial Pledge Payments, Phase A (foundation) — see the approved plan at
 * C:\Users\User\.claude\plans\jazzy-knitting-galaxy.md for the full architecture.
 *
 * donation_payments becomes the ledger of truth for every money-movement event against a
 * donation/pledge, old and new: one row per actual payment (amount, method, status,
 * transaction reference, timestamp), independent of how many payments a donation ends up
 * having. donations/donations_without_logins keep meaning "the pledge" — amount stays the
 * original pledged total, unchanged — and gain one new donation_status column tracking the
 * pledge's own lifecycle (Pending/Partially Paid/Completed/Cancelled) separately from each
 * payment's own payment_status (Pending/Completed/Failed/Cancelled/Refunded).
 *
 * donation_type/donation_id is the same loose, non-FK polymorphic pair sci_transactions/
 * linkly_transactions already use (confirmed via those tables' own migrations) — not a real
 * foreign key, by the same existing convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_payments', function (Blueprint $table) {
            $table->id();
            $table->enum('donation_type', ['devotee', 'guest']);
            $table->unsignedBigInteger('donation_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('Pending');
            $table->string('transaction_id')->nullable();
            // Anchors this payment to the specific terminal charge that paid it, once one
            // exists — replaces the fragile donation_type:donation_id keyBy() the console/
            // refund flow use today (silently drops all but the last terminal row per
            // donation; see the plan's "EFT Terminal linkage fix" section).
            $table->unsignedBigInteger('sci_transaction_id')->nullable();
            $table->unsignedBigInteger('linkly_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['donation_type', 'donation_id']);
        });

        // Default 'Pending' (no confirmed payment) is the correct neutral value for any
        // row inserted without setting this column explicitly — the backfill below then
        // corrects every existing row to its real status.
        // Deliberately no ->after(...): on sqlite (tests) that forces Laravel to rebuild
        // the whole table via its own internal transaction, which conflicts with the
        // migration's own automatic transaction wrapper ("already an active transaction").
        // Column order has no functional effect, so it isn't worth the sqlite fragility.
        Schema::table('donations', function (Blueprint $table) {
            $table->string('donation_status')->default('Pending');
        });
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->string('donation_status')->default('Pending');
        });

        Schema::table('sci_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('donation_payment_id')->nullable();
        });
        Schema::table('linkly_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('donation_payment_id')->nullable();
        });

        $this->backfill();
    }

    /**
     * One donation_payments row per existing non-Pledge donation, so the ledger is complete
     * for 100% of historical data from day one — no "old vs new donation" special-casing
     * anywhere downstream. A Pledge (payment_status='Pledge', zero money yet) correctly gets
     * no payment row and donation_status='Pending'.
     */
    private function backfill(): void
    {
        $sciByDonation = DB::table('sci_transactions')
            ->where('txn_type', 'purchase')
            ->whereNotNull('donation_id')
            ->get()
            ->keyBy(fn ($t) => $t->donation_type . ':' . $t->donation_id);
        $linklyByDonation = DB::table('linkly_transactions')
            ->where('txn_type', 'purchase')
            ->whereNotNull('donation_id')
            ->get()
            ->keyBy(fn ($t) => $t->donation_type . ':' . $t->donation_id);

        foreach (['devotee' => 'donations', 'guest' => 'donations_without_logins'] as $donationType => $table) {
            DB::table($table)->orderBy('id')->get()->each(function ($row) use ($donationType, $table, $sciByDonation, $linklyByDonation) {
                $donationStatus = match ($row->payment_status) {
                    'Pledge' => 'Pending',
                    'Paid' => 'Completed',
                    'Cancelled' => 'Cancelled',
                    default => 'Pending',
                };

                DB::table($table)->where('id', $row->id)->update(['donation_status' => $donationStatus]);

                if ($row->payment_status === 'Pledge') {
                    return;
                }

                $paymentStatus = match ($row->payment_status) {
                    'Paid' => 'Completed',
                    default => $row->payment_status,
                };

                $key = $donationType . ':' . $row->id;
                $sci = $sciByDonation->get($key);
                $linkly = $linklyByDonation->get($key);

                $paymentId = DB::table('donation_payments')->insertGetId([
                    'donation_type' => $donationType,
                    'donation_id' => $row->id,
                    'amount' => $row->amount,
                    'payment_method' => $row->payment_method,
                    'payment_status' => $paymentStatus,
                    'transaction_id' => $row->transaction_id,
                    'sci_transaction_id' => $sci->id ?? null,
                    'linkly_transaction_id' => $linkly->id ?? null,
                    'received_at' => $row->created_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                if ($sci) {
                    DB::table('sci_transactions')->where('id', $sci->id)->update(['donation_payment_id' => $paymentId]);
                }
                if ($linkly) {
                    DB::table('linkly_transactions')->where('id', $linkly->id)->update(['donation_payment_id' => $paymentId]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('linkly_transactions', function (Blueprint $table) {
            $table->dropColumn('donation_payment_id');
        });
        Schema::table('sci_transactions', function (Blueprint $table) {
            $table->dropColumn('donation_payment_id');
        });
        Schema::table('donations_without_logins', function (Blueprint $table) {
            $table->dropColumn('donation_status');
        });
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('donation_status');
        });
        Schema::dropIfExists('donation_payments');
    }
};
