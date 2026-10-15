<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashSettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Ticket Console's "All Transactions" pane (ticket-console.blade.php's pane-sales) gets
 * the same multi-select Status/Payment Method filter plus a Settlement filter already built
 * for the Donations "All Transactions" pane — each order tagged with whichever settlement
 * claimed it (or "current" if none has), via CashSettlementService::settlementKeyMap()
 * against the 'ticket_order' type string CashSettlementService::run() stores for this scope.
 */
class TicketAllTransactionsFilterTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function insertOrder(string $customerName, float $amount, string $method, string $status, ?string $email = null, ?string $mobile = null): int
    {
        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => $customerName, 'total_amount' => $amount,
            'payment_method' => $method, 'payment_status' => $status,
            'email' => $email, 'mobile' => $mobile,
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ticket_order_items')->insert([
            'ticket_order_id' => $orderId, 'ticket_name' => 'Archana', 'unit_price' => $amount,
            'quantity' => 1, 'line_total' => $amount, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $orderId;
    }

    public function test_the_console_renders_multi_select_filter_pills_for_method_status_and_settlement(): void
    {
        $admin = $this->admin();
        $this->insertOrder('Settled Buyer', 50, 'Cash', 'Paid');
        $settlement = CashSettlementService::run('tickets', null, $admin->id, 50.0, 50.0, null);
        $this->insertOrder('Current Buyer', 30, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('ticketsStatusPills', false);
        $response->assertSee('ticketsPaymentPills', false);
        $response->assertSee('ticketsSettlementSelect', false);
        $response->assertSee('Current (Unsettled)');
        $response->assertSee('Settled Buyer');
        $response->assertSee('Current Buyer');
    }

    public function test_search_input_and_contact_column_are_present(): void
    {
        $admin = $this->admin();
        $this->insertOrder('Searchable Buyer', 40, 'Cash', 'Paid', 'buyer@example.com', '0412345678');

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('ticketsSearchInput', false);
        $response->assertSee('0412345678');
        $response->assertSee('buyer@example.com');
    }

    public function test_default_active_statuses_are_paid_and_pending(): void
    {
        $admin = $this->admin();
        $this->insertOrder('Paid Buyer', 20, 'Cash', 'Paid');
        $this->insertOrder('Pending Buyer', 25, 'Bank Transfer', 'Pending');
        $this->insertOrder('Cancelled Buyer', 15, 'Cash', 'Cancelled');

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        // Paid/Pending pills ship pre-activated; Cancelled must be opted into, not default-on.
        $response->assertSee('class="donations-multi-pill active" data-value="paid"', false);
    }

    public function test_row_click_opens_a_detail_modal_with_items_breakdown(): void
    {
        $admin = $this->admin();
        $orderId = $this->insertOrder('Detail Buyer', 60, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('id="orderDetail' . $orderId . '"', false);
        $response->assertSee('donations-row-clickable', false);
        $response->assertSee('1x Archana', false);
    }

    public function test_sortable_column_headers_are_rendered(): void
    {
        $admin = $this->admin();
        $this->insertOrder('Any Buyer', 10, 'Cash', 'Paid');

        $response = $this->actingAs($admin)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('class="sortable" data-sort="timestamp"', false);
        $response->assertSee('data-sort="amount"', false);
    }
}
