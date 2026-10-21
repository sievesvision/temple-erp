<?php

namespace Tests\Feature;

use App\Models\LinklyTransaction;
use App\Models\SciTransaction;
use App\Models\User;
use App\Services\CashSettlementService;
use App\Services\DonationPaymentService;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Partial Pledge Payments, Phase E — cash-till reconciliation and the Settlement Summary
 * correctly "explode" a pledge paid across several methods/installments into its real
 * per-payment amounts, two claim granularities (donation-level review vs payment-level
 * money) never double-count or lose track of a payment, and a refund cancels only the
 * specific installment it's for.
 */
class SettlementPaymentExplosionTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Settlement Explosion Test Event', 'slug' => 'settlement-explosion-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePledge(int $eventId, float $amount): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Pledging Donor', 'amount' => $amount, 'purpose' => 'General',
            'payment_method' => null, 'payment_status' => 'Pledge', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cash_till_only_counts_the_cash_slice_of_a_split_pledge(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'Bank', 'payment_status' => 'Completed']);

        $preview = CashSettlementService::preview('event', $eventId);

        $this->assertSame(200.0, $preview['cash_received']);
    }

    public function test_a_partially_paid_pledge_is_visible_in_current_period_transactions(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $current = CashSettlementService::currentPeriodTransactions('event', $eventId);

        $this->assertTrue($current->contains(fn ($r) => $r->id === $donationId));
    }

    public function test_a_settlement_never_donation_claims_a_partially_paid_pledge_so_it_stays_visible_next_period(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        CashSettlementService::run('event', $eventId, $admin->id, 200.0, 0.0, null);

        $current = CashSettlementService::currentPeriodTransactions('event', $eventId);
        $this->assertTrue($current->contains(fn ($r) => $r->id === $donationId));
    }

    public function test_a_settlement_never_double_counts_a_cash_payment_in_the_next_period(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        CashSettlementService::run('event', $eventId, $admin->id, 200.0, 0.0, null);

        // The same pledge, still Partially Paid, is visible again — but its already-
        // counted $200 Cash must not be counted a second time.
        $nextPreview = CashSettlementService::preview('event', $eventId);
        $this->assertSame(0.0, $nextPreview['cash_received']);

        // Paying off the rest brings in only the NEW money.
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        $afterSecondPayment = CashSettlementService::preview('event', $eventId);
        $this->assertSame(300.0, $afterSecondPayment['cash_received']);
    }

    public function test_payment_method_breakdown_explodes_a_split_pledge_across_its_real_methods(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'Bank', 'payment_status' => 'Completed']);

        $breakdown = CashSettlementService::paymentMethodBreakdown('event', $eventId);

        $this->assertSame(500.0, $breakdown['total']);
        $this->assertSame(200.0, $breakdown['by_method']['Cash']);
        $this->assertSame(300.0, $breakdown['by_method']['Bank']);
    }

    public function test_the_settlement_summary_tiles_show_the_exploded_breakdown_not_split(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'Bank', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('AUD 500.00');
        $response->assertSeeInOrder(['Bank', 'AUD 300.00']);
        $response->assertSeeInOrder(['Cash', 'AUD 200.00']);
    }

    public function test_tickets_scope_payment_method_breakdown_is_a_noop(): void
    {
        $breakdown = CashSettlementService::paymentMethodBreakdown('tickets', null);

        $this->assertSame(0.0, $breakdown['total']);
        $this->assertSame([], $breakdown['by_method']);
    }

    public function test_an_sci_refund_cancels_only_its_own_payment_not_the_whole_pledge(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        $cashPayment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        $eftPayment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed']);

        $refundTxn = SciTransaction::create([
            'client_ref' => 'refund-' . uniqid(),
            'sci_transaction_id' => 'SCI-REFUND-' . uniqid(),
            'event_id' => $eventId,
            'donation_type' => 'guest',
            'donation_id' => $donationId,
            'donation_payment_id' => $eftPayment->id,
            'txn_type' => 'refund',
            'amount' => 300,
            'status' => 'FINALISED',
            'result_financial_status' => 'APPROVED',
        ]);

        $controller = app(\App\Http\Controllers\CbaSciController::class);
        $method = new ReflectionMethod($controller, 'markDonationCancelledIfRefundJustApproved');
        $method->setAccessible(true);
        $method->invoke($controller, $refundTxn);

        $this->assertDatabaseHas('donation_payments', ['id' => $eftPayment->id, 'payment_status' => 'Refunded']);
        // The OTHER installment (Cash) must be completely untouched.
        $this->assertDatabaseHas('donation_payments', ['id' => $cashPayment->id, 'payment_status' => 'Completed']);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donation_status' => 'Partially Paid']);
    }

    public function test_a_linkly_refund_cancels_only_its_own_payment_not_the_whole_pledge(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        $cashPayment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        $eftPayment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed']);

        $sessionId = 'linkly-refund-session-' . uniqid();
        LinklyTransaction::create([
            'pos_txn_ref' => 'refund-' . uniqid(),
            'linkly_session_id' => $sessionId,
            'event_id' => $eventId,
            'donation_type' => 'guest',
            'donation_id' => $donationId,
            'donation_payment_id' => $eftPayment->id,
            'txn_type' => 'refund',
            'amount' => 300,
            'status' => 'approved',
        ]);

        $controller = app(\App\Http\Controllers\DonationController::class);
        $method = new ReflectionMethod($controller, 'markDonationCancelledIfRefundJustApproved');
        $method->setAccessible(true);
        $method->invoke($controller, $sessionId, ['done' => true, 'success' => true]);

        $this->assertDatabaseHas('donation_payments', ['id' => $eftPayment->id, 'payment_status' => 'Refunded']);
        $this->assertDatabaseHas('donation_payments', ['id' => $cashPayment->id, 'payment_status' => 'Completed']);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donation_status' => 'Partially Paid']);
    }

    public function test_a_refund_with_no_donation_payment_id_anchor_falls_back_to_the_old_behaviour(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 300);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed']);

        $refundTxn = SciTransaction::create([
            'client_ref' => 'refund-noanchor-' . uniqid(),
            'sci_transaction_id' => 'SCI-REFUND-' . uniqid(),
            'event_id' => $eventId,
            'donation_type' => 'guest',
            'donation_id' => $donationId,
            // No donation_payment_id — simulates a transaction from before the reverse-
            // link fix. Must still fall back to the old whole-row cancel, not silently
            // do nothing.
            'txn_type' => 'refund',
            'amount' => 300,
            'status' => 'FINALISED',
            'result_financial_status' => 'APPROVED',
        ]);

        $controller = app(\App\Http\Controllers\CbaSciController::class);
        $method = new ReflectionMethod($controller, 'markDonationCancelledIfRefundJustApproved');
        $method->setAccessible(true);
        $method->invoke($controller, $refundTxn);

        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'payment_status' => 'Cancelled']);
    }

    public function test_organisation_report_by_method_breakdown_explodes_a_split_donation(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 300, 'payment_method' => 'Bank', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->get(route('admin.transactions.index'));

        $response->assertOk();
        // by_method is embedded as the chart's own @json data — exploded into its real
        // per-method amounts (not bucketed under 'Split').
        $response->assertSee('{"label":"Cash","total":200}', false);
        $response->assertSee('{"label":"Bank Transfer","total":300}', false);
    }
}
