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
     * The top summary (Opening Balance / Cash Received / Expected in Till) is now a compact
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
}
