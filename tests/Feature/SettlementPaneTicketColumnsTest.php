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
}
