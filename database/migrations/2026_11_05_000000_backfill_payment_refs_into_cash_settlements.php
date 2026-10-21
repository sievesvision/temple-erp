<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partial Pledge Payments, Phase E: CashSettlementService::eventCashBreakdown()/
 * paymentMethodBreakdown() now explode a donation into its real donation_payments rows
 * and exclude anything already claimed at the PAYMENT level ({type:'payment', id}) —
 * a new, separate granularity from the existing donation-level claiming. Without this
 * backfill, the very first settlement run after this phase ships would re-count every
 * historical Cash (and other-method) amount that a past settlement already claimed at
 * the donation level and that has already been physically banked.
 *
 * For every existing event-scope settlement, walk its transaction_refs and add a
 * {type:'payment', id} ref for every donation_payments row belonging to a devotee/guest
 * donation already claimed there. Idempotent — a settlement that already has a
 * 'payment'-type ref (run after this phase shipped) is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cash_settlements')->where('scope', 'event')->orderBy('id')->get(['id', 'transaction_refs'])
            ->each(function ($settlement) {
                $refs = json_decode($settlement->transaction_refs ?? '[]', true) ?? [];

                if (collect($refs)->contains(fn ($r) => ($r['type'] ?? null) === 'payment')) {
                    return;
                }

                $added = false;
                foreach ($refs as $ref) {
                    if (!in_array($ref['type'] ?? null, ['devotee', 'guest'], true)) {
                        continue;
                    }
                    $payments = DB::table('donation_payments')
                        ->where('donation_type', $ref['type'])
                        ->where('donation_id', $ref['id'])
                        ->get(['id']);
                    foreach ($payments as $payment) {
                        $refs[] = ['type' => 'payment', 'id' => $payment->id];
                        $added = true;
                    }
                }

                if ($added) {
                    DB::table('cash_settlements')->where('id', $settlement->id)->update(['transaction_refs' => json_encode($refs)]);
                }
            });
    }

    public function down(): void
    {
        // Deliberately irreversible — telling apart a payment-ref this migration added
        // from one a real settlement validly added afterwards isn't worth tracking just
        // to support a rollback. Re-running up() after any rollback attempt is safely
        // idempotent on its own (it skips any settlement that already has one).
    }
};
