<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cash reconciliation for event donations and ticket sales — cash received (per option/
 * ticket-type breakdown), banking deposits (always a lump sum), and settlements (locked
 * snapshots where the next period's opening balance is simply the previous one's closing).
 */
class CashSettlementTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Settlement Test Event',
            'slug' => 'settlement-test-event-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(),
            'status' => 'Upcoming',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, float $amount, string $method, string $date, ?int $optionId = null): int
    {
        $donationId = DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId,
            'donor_name' => 'Test Donor',
            'email' => 'donor@example.com',
            'amount' => $amount,
            'payment_method' => $method,
            'payment_status' => 'Paid',
            'donation_date' => $date,
            'purpose' => 'General',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($optionId) {
            DB::table('donation_selections')->insert([
                'donation_type' => 'guest',
                'donation_id' => $donationId,
                'event_donation_option_id' => $optionId,
                'option_label' => 'General Donation',
                'quantity' => 1,
                'amount' => $amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $donationId;
    }

    public function test_preview_computes_opening_received_banked_and_closing_for_an_event(): void
    {
        $eventId = $this->makeEvent();
        $optionId = DB::table('event_donation_options')->insertGetId([
            'event_id' => $eventId, 'label' => 'General Donation', 'amount' => null, 'allow_quantity' => false, 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Two Cash donations, one Bank Transfer donation (must be excluded from cash figures).
        $this->insertGuestDonation($eventId, 100, 'Cash', now()->toDateString(), $optionId);
        $this->insertGuestDonation($eventId, 50, 'Cash', now()->toDateString(), $optionId);
        $this->insertGuestDonation($eventId, 999, 'Bank', now()->toDateString(), $optionId);

        $preview = CashSettlementService::preview('event', $eventId);

        $this->assertSame(0.0, $preview['opening_balance']);
        $this->assertSame(150.0, $preview['cash_received']);
        $this->assertSame(0.0, $preview['amount_banked']);
        $this->assertSame(150.0, $preview['closing_balance']);
        $this->assertCount(1, $preview['breakdown']);
        $this->assertSame('General Donation', $preview['breakdown'][0]['label']);
        $this->assertSame(150.0, $preview['breakdown'][0]['cash_received']);
    }

    public function test_banking_a_deposit_reduces_remaining_cash_to_bank(): void
    {
        $admin = $this->adminUser();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 200, 'Cash', now()->toDateString());

        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.recordBanking'), [
            'scope' => 'event',
            'event_id' => $eventId,
            'amount' => 120,
            'banked_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $preview = CashSettlementService::preview('event', $eventId);
        $this->assertSame(200.0, $preview['cash_received']);
        $this->assertSame(120.0, $preview['amount_banked']);
        $this->assertSame(80.0, $preview['closing_balance']);
    }

    public function test_running_a_settlement_locks_it_in_and_the_next_period_opens_from_its_closing_balance(): void
    {
        $admin = $this->adminUser();
        $eventId = $this->makeEvent();
        // A settlement is a once-per-day, end-of-day action — model "yesterday" so today's
        // fresh cash unambiguously belongs to the next, still-open period (donation_date/
        // banked_date are date-only columns, so same-day ordering can't be split further).
        $yesterday = now()->subDay()->toDateString();
        $this->insertGuestDonation($eventId, 300, 'Cash', $yesterday);

        $this->actingAs($admin)->post(route('admin.cash-settlement.recordBanking'), [
            'scope' => 'event', 'event_id' => $eventId, 'amount' => 100, 'banked_date' => $yesterday,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId, 'period_end' => $yesterday,
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('cash_settlements', [
            'scope' => 'event', 'event_id' => $eventId,
            'opening_balance' => 0, 'cash_received' => 300, 'amount_banked' => 100, 'closing_balance' => 200,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event_id' => $eventId]);

        // A new Cash donation the next day starts a fresh period whose opening balance is
        // exactly the previous settlement's closing balance.
        $this->insertGuestDonation($eventId, 50, 'Cash', now()->toDateString());
        $nextPreview = CashSettlementService::preview('event', $eventId);
        $this->assertSame(200.0, $nextPreview['opening_balance']);
        $this->assertSame(50.0, $nextPreview['cash_received']);
        $this->assertSame(250.0, $nextPreview['closing_balance']);
    }

    public function test_tickets_scope_is_global_and_breaks_down_by_ticket_type(): void
    {
        $admin = $this->adminUser();
        $ticketId = DB::table('tickets')->insertGetId([
            'name' => 'Adult Entry', 'price' => 10, 'status' => 'Active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = DB::table('ticket_orders')->insertGetId([
            'total_amount' => 20, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ticket_order_items')->insert([
            'ticket_order_id' => $orderId, 'ticket_id' => $ticketId, 'ticket_name' => 'Adult Entry',
            'unit_price' => 10, 'quantity' => 2, 'line_total' => 20, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $preview = CashSettlementService::preview('tickets', null);
        $this->assertSame(20.0, $preview['cash_received']);
        $this->assertSame('Adult Entry', $preview['breakdown'][0]['label']);

        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.run'), ['scope' => 'tickets']);
        $response->assertRedirect();
        $this->assertDatabaseHas('cash_settlements', ['scope' => 'tickets', 'event_id' => null, 'cash_received' => 20]);
    }
}
