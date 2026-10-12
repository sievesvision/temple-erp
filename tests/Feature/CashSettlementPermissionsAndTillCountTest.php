<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The till-count settlement model: pos/entry/admin can all settle, but a pos-level event
 * coordinator never sees past settlements/bankings (CashSettlementService::canSettle()/
 * canViewHistory()) — they count what's in front of them and settle, nothing more. Covers
 * the carry-forward math (closing_balance = till_count - amount_banked becomes the next
 * opening_balance), the required variance note on a mismatch, and that only the latest
 * settlement's figures (never an older one's) can be edited.
 */
class CashSettlementPermissionsAndTillCountTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Till Count Test Event', 'slug' => 'till-count-test-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function coordinator(int $eventId, string $level): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => $level,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    public function test_pos_level_coordinator_can_settle_but_the_pos_page_shows_no_history(): void
    {
        $eventId = $this->makeEvent();
        $pos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($pos)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Settle Till');
        $response->assertDontSee('Settlement History');
        $response->assertDontSee('Banking History');
    }

    private function insertCashDonation(int $eventId, string $donorName, float $amount, ?string $date = null): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => $donorName, 'amount' => $amount,
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'purpose' => 'General',
            'donation_date' => $date ?? now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_pos_level_coordinator_sees_and_can_edit_a_current_period_cash_donation(): void
    {
        $eventId = $this->makeEvent();
        $pos = $this->coordinator($eventId, 'pos');
        $donationId = $this->insertCashDonation($eventId, 'Walk-up Donor', 75);

        $response = $this->actingAs($pos)->get(route('admin.events.pos', $eventId));
        $response->assertOk();
        $response->assertSee('Walk-up Donor');
        $response->assertSee('Current Period Transactions');

        $editResponse = $this->actingAs($pos)->post(route('admin.donations.updateGuest', $donationId), [
            'donor_name' => 'Corrected Donor Name', 'amount' => 80, 'purpose' => 'General',
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'donation_date' => now()->toDateString(),
        ]);
        $editResponse->assertRedirect();
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donor_name' => 'Corrected Donor Name', 'amount' => 80]);
    }

    public function test_a_donation_from_an_already_settled_period_is_excluded_from_current_transactions(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $yesterday = now()->subDay()->toDateString();
        $this->insertCashDonation($eventId, 'Settled Donor', 40, $yesterday);

        CashSettlementService::run('event', $eventId, $admin->id, 40.0, 0.0, null, now()->subDay());

        $this->insertCashDonation($eventId, 'Fresh Donor', 25);

        $current = CashSettlementService::currentPeriodTransactions('event', $eventId);

        $this->assertCount(1, $current);
        $this->assertSame('Fresh Donor', $current->first()->display_name);
    }

    public function test_tickets_current_transactions_show_without_an_edit_option(): void
    {
        $entry = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert(['user_id' => $entry->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ticket_orders')->insert([
            'customer_name' => 'Ticket Buyer', 'total_amount' => 30, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($entry)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('Ticket Buyer');
        $response->assertSee("can't be edited here yet", false);
    }

    public function test_entry_level_coordinator_sees_full_history_on_the_pos_page(): void
    {
        $eventId = $this->makeEvent();
        $entry = $this->coordinator($eventId, 'entry');
        CashSettlementService::run('event', $eventId, $entry->id, 50.0, 0.0, null);

        $response = $this->actingAs($entry)->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Settlement History');
        $response->assertSee('Banking History');
    }

    public function test_a_pos_level_coordinator_can_actually_run_a_settlement(): void
    {
        $eventId = $this->makeEvent();
        $pos = $this->coordinator($eventId, 'pos');

        // Nothing's been received yet for this fresh event, so expected is 0 — till_count=0
        // matches it exactly and needs no variance note.
        $response = $this->actingAs($pos)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId,
            'till_count' => 0, 'amount_banked' => 0,
        ]);

        $response->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('cash_settlements', [
            'scope' => 'event', 'event_id' => $eventId, 'till_count' => 0, 'amount_banked' => 0, 'closing_balance' => 0,
        ]);
    }

    public function test_a_view_level_coordinator_cannot_settle_at_all(): void
    {
        $eventId = $this->makeEvent();
        $view = $this->coordinator($eventId, 'view');

        $response = $this->actingAs($view)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId, 'till_count' => 10, 'amount_banked' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_till_count_matching_expected_needs_no_variance_note(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId, 'till_count' => 0, 'amount_banked' => 0,
        ]);

        $response->assertRedirect()->assertSessionDoesntHaveErrors();
    }

    public function test_a_mismatched_till_count_is_rejected_without_a_note(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId, 'till_count' => 25, 'amount_banked' => 0,
        ]);

        $response->assertSessionHasErrors('variance_note');
        $this->assertDatabaseMissing('cash_settlements', ['event_id' => $eventId]);
    }

    public function test_a_mismatched_till_count_with_a_note_is_accepted_and_variance_is_recorded(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId, 'till_count' => 25, 'amount_banked' => 0,
            'variance_note' => 'Found an extra $25 note in the till from an earlier shift.',
        ]);

        $response->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('cash_settlements', [
            'event_id' => $eventId, 'till_count' => 25, 'variance' => 25, 'closing_balance' => 25,
        ]);
    }

    public function test_closing_balance_carries_forward_as_the_next_opening_balance(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        // Till count of 60 against an expected 0 — overage, needs a note; 20 goes to the
        // bank, so 40 should carry forward.
        $this->actingAs($admin)->post(route('admin.cash-settlement.run'), [
            'scope' => 'event', 'event_id' => $eventId, 'till_count' => 60, 'amount_banked' => 20,
            'variance_note' => 'Float top-up from the safe.',
        ]);

        $this->assertSame(40.0, CashSettlementService::currentOpeningBalance('event', $eventId));
    }

    public function test_only_the_latest_settlements_figures_can_be_edited(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $first = CashSettlementService::run('event', $eventId, $admin->id, 100.0, 0.0, null);
        $second = CashSettlementService::run('event', $eventId, $admin->id, 0.0, 0.0, null);

        $this->assertFalse(CashSettlementService::canEditFigures($first));
        $this->assertTrue(CashSettlementService::canEditFigures($second));

        // Attempting to edit the OLDER settlement's figures is rejected server-side even if
        // someone crafts the request — only its note is ever accepted.
        $response = $this->actingAs($admin)->post(route('admin.cash-settlement.update', $first->id), [
            'till_count' => 999, 'amount_banked' => 999, 'variance_note' => 'trying to rewrite history',
        ]);
        $response->assertRedirect();
        $first->refresh();
        $this->assertSame('100.00', $first->till_count);
        $this->assertSame('trying to rewrite history', $first->variance_note);
    }

    public function test_pos_level_coordinator_cannot_update_a_settlement(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $pos = $this->coordinator($eventId, 'pos');
        $settlement = CashSettlementService::run('event', $eventId, $admin->id, 10.0, 0.0, null);

        $response = $this->actingAs($pos)->post(route('admin.cash-settlement.update', $settlement->id), [
            'variance_note' => 'should not be allowed',
        ]);

        $response->assertStatus(403);
    }

    public function test_entry_level_ticket_controller_sees_full_history_on_the_ticket_pos_page(): void
    {
        $entry = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert(['user_id' => $entry->id, 'level' => 'entry', 'created_at' => now(), 'updated_at' => now()]);
        CashSettlementService::run('tickets', null, $entry->id, 0.0, 0.0, null);

        $response = $this->actingAs($entry)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('Settlement History');
    }
}
