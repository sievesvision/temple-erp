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
        if ($row->payment_method !== 'Stripe' || !$row->transaction_id || !str_starts_with((string) $row->transaction_id, 'cs_')) {
            return ['outcome' => 'skipped', 'message' => 'This donation has no Stripe Checkout session to check.'];
        }

        $stripe = new StripeClient(StripeConfigService::secret());

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
