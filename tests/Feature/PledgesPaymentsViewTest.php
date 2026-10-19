<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DonationPaymentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Partial Pledge Payments, Phase D — the All Transactions pane's Pledges/Payments view
 * toggle: the Pledges view (existing table) gains Paid/Outstanding columns and a Pledge
 * Status filter group; the Payments view is a new table sourced from donation_payments,
 * one row per actual payment, reusing the same filter-pill mechanism.
 */
class PledgesPaymentsViewTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Pledges Payments View Test Event', 'slug' => 'pledges-payments-view-' . uniqid(),
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

    public function test_the_portal_renders_the_pledges_payments_toggle(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('id="viewTogglePledges"', false);
        $response->assertSee('id="viewTogglePayments"', false);
        $response->assertSee('id="paymentsTable"', false);
    }

    public function test_the_pledges_table_shows_paid_and_outstanding_columns(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('>Paid<', false);
        $response->assertSee('>Outstanding<', false);
        $response->assertSee('>Pledged<', false);
    }

    public function test_the_pledge_status_filter_pills_are_rendered(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->makePledge($eventId, 500);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('id="donationsPledgeStatusPills"', false);
        $response->assertSee('Pledge Status', false);
    }

    public function test_the_payments_table_lists_one_row_per_actual_payment(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 1000);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 400, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);
        DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 600, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $html = $response->getContent();
        // Both payment rows render inside the Payments table, each with its own amount.
        $this->assertStringContainsString('paymentsTable', $html);
        preg_match('/<table class="console-table" id="paymentsTable">.*?<\/table>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('400.00', $m[0]);
        $this->assertStringContainsString('600.00', $m[0]);
    }

    public function test_the_payments_table_offers_a_confirm_action_for_a_pending_payment(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->makePledge($eventId, 500);
        $payment = DonationPaymentService::recordPayment('guest', $donationId, ['amount' => 500, 'payment_method' => 'Bank', 'payment_status' => 'Pending']);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee(route('admin.donations.confirmPayment', $payment->id), false);
    }

    public function test_a_devotee_payment_also_appears_in_the_payments_table(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $devoteeUser = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        $devotee = \App\Models\Devotee::create(['user_id' => $devoteeUser->id, 'verified' => 1]);
        $donationId = DB::table('donations')->insertGetId([
            'devotee_id' => $devotee->devotee_id, 'event_id' => $eventId, 'amount' => 300, 'payment_method' => null, 'payment_status' => 'Pledge',
            'donation_status' => 'Pending', 'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DonationPaymentService::recordPayment('devotee', $donationId, ['amount' => 300, 'payment_method' => 'Cash', 'payment_status' => 'Completed']);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $html = $response->getContent();
        preg_match('/<table class="console-table" id="paymentsTable">.*?<\/table>/s', $html, $m);
        $this->assertStringContainsString('300.00', $m[0]);
    }
}
