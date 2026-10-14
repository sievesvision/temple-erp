<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Settle Till pane's "Current Period Transactions" table already showed Payment
 * Method/Status for the event (donations) scope — the tickets scope was missing a
 * Method column and had no way to reprint a receipt/stub without leaving the pane.
 */
class SettlementPaneTicketColumnsTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_tickets_current_transactions_show_payment_method_and_a_print_receipt_link(): void
    {
        $admin = $this->admin();
        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => 'Receipt Buyer', 'total_amount' => 40,
            'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('Receipt Buyer');
        $response->assertSee('Cash');
        $response->assertSee(route('admin.tickets.print', $orderId), false);
        $response->assertSee('Print Receipt');
    }

    /**
     * The whole row is clickable through to the same order detail/reprint view the print
     * icon already links to (see the cs-row-clickable JS in the partial) — not just the
     * small icon in the last column.
     */
    public function test_a_ticket_order_row_is_clickable_through_to_its_detail_and_reprint_view(): void
    {
        $admin = $this->admin();
        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => 'Clickable Row Buyer', 'total_amount' => 25,
            'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('class="cs-row cs-row-clickable"', false);
        $response->assertSee('data-order-url="' . route('admin.tickets.print', $orderId) . '"', false);
    }

    /**
     * The summary (Opening Balance / Cash Received / Expected in Till) is now a compact
     * label-left, value-right list instead of three separate icon tiles.
     */
    public function test_the_summary_is_a_compact_left_right_list_not_icon_tiles(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('class="cs-summary-row"', false);
        $response->assertSee('Opening Balance');
        $response->assertSee('Expected in Till');
        // The old icon-tile markup is gone.
        $response->assertDontSee('class="cs-tile"', false);
    }

    public function test_a_ticket_order_row_has_a_view_icon_alongside_the_print_icon(): void
    {
        $admin = $this->admin();
        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => 'View Icon Buyer', 'total_amount' => 15,
            'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('title="View Order"', false);
        $response->assertSee('bi-eye-fill', false);
        $response->assertSee('title="Print Receipt"', false);
        $response->assertSee(route('admin.tickets.print', $orderId), false);
    }

    public function test_the_settle_till_form_uses_compact_audit_style_inputs(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('class="cs-audit-input-wrap"', false);
        $response->assertSee('cs-audit-input settle-till-count', false);
        $response->assertSee('cs-audit-input settle-amount-banked', false);
    }

    public function test_variance_and_carried_forward_show_zero_not_a_dash_before_input(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('class="cs-audit-stat-value settle-variance-display"', false);
        $response->assertSee('class="cs-audit-stat-value settle-carry-display"', false);
        $response->assertDontSee('settle-variance-display">—', false);
        $response->assertDontSee('settle-carry-display">—', false);
    }

    /**
     * Clicking a ticket order row (or its View icon) opens a popup — order details on the
     * left (from the row's own data-* attributes) and the real receipt embedded on the
     * right — rather than navigating straight to the print page.
     */
    public function test_a_ticket_order_row_opens_a_view_popup_with_an_embedded_receipt(): void
    {
        $admin = $this->admin();
        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => 'Popup Buyer', 'total_amount' => 60,
            'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertSee('data-order-id="' . $orderId . '"', false);
        $response->assertSee('data-order-customer="Popup Buyer"', false);
        $response->assertSee('cs-view-order-btn', false);
        $response->assertSee('id="csOrderModalFrame_', false);
        $response->assertSee('cs-order-receipt-col', false);
    }

    public function test_cash_received_by_ticket_type_breakdown_is_removed(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $response->assertDontSee('Cash Received by Ticket Type');
    }

    /**
     * The period's cash summary and the Settle Till form now share one combined
     * "Settlement" card at the bottom of the pane (summary left, form right), below
     * Current Period Transactions rather than above it.
     */
    public function test_transactions_come_before_the_combined_settlement_panel(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.cash-settlement.pane', [
            'scope' => 'tickets', 'context' => 'pos',
        ]));

        $response->assertOk();
        $content = $response->getContent();
        $transactionsPos = strpos($content, 'Current Period Transactions');
        $settlementPos = strpos($content, '>Settlement<');
        $this->assertNotFalse($transactionsPos);
        $this->assertNotFalse($settlementPos);
        $this->assertLessThan($settlementPos, $transactionsPos, 'Current Period Transactions should render before the combined Settlement panel.');
    }
}
