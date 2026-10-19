<?php

namespace Tests\Feature;

use App\Models\DonationPayment;
use App\Services\DonationPaymentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Partial Pledge Payments, Phase A — DonationPaymentService is the one shared path every
 * entry point (portal Add Payment, POS, EFT approval handlers) will call in later phases to
 * record a payment against a pledge. This covers the recompute/lock/idempotency logic in
 * isolation, against synthetic data, before any UI exists on top of it.
 */
class DonationPaymentServiceTest extends TestCase
{
    private function makePledge(float $amount = 1000): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'donor_name' => 'Pledging Donor', 'amount' => $amount, 'purpose' => 'General',
            'payment_method' => null, 'payment_status' => 'Pledge', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_new_pledge_with_no_payments_has_the_full_amount_outstanding(): void
    {
        $id = $this->makePledge(1000);

        $this->assertEquals(1000.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertEquals(0.00, DonationPaymentService::totalPaid('guest', $id));
        $this->assertCount(0, DonationPaymentService::paymentHistory('guest', $id));
    }

    public function test_first_partial_cash_payment_moves_the_pledge_to_partially_paid(): void
    {
        $id = $this->makePledge(1000);

        $payment = DonationPaymentService::recordPayment('guest', $id, [
            'amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $this->assertEquals('Cash', $payment->payment_method);
        $this->assertEquals(800.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $id, 'donation_status' => 'Partially Paid', 'payment_method' => 'Cash', 'payment_status' => 'Paid',
        ]);
    }

    public function test_multiple_payments_using_different_methods_mark_the_parent_split(): void
    {
        $id = $this->makePledge(1000);

        DonationPaymentService::recordPayment('guest', $id, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 300, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed']);

        $this->assertEquals(500.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $id, 'donation_status' => 'Partially Paid', 'payment_method' => 'Split', 'payment_status' => 'Partially Paid',
        ]);
        $this->assertCount(2, DonationPaymentService::paymentHistory('guest', $id));
    }

    public function test_the_final_payment_completing_a_pledge_marks_it_completed_and_paid(): void
    {
        $id = $this->makePledge(1000);

        DonationPaymentService::recordPayment('guest', $id, ['amount' => 600, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 400, 'payment_method' => 'Bank', 'payment_status' => 'Completed']);

        $this->assertEquals(0.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $id, 'donation_status' => 'Completed', 'payment_method' => 'Split', 'payment_status' => 'Paid',
        ]);
    }

    public function test_a_pending_bank_transfer_does_not_count_as_received_funds(): void
    {
        $id = $this->makePledge(1000);

        DonationPaymentService::recordPayment('guest', $id, ['amount' => 400, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);

        $this->assertEquals(0.00, DonationPaymentService::totalPaid('guest', $id));
        $this->assertEquals(1000.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertDatabaseHas('donations_without_logins', ['id' => $id, 'donation_status' => 'Pending']);
    }

    public function test_a_pending_bank_transfer_later_confirmed_reduces_the_outstanding_balance(): void
    {
        $id = $this->makePledge(1000);

        $payment = DonationPaymentService::recordPayment('guest', $id, ['amount' => 400, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);
        $payment->payment_status = 'Completed';
        $payment->save();
        DonationPaymentService::recomputeParent('guest', $id);

        $this->assertEquals(600.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertDatabaseHas('donations_without_logins', ['id' => $id, 'donation_status' => 'Partially Paid']);
    }

    public function test_a_payment_cannot_exceed_the_outstanding_balance(): void
    {
        $id = $this->makePledge(1000);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 700, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $this->expectException(\RuntimeException::class);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
    }

    public function test_two_counters_racing_the_same_pledge_the_second_is_rejected_once_the_first_lands(): void
    {
        $id = $this->makePledge(500);

        // Simulates two counters both trying to pay off the same $500 outstanding balance —
        // the lock inside recordPayment() means the second call sees the first's already-
        // updated outstanding balance, not a stale pre-payment one.
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 500, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $this->expectException(\RuntimeException::class);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 500, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
    }

    public function test_a_cancelled_pledge_rejects_further_payments(): void
    {
        $id = $this->makePledge(500);
        DB::table('donations_without_logins')->where('id', $id)->update(['donation_status' => 'Cancelled']);

        $this->expectException(\RuntimeException::class);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 100, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
    }

    public function test_a_duplicate_submission_with_the_same_transaction_id_is_not_double_recorded(): void
    {
        $id = $this->makePledge(500);

        $first = DonationPaymentService::recordPayment('guest', $id, [
            'amount' => 200, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed', 'transaction_id' => 'TXN-ABC',
        ]);
        $second = DonationPaymentService::recordPayment('guest', $id, [
            'amount' => 200, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed', 'transaction_id' => 'TXN-ABC',
        ]);

        $this->assertEquals($first->id, $second->id);
        $this->assertCount(1, DonationPaymentService::paymentHistory('guest', $id));
        $this->assertEquals(300.00, DonationPaymentService::outstandingBalance('guest', $id));
    }

    public function test_cancelling_one_payment_recomputes_the_pledge_without_cancelling_the_whole_donation(): void
    {
        $id = $this->makePledge(1000);
        $a = DonationPaymentService::recordPayment('guest', $id, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $id, ['amount' => 300, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed']);

        DonationPaymentService::cancelPayment($a, 'Refunded');

        $this->assertEquals(700.00, DonationPaymentService::outstandingBalance('guest', $id));
        $this->assertDatabaseHas('donations_without_logins', ['id' => $id, 'donation_status' => 'Partially Paid']);
        $this->assertDatabaseHas('donation_payments', ['id' => $a->id, 'payment_status' => 'Refunded']);
    }

    public function test_a_devotee_pledge_payment_writes_to_the_donations_table(): void
    {
        $id = DB::table('donations')->insertGetId([
            'amount' => 500, 'payment_method' => null, 'payment_status' => 'Pledge', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DonationPaymentService::recordPayment('devotee', $id, ['amount' => 500, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $this->assertDatabaseHas('donations', ['id' => $id, 'donation_status' => 'Completed', 'payment_status' => 'Paid']);
        $this->assertDatabaseHas('donation_payments', ['donation_type' => 'devotee', 'donation_id' => $id, 'amount' => 500]);
    }
}
