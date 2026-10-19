<?php

namespace Tests\Feature;

use App\Models\LinklyTransaction;
use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Portal's "All Transactions" pane (event-console.blade.php's pane-table) gets a
 * multi-select Status/Payment Method filter plus a Settlement filter tagging each row with
 * whichever settlement claimed it (or "current" if none has) — see
 * CashSettlementService::settlementKeyMap()'s own docblock for how that tagging is derived.
 */
class AllTransactionsFilterTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'All Transactions Filter Test', 'slug' => 'all-txn-filter-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertGuestDonation(int $eventId, string $donorName, float $amount, string $method, string $status, ?string $email = null, ?string $mobile = null): int
    {
        return DB::table('donations_without_logins')->insertGetId([
            'event_id' => $eventId, 'donor_name' => $donorName, 'amount' => $amount,
            'payment_method' => $method, 'payment_status' => $status, 'purpose' => 'General',
            'email' => $email, 'mobile' => $mobile,
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_portal_renders_multi_select_filter_pills_for_method_status_and_settlement(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Settled Donor', 50, 'Cash', 'Paid');
        $settlement = CashSettlementService::run('event', $eventId, $admin->id, 50.0, 50.0, null);
        $this->insertGuestDonation($eventId, 'Current Donor', 30, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('donationsStatusPills', false);
        $response->assertSee('donationsPaymentPills', false);
        $response->assertSee('donationsSettlementSelect', false);
        $response->assertSee('Current (Unsettled)');
        $response->assertSee('Settled Donor');
        $response->assertSee('Current Donor');
        // Settled Donor's row is tagged with the real settlement id, Current Donor's with
        // "current" — confirms settlementKeyMap() tagging actually reached the rendered row.
        $response->assertSee('data-settlement="' . $settlement->id . '"', false);
        $response->assertSee('data-settlement="current"', false);
    }

    public function test_cancelled_and_failed_statuses_are_not_active_by_default(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Cancelled Donor', 20, 'Cash', 'Cancelled');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        // The Cancelled pill itself renders, just not pre-activated — assert the inactive
        // markup pattern (no "active" class token immediately before the Cancelled pill's
        // own data-value) rather than plain text, since "Cancelled" also appears as the
        // donor's own row data regardless of pill state.
        $response->assertSee('data-value="cancelled">', false);
        $response->assertDontSee('donations-multi-pill active" data-value="cancelled"', false);
    }

    public function test_contact_details_are_shown_and_searchable_by_email_or_mobile(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Contactable Donor', 40, 'Cash', 'Paid', 'contactable@example.test', '0412345678');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('contactable@example.test');
        $response->assertSee('0412345678');
        // The row's own data-search attribute (client-side search index) must include both,
        // not just the visible column — this is what lets typing an email or mobile into
        // the search box actually find the row.
        $response->assertSee('data-search="contactable donor contactable@example.test 0412345678"', false);
    }

    public function test_transaction_id_column_is_shown_for_donations(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Reference Donor', 60, 'Bank', 'Paid');
        DB::table('donations_without_logins')->where('id', $donationId)->update(['transaction_id' => 'BK-REF-00042']);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Transaction ID');
        $response->assertSee('BK-REF-00042');
    }

    /**
     * A declined EFT Terminal attempt never becomes a donation, so it's not part of the
     * normal $rows loop — it's rendered as its own "orphan" row instead, but should still
     * get the exact same clickable row + View button + detail modal treatment as any other
     * transaction, not a lesser, inline-only one.
     */
    public function test_an_eft_attempt_gets_a_view_button_and_detail_modal_like_any_other_transaction(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $txn = LinklyTransaction::create([
            'pos_txn_ref' => 'EFT-ATTEMPT-001', 'client_ref' => 'client-ref-1', 'linkly_session_id' => 'session-1',
            'txn_type' => 'purchase', 'event_id' => $eventId, 'eft_terminal_id' => null,
            'donation_type' => null, 'donation_id' => null, 'amount' => 77.00, 'currency_code' => 'AUD',
            'status' => 'declined', 'initiated_by' => $admin->id,
            'meta' => ['donor_name' => 'Declined Donor', 'email' => 'declined@example.test'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $modalId = 'eftDetaillinkly' . $txn->id;
        $response->assertSee('data-bs-target="#' . $modalId . '"', false);
        $response->assertSee('id="' . $modalId . '"', false);
        $response->assertSee('Declined Donor');
        $response->assertSee('EFT-ATTEMPT-001');
    }

    /**
     * Once a transaction is Completed (Paid), Edit and Delete are gone — Resend is the only
     * correction left available, regardless of payment method (not just EFT Terminal, which
     * was already locked for a different reason — see EftTerminalDonationImmutabilityTest).
     */
    public function test_a_completed_donation_hides_edit_and_delete_and_keeps_only_resend(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Paid Cash Donor', 45, 'Cash', 'Paid', 'paid@example.test');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertDontSee('editGuestDonationModal' . $donationId, false);
        $response->assertDontSee(route('admin.donations.deleteGuest', $donationId), false);
        $response->assertSee(route('admin.donations.resendReceipt', ['type' => 'guest', 'id' => $donationId]), false);
    }

    /**
     * A still-Pending donation keeps Edit/Delete (it hasn't been counted as received yet)
     * and gets the prominent "Confirm & Send Receipt" action instead of Resend — approving
     * is what marks it Paid and sends the receipt in the same step (see
     * DonationController::approveGuestDonation()).
     */
    public function test_a_pending_donation_shows_the_prominent_confirm_and_send_receipt_action(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Pending Bank Donor', 35, 'Bank', 'Pending');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Confirm &amp; Send Receipt', false);
        $response->assertSee('btn-action-approve-prominent', false);
        $response->assertSee('editGuestDonationModal' . $donationId, false);
        $response->assertSee(route('admin.donations.deleteGuest', $donationId), false);
    }

    public function test_a_completed_donations_receipt_id_is_shown_in_the_detail_modal(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'Receipted Donor', 90, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Receipt ID');
        $response->assertSee('G-' . str_pad((string) $donationId, 6, '0', STR_PAD_LEFT));
    }

    /**
     * $distinctStatuses used to be built only from real donation/order rows — an event whose
     * only "Cancelled"-looking activity was a declined/cancelled EFT attempt (no donation ever
     * actually had Cancelled status) never got a Cancelled pill rendered at all, so that row
     * had no way to ever become visible: "All" only re-activates pills that exist. The orphan
     * row's own data-status also used to carry the raw provider status word ("declined",
     * "AWAITING_POS", ...) instead of the same paid/pending/cancelled vocabulary the pills use,
     * which would have broken this even with the pill present.
     */
    public function test_an_eft_only_cancelled_attempt_gets_its_own_status_pill_and_matching_data_status(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        // The only donation on this event is Paid — nothing here ever has Cancelled status.
        $this->insertGuestDonation($eventId, 'Paid Donor', 50, 'Cash', 'Paid');
        \App\Models\SciTransaction::create([
            'client_ref' => 'client-ref-cancel-1', 'sci_transaction_id' => 'sci-cancel-1', 'sci_version' => 1,
            'event_id' => $eventId, 'eft_terminal_id' => null, 'donation_type' => null, 'donation_id' => null,
            'txn_type' => 'purchase', 'amount' => 20.00, 'currency_code' => 'AUD',
            'status' => 'FINALISED', 'result_financial_status' => 'CANCELLED',
            'message' => 'Transaction cancelled',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('data-value="cancelled"', false);
        $response->assertSee('data-status="cancelled"', false);
    }

    public function test_the_table_shows_a_donation_id_column(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $donationId = $this->insertGuestDonation($eventId, 'ID Column Donor', 40, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('Donation ID');
        $response->assertSee('GD' . str_pad((string) $donationId, 5, '0', STR_PAD_LEFT));
    }

    /**
     * donation_date is a business/accounting date the Add Transaction modal lets staff
     * deliberately backdate — shown as the main line since it's what the donation is dated
     * as. created_at is the real, uneditable moment the record was actually saved — shown
     * underneath so both stay visible rather than one silently standing in for the other.
     */
    public function test_date_and_time_column_shows_both_donation_date_and_the_real_recorded_timestamp(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        DB::table('donations_without_logins')->insert([
            'event_id' => $eventId, 'donor_name' => 'Backdated Donor', 'amount' => 60,
            'payment_method' => 'Cash', 'payment_status' => 'Paid', 'purpose' => 'General',
            'donation_date' => '2026-01-01', 'created_at' => '2026-09-05 14:35:00', 'updated_at' => '2026-09-05 14:35:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $html = $response->getContent();
        preg_match('/<table class="console-table" id="donationsTable">.*?<\/table>/s', $html, $m);
        $this->assertNotEmpty($m, 'donationsTable not found in response');
        $this->assertStringContainsString('01 Jan 2026', $m[0]);
        $this->assertStringContainsString('Recorded 05 Sep 2026, 2:35 PM', $m[0]);
    }

    public function test_the_filter_row_combines_quick_ranges_date_range_and_a_compact_search_box(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('donations-filter-row-combined', false);
        $response->assertSee('donations-search-wrap-compact', false);
    }

    /**
     * Clear moved out of the filter row (where the quick-range pills/date range/search box
     * live) to a small red "x" pinned to the top of the filter box itself — a distinct,
     * slightly destructive action rather than a peer of the other controls. Only one
     * #donationsFilterClear element should exist (the old in-row button text is gone).
     */
    public function test_clear_is_a_small_icon_button_at_the_top_of_the_filter_box(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));

        $response->assertOk();
        $response->assertSee('donations-filter-clear-x', false);
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="donationsFilterClear"'));
    }

    /**
     * Clear used to uniformly re-select every pill in every group — correct for Status
     * (whose default IS every pill it starts with active), wrong for Payment Method (whose
     * default is nothing selected, All alone). Both groups' own defaultActive snapshot
     * (captured from how they're rendered here) is what the JS restores on Clear — this
     * locks in that the server-rendered starting state for each group is what a reader of
     * the JS would expect Clear to restore it to.
     */
    public function test_payment_method_and_status_pills_keep_their_own_distinct_defaults(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $this->insertGuestDonation($eventId, 'Bank Donor', 50, 'Bank', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.events.portal', $eventId));
        $response->assertOk();
        $html = $response->getContent();

        preg_match('/<div class="donations-filter-pills" id="donationsPaymentPills">.*?<\/div>/s', $html, $paymentBlock);
        $this->assertStringNotContainsString('data-value="bank"><span class="pill-dot', $paymentBlock[0] ?? '');
        $this->assertMatchesRegularExpression('/data-value="bank"[^>]*>Bank/', $paymentBlock[0] ?? '');
        $this->assertDoesNotMatchRegularExpression('/class="donations-multi-pill active" data-value="bank"/', $paymentBlock[0] ?? '');

        preg_match('/<div class="donations-filter-pills" id="donationsStatusPills">.*?<\/div>/s', $html, $statusBlock);
        $this->assertMatchesRegularExpression('/class="donations-multi-pill active" data-value="paid"/', $statusBlock[0] ?? '');
    }
}
