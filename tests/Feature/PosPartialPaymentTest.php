<?php

namespace Tests\Feature;

use App\Models\DonationPayment;
use App\Models\User;
use App\Services\DonationPaymentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Partial Pledge Payments, Phase C — the POS's own completion flow for a Pending/Pledge/
 * Partially Paid donation (DonationController::completeGuestPendingPayment(), the widened
 * PosDonationController::show()/pendingPayments() queries), the per-payment Confirm action,
 * and the fixed approveGuestDonation()/approveDevoteeDonation() (which now resolve the
 * matching ledger row instead of only touching the parent donation).
 */
class PosPartialPaymentTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'POS Partial Payment Test Event', 'slug' => 'pos-partial-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePledge(int $eventId, float $amount = 1000): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Pledging Donor', 'amount' => $amount, 'purpose' => 'General',
            'payment_method' => null, 'payment_status' => 'Pledge', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_pos_page_loads_a_pledge_via_edit_id_and_shows_the_outstanding_balance(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId) . '?edit_id=' . $donationId);

        $response->assertOk();
        $response->assertSee('Completing a pending transaction', false);
        $response->assertSee('Outstanding', false);
        $response->assertSee('600.00', false);
    }

    public function test_completing_a_partial_cash_payment_from_the_pos_records_an_installment(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);

        $response = $this->actingAs($admin)->postJson(route('admin.donations.completeGuestPayment', $donationId), [
            'amount' => 300, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('donation_payments', [
            'donation_type' => 'guest', 'donation_id' => $donationId, 'amount' => 300, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donation_status' => 'Partially Paid']);
    }

    public function test_completing_the_full_outstanding_balance_marks_the_pledge_completed(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);

        $response = $this->actingAs($admin)->postJson(route('admin.donations.completeGuestPayment', $donationId), [
            'amount' => 500, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('donations_without_logins', [
            'id' => $donationId, 'donation_status' => 'Completed', 'payment_status' => 'Paid', 'payment_method' => 'Cash',
        ]);
    }

    public function test_a_bank_transfer_completion_from_the_pos_stays_pending_until_confirmed(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);

        $this->actingAs($admin)->postJson(route('admin.donations.completeGuestPayment', $donationId), [
            'amount' => 500, 'payment_method' => 'Bank', 'payment_status' => 'Pending',
        ]);

        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donation_status' => 'Pending', 'payment_status' => 'Pending']);
        $this->assertEquals(500.00, DonationPaymentService::outstandingBalance('guest', $donationId));
    }

    public function test_completing_from_the_pos_cannot_exceed_the_outstanding_balance_for_cash(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 300);

        $response = $this->actingAs($admin)->postJson(route('admin.donations.completeGuestPayment', $donationId), [
            'amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('donation_payments', ['donation_id' => $donationId]);
    }

    public function test_an_eft_terminal_overage_is_still_recorded_since_the_money_already_moved(): void
    {
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 300);

        // Simulates CbaSciController::createRecordIfApprovedAndUnrecorded()'s own call —
        // the terminal already charged this amount in real life, so it must be recorded
        // even though it's $50 over the pledge's outstanding balance (e.g. a stale amount
        // shown before an admin adjusted something concurrently).
        $payment = DonationPaymentService::recordPayment('guest', $donationId, [
            'amount' => 350, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Completed', 'transaction_id' => 'EFT-REF-1',
        ], true);

        $this->assertEquals(350.00, (float) $payment->amount);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donation_status' => 'Completed']);
    }

    public function test_confirm_payment_confirms_a_specific_pending_payment_and_recomputes_the_pledge(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        $pending = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 600, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);

        $response = $this->actingAs($admin)->post(route('admin.donations.confirmPayment', $pending->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('donation_payments', ['id' => $pending->id, 'payment_status' => 'Completed']);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donation_status' => 'Completed']);
    }

    public function test_approve_guest_donation_still_works_for_the_common_single_pending_payment_case(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        // A donation created the normal way (storePublic()-style): one Pending Bank payment.
        $donationId = DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => 'Bank Donor', 'amount' => 80, 'purpose' => 'General',
            'payment_method' => 'Bank', 'payment_status' => 'Pending', 'donation_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DonationPayment::create([
            'donation_type' => 'guest', 'donation_id' => $donationId, 'amount' => 80,
            'payment_method' => 'Bank', 'payment_status' => 'Pending', 'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.donations.approveGuest', $donationId));

        $response->assertRedirect();
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'payment_status' => 'Paid', 'donation_status' => 'Completed']);
        $this->assertDatabaseHas('donation_payments', ['donation_id' => $donationId, 'payment_status' => 'Completed']);
    }

    public function test_approve_guest_donation_rejects_when_more_than_one_payment_is_pending(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 400, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 600, 'payment_method' => 'Cheque', 'payment_status' => 'Pending']);

        $response = $this->actingAs($admin)->post(route('admin.donations.approveGuest', $donationId));

        $response->assertSessionHas('error');
    }

    public function test_the_pending_transactions_popup_includes_a_partially_paid_donation(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(1, $results);
        $this->assertSame('600.00', $results[0]['amount']);
    }

    public function test_the_pending_transactions_popup_includes_a_pure_pledge(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->makePledge($eventId, 250);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $response->assertOk();
        $this->assertCount(1, $response->json('results'));
        $this->assertSame('250.00', $response->json('results.0.amount'));
    }

    public function test_the_pending_transactions_popup_excludes_a_completed_pledge(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 100);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 100, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->getJson(route('admin.events.pos.pending-payments', $eventId));

        $this->assertSame([], $response->json('results'));
    }
}
