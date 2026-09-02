<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

/**
 * Resolves a donation row stuck as Stripe 'Pending' or 'Cancelled' against Stripe's real
 * Checkout Session status. A donation starts 'Pending' the instant the session is created,
 * before the donor has paid; if they complete payment the webhook/success page flips it to
 * 'Paid', and if they click back on Stripe's page stripeCancel() flips it to 'Cancelled'. A
 * donor who just abandons the tab triggers neither, so the row can sit wrong forever unless
 * someone checks Stripe directly — that's what this does, on demand from the admin panel's
 * "Check Status" button and from the donations:reconcile-stripe-pending command.
 */
class StripeReconciliationService
{
    public static function reconcile(string $table, object $row, bool $dryRun = false): array
    {
        $transactionId = (string) $row->transaction_id;

        if ($row->payment_method !== 'Stripe' || !$transactionId || !str_starts_with($transactionId, 'cs_')) {
            return ['outcome' => 'skipped', 'message' => 'This donation has no Stripe Checkout session to check.'];
        }

        // The session's own id says which mode it was created under — a row can predate the
        // account's *current* stripe_mode setting, so the active mode's key is not reliable
        // here; Stripe rejects a test-mode session id under a live key (and vice versa) with
        // a generic "No such checkout.session" error that looks identical to "truly gone".
        $secret = str_starts_with($transactionId, 'cs_test_')
            ? config('services.stripe.test_secret')
            : config('services.stripe.live_secret');

        $stripe = new StripeClient($secret);

        try {
            $session = $stripe->checkout->sessions->retrieve($row->transaction_id);
        } catch (\Exception $e) {
            return ['outcome' => 'error', 'message' => 'Could not reach Stripe: ' . $e->getMessage()];
        }

        if ($session->status === 'complete' && $session->payment_status === 'paid') {
            if (!$dryRun) {
                DB::table($table)->where('id', $row->id)->update(['payment_status' => 'Paid', 'updated_at' => now()]);
                DonationReceiptService::send(DonationReceiptService::payloadForRow($table, $row));
            }

            return [
                'outcome' => 'paid',
                'message' => 'Stripe confirms this was actually paid' . ($dryRun ? ' (dry run — not updated).' : ' — marked as Paid and the receipt has been sent.'),
            ];
        }

        if ($session->status === 'expired') {
            if (!$dryRun) {
                DB::table($table)->where('id', $row->id)->update(['payment_status' => 'Cancelled', 'updated_at' => now()]);
            }

            return [
                'outcome' => 'cancelled',
                'message' => 'The Checkout session expired on Stripe without payment' . ($dryRun ? ' (dry run — not updated).' : ' — marked as Cancelled.'),
            ];
        }

        return [
            'outcome' => 'still_open',
            'message' => "Still open on Stripe (status: {$session->status}) — the donor may still complete it, no change made.",
        ];
    }
}
