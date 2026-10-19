<?php

namespace App\Services;

use App\Models\DonationPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Partial Pledge Payments — the one shared path every entry point (portal Add Payment,
 * POS partial-payment completion, EFT Terminal/Linkly approval handlers) calls to record a
 * payment against a donation/pledge, so every path can never drift (same reasoning as
 * DonationController::insertGuestDonationRecord()). See the approved plan at
 * C:\Users\User\.claude\plans\jazzy-knitting-galaxy.md for the full architecture.
 *
 * donation_type is always 'devotee' (table: donations) or 'guest' (table: donations_without_logins).
 */
class DonationPaymentService
{
    private static function table(string $donationType): string
    {
        return $donationType === 'devotee' ? 'donations' : 'donations_without_logins';
    }

    /**
     * Sum of only Completed payments — per spec, a pending bank transfer or unverified
     * EFTPOS transaction never counts as received funds.
     */
    public static function totalPaid(string $donationType, int $donationId): float
    {
        return (float) DonationPayment::where('donation_type', $donationType)
            ->where('donation_id', $donationId)
            ->where('payment_status', 'Completed')
            ->sum('amount');
    }

    public static function outstandingBalance(string $donationType, int $donationId): float
    {
        $donation = DB::table(self::table($donationType))->where('id', $donationId)->first();
        if (!$donation) {
            throw new RuntimeException("Donation {$donationType}:{$donationId} not found.");
        }

        return round((float) $donation->amount - self::totalPaid($donationType, $donationId), 2);
    }

    public static function paymentHistory(string $donationType, int $donationId): Collection
    {
        return DonationPayment::where('donation_type', $donationType)
            ->where('donation_id', $donationId)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Records one payment event against a pledge, inside a locked transaction so two
     * counters recording a payment for the same pledge at once can never both succeed past
     * the outstanding balance (test scenario: "two counters processing the same pledge
     * simultaneously").
     *
     * $fields: amount (required), payment_method (nullable), payment_status (default
     * 'Pending'), transaction_id, sci_transaction_id, linkly_transaction_id, notes,
     * received_at, created_by, idempotency_key (optional — if a payment with this exact
     * transaction_id already exists for this donation, that row is returned unchanged
     * rather than inserting a duplicate).
     */
    public static function recordPayment(string $donationType, int $donationId, array $fields): DonationPayment
    {
        return DB::transaction(function () use ($donationType, $donationId, $fields) {
            $table = self::table($donationType);
            $donation = DB::table($table)->where('id', $donationId)->lockForUpdate()->first();
            if (!$donation) {
                throw new RuntimeException("Donation {$donationType}:{$donationId} not found.");
            }
            if ($donation->donation_status === 'Cancelled') {
                throw new RuntimeException('This pledge has been cancelled — no further payments can be recorded against it.');
            }

            $idempotencyKey = $fields['idempotency_key'] ?? ($fields['transaction_id'] ?? null);
            if ($idempotencyKey) {
                $existing = DonationPayment::where('donation_type', $donationType)
                    ->where('donation_id', $donationId)
                    ->where('transaction_id', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $amount = round((float) $fields['amount'], 2);
            if ($amount <= 0) {
                throw new RuntimeException('Payment amount must be greater than zero.');
            }

            $outstanding = round((float) $donation->amount - self::totalPaid($donationType, $donationId), 2);
            if ($amount > $outstanding + 0.004) {
                throw new RuntimeException("Payment of {$amount} exceeds the outstanding balance of {$outstanding}.");
            }

            $payment = DonationPayment::create([
                'donation_type' => $donationType,
                'donation_id' => $donationId,
                'amount' => $amount,
                'payment_method' => $fields['payment_method'] ?? null,
                'payment_status' => $fields['payment_status'] ?? 'Pending',
                'transaction_id' => $fields['transaction_id'] ?? null,
                'sci_transaction_id' => $fields['sci_transaction_id'] ?? null,
                'linkly_transaction_id' => $fields['linkly_transaction_id'] ?? null,
                'notes' => $fields['notes'] ?? null,
                'received_at' => $fields['received_at'] ?? now(),
                'created_by' => $fields['created_by'] ?? null,
            ]);

            self::recomputeParent($donationType, $donationId);

            return $payment;
        });
    }

    /**
     * Cancels/refunds one specific payment (e.g. a terminal refund) and recomputes the
     * parent pledge's status — unlike today's pre-fix behaviour, this never cancels the
     * whole pledge over one refunded installment.
     */
    public static function cancelPayment(DonationPayment $payment, string $newStatus = 'Cancelled'): DonationPayment
    {
        if (!in_array($newStatus, ['Cancelled', 'Refunded'], true)) {
            throw new RuntimeException("Invalid cancel status: {$newStatus}");
        }

        return DB::transaction(function () use ($payment, $newStatus) {
            DB::table(self::table($payment->donation_type))
                ->where('id', $payment->donation_id)
                ->lockForUpdate()
                ->first();

            $payment->payment_status = $newStatus;
            $payment->save();

            self::recomputeParent($payment->donation_type, $payment->donation_id);

            return $payment;
        });
    }

    /**
     * Recomputes and writes the parent donation's donation_status, plus the legacy
     * payment_method/payment_status mirror columns the ~45 existing display-only call sites
     * still read directly — see the plan's "backward-compatible additive design" section.
     */
    public static function recomputeParent(string $donationType, int $donationId): void
    {
        $table = self::table($donationType);
        $donation = DB::table($table)->where('id', $donationId)->first();
        if (!$donation) {
            return;
        }

        $payments = DonationPayment::where('donation_type', $donationType)
            ->where('donation_id', $donationId)
            ->get();

        if ($payments->isEmpty()) {
            // A pure Pledge — nothing to recompute, leave payment_method/status as the
            // Pledge feature already set them.
            return;
        }

        $totalPaid = (float) $payments->where('payment_status', 'Completed')->sum('amount');
        $pledged = (float) $donation->amount;

        if ($totalPaid <= 0) {
            $donationStatus = 'Pending';
        } elseif ($totalPaid >= $pledged - 0.004) {
            $donationStatus = 'Completed';
        } else {
            $donationStatus = 'Partially Paid';
        }

        $distinctMethods = $payments->pluck('payment_method')->filter()->unique();

        if ($payments->count() === 1) {
            $only = $payments->first();
            $method = $only->payment_method;
            if ($only->payment_status !== 'Completed') {
                // Pending/Failed/Cancelled/Refunded — mirror the literal status as-is, same
                // wording the ~45 existing call sites (e.g. Stripe's own Check Status button)
                // already expect.
                $status = $only->payment_status;
            } elseif ($donationStatus === 'Completed') {
                $status = 'Paid';
            } else {
                // Completed but didn't fully cover the pledge (a partial single payment) —
                // 'Paid' would misrepresent it as fully settled.
                $status = 'Partially Paid';
            }
        } else {
            $method = $distinctMethods->count() > 1 ? 'Split' : $distinctMethods->first();
            $status = match ($donationStatus) {
                'Completed' => 'Paid',
                'Partially Paid' => 'Partially Paid',
                default => 'Pending',
            };
        }

        DB::table($table)->where('id', $donationId)->update([
            'donation_status' => $donationStatus,
            'payment_method' => $method,
            'payment_status' => $status,
            'updated_at' => now(),
        ]);
    }
}
