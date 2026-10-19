<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Settlement pane's "Settlement Summary" card — total received across every payment
 * method (not just cash), a breakdown by payment type, and the pending count/total — built
 * straight from $currentTransactions (see cash-settlement-pane.blade.php) so it can never
 * drift from the Current Period Transactions table sitting right below it.
 */
class CashSettlementSummaryTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Settlement Summary Test Event', 'slug' => 'settlement-summary-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, string $donorName, float $amount, string $method, string $status): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => $donorName, 'amount' => $amount,
            'payment_method' => $method, 'payment_status' => $status, 'purpose' => 'General',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_total_received_sums_paid_rows_across_every_payment_method(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Cash Donor', 100, 'Cash', 'Paid');
        $this->insertGuestDonation($eventId, 'Bank Donor', 200, 'Bank', 'Paid');
        $this->insertGuestDonation($eventId, 'Card Donor', 75, 'EFT Terminal', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Total Received (Completed)');
        $response->assertSee('AUD 375.00');
    }

    public function test_received_is_broken_down_by_payment_type(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Cash Donor', 100, 'Cash', 'Paid');
        $this->insertGuestDonation($eventId, 'Bank Donor', 200, 'Bank', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Received by Payment Type');
        $response->assertSeeInOrder(['Bank', 'AUD 200.00']);
        $response->assertSeeInOrder(['Cash', 'AUD 100.00']);
    }

    public function test_pending_transactions_shows_count_and_total_excluded_from_received(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Paid Donor', 50, 'Cash', 'Paid');
        $this->insertGuestDonation($eventId, 'Pending Donor 1', 30, 'Bank', 'Pending');
        $this->insertGuestDonation($eventId, 'Pending Donor 2', 45, 'Bank', 'Pending');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Pending Transactions');
        $response->assertSee('2');
        $response->assertSee('AUD 75.00');
        // The Total Received figure must stay Paid-only — Pending never counts as received.
        $response->assertSee('AUD 50.00');
    }

    public function test_cancelled_rows_are_excluded_from_both_received_and_pending(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Cancelled Donor', 999, 'Cash', 'Cancelled');

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertDontSee('AUD 999.00');
    }
}
