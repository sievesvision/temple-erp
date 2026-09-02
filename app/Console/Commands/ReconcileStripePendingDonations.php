<?php

namespace App\Console\Commands;

use App\Services\StripeReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Batch version of the "Check Status" admin button — checks every donation still stuck as
 * Stripe/Pending across both tables against Stripe's real Checkout Session status and
 * resolves it. See StripeReconciliationService for how each row is actually resolved.
 * Run manually (no recurring schedule is configured for this):
 *   php artisan donations:reconcile-stripe-pending [--dry-run]
 */
class ReconcileStripePendingDonations extends Command
{
    protected $signature = 'donations:reconcile-stripe-pending {--dry-run : Show what would change without updating anything}';

    protected $description = 'Resolve donations stuck as Stripe/Pending by checking their real Checkout Session status';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (['donations', 'donations_without_logins'] as $table) {
            $rows = DB::table($table)
                ->where('payment_method', 'Stripe')
                ->where('payment_status', 'Pending')
                ->whereNotNull('transaction_id')
                ->where('transaction_id', 'like', 'cs\_%')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $this->info("Checking {$rows->count()} pending Stripe row(s) in {$table}...");

            foreach ($rows as $row) {
                $result = StripeReconciliationService::reconcile($table, $row, $dryRun);
                $this->line("  #{$row->id}: {$result['message']}");
            }
        }

        if ($dryRun) {
            $this->comment('Dry run — no records were changed.');
        }

        return self::SUCCESS;
    }
}
