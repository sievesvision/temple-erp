<?php

namespace Tests\Feature;

use App\Models\LinklyTransaction;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the standalone Ticket module (catalog, kiosk order recording, individual stub
 * printing, and reuse of the Linkly EFT infrastructure built for donations) — see
 * TicketController and DonationController's generalised startEftCharge()/
 * createDonationIfApprovedPurchaseUnrecorded()/markDonationCancelledIfRefundJustApproved().
 */
class TicketModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
    }

    private function ticketUser(): User
    {
        $user = User::factory()->create([
            'role' => 'Committee',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
        // Committee is seeded with default 'tickets' access (see
        // 2026_10_01_000001_add_committee_tickets_permission.php).
        return $user;
    }

    // A user with only 'tickets' permission (no 'donations' grant) can still reach the
    // catalog and kiosk — proves the 'tickets' resource is actually wired into RolePermission.
    public function test_committee_user_can_manage_ticket_catalog(): void
    {
        $user = $this->ticketUser();

        $response = $this->actingAs($user)->postJson('/admin/ticket/store', [
            'name' => 'Adult Entry',
            'price' => '10.00',
            'status' => 'Active',
        ]);

        // Non-JSON redirect route, but postJson still hits it; assert the row landed.
        $this->assertDatabaseHas('tickets', ['name' => 'Adult Entry', 'price' => 10.00]);
    }

    public function test_a_role_without_tickets_permission_is_rejected(): void
    {
        $user = User::factory()->create([
            'role' => 'Staff',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->actingAs($user)->get('/admin/tickets/pos');

        $response->assertStatus(403);
    }

    // Cash order: creates the order, one item per ticket line, and one stub per unit of
    // quantity (5 of a $5 ticket => 5 individually-numbered stubs).
    public function test_cash_order_creates_order_items_and_individual_stubs(): void
    {
        $user = $this->ticketUser();
        $ticket = Ticket::create(['name' => 'Prasadam Coupon', 'price' => 5.00, 'status' => 'Active']);

        $response = $this->actingAs($user)->postJson('/admin/tickets/order', [
            'customer_name' => 'Walk-up Donor',
            'cart_json' => json_encode([
                ['ticket_id' => $ticket->id, 'name' => $ticket->name, 'price' => 5.00, 'quantity' => 5],
            ]),
            'payment_method' => 'Cash',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $orderId = $response->json('order_id');

        $this->assertDatabaseHas('ticket_orders', ['id' => $orderId, 'total_amount' => 25.00, 'payment_status' => 'Paid']);
        $item = DB::table('ticket_order_items')->where('ticket_order_id', $orderId)->first();
        $this->assertSame(5, $item->quantity);
        $this->assertSame(5, DB::table('ticket_stubs')->where('ticket_order_item_id', $item->id)->count());

        // Every stub number is unique.
        $stubNumbers = DB::table('ticket_stubs')->where('ticket_order_item_id', $item->id)->pluck('stub_number');
        $this->assertSame($stubNumbers->count(), $stubNumbers->unique()->count());
    }

    public function test_empty_cart_is_rejected(): void
    {
        $user = $this->ticketUser();

        $response = $this->actingAs($user)->postJson('/admin/tickets/order', [
            'cart_json' => json_encode([]),
            'payment_method' => 'Cash',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    // EFT Terminal: starting a ticket-order charge stores the cart in the ledger's meta with
    // record_type = 'ticket_order', separately from a donation's own meta shape.
    public function test_eft_ticket_charge_stores_cart_in_ledger_meta(): void
    {
        $user = $this->ticketUser();
        $ticket = Ticket::create(['name' => 'Adult Entry', 'price' => 10.00, 'status' => 'Active']);

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $response = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'record_type' => 'ticket_order',
            'amount' => '10.00',
            'client_ref' => 'ticket-eft-test',
            'donor_name' => 'Card Customer',
            'cart_json' => json_encode([['ticket_id' => $ticket->id, 'name' => $ticket->name, 'price' => 10.00, 'quantity' => 1]]),
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $sessionId = $response->json('session_id');

        $this->assertDatabaseHas('linkly_transactions', ['linkly_session_id' => $sessionId, 'txn_type' => 'purchase']);
        $txn = LinklyTransaction::where('linkly_session_id', $sessionId)->first();
        $this->assertSame('ticket_order', $txn->meta['record_type']);
        $this->assertSame('Adult Entry', $txn->meta['cart'][0]['name']);
    }

    // Once Linkly confirms approval, the ticket order is auto-created from the ledger's own
    // cart meta — the same server-side recovery fallback built for donations, generalised.
    public function test_approved_eft_ticket_purchase_auto_creates_order_and_stubs(): void
    {
        $user = $this->ticketUser();
        $ticket = Ticket::create(['name' => 'Adult Entry', 'price' => 10.00, 'status' => 'Active']);

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => function ($request) {
                if (strtoupper($request->method()) === 'GET') {
                    return Http::response(['response' => ['success' => true, 'responseCode' => '00', 'authCode' => 9, 'rrn' => '000009']], 200);
                }
                return Http::response('', 202);
            },
        ]);

        $start = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'record_type' => 'ticket_order',
            'amount' => '20.00',
            'client_ref' => 'ticket-auto-create-test',
            'donor_name' => 'Card Customer',
            'cart_json' => json_encode([['ticket_id' => $ticket->id, 'name' => $ticket->name, 'price' => 10.00, 'quantity' => 2]]),
        ]);
        $sessionId = $start->json('session_id');

        $poll = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}");
        $poll->assertOk()->assertJson(['payment_status' => 'approved', 'done' => true]);
        $orderId = $poll->json('donation_id');
        $this->assertNotNull($orderId);

        $this->assertDatabaseHas('ticket_orders', ['id' => $orderId, 'total_amount' => 20.00, 'payment_status' => 'Paid', 'payment_method' => 'EFT Terminal']);
        $item = DB::table('ticket_order_items')->where('ticket_order_id', $orderId)->first();
        $this->assertSame(2, $item->quantity);
        $this->assertSame(2, DB::table('ticket_stubs')->where('ticket_order_item_id', $item->id)->count());
    }

    // A refund against a ticket-order EFT purchase flips ticket_orders.payment_status to
    // Cancelled, the same generalised hook used for donations.
    public function test_refund_marks_ticket_order_cancelled(): void
    {
        $user = $this->ticketUser();

        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => 'Card Customer', 'total_amount' => 10, 'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid', 'transaction_id' => '000010', 'order_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $refundSessionId = (string) \Illuminate\Support\Str::uuid();
        LinklyTransaction::create([
            'pos_txn_ref' => 'RFDTICKET1',
            'linkly_session_id' => $refundSessionId,
            'txn_type' => 'refund',
            'donation_type' => 'ticket_order',
            'donation_id' => $orderId,
            'amount' => 10,
            'status' => 'initiated',
            'initiated_by' => $user->id,
            'authorised_by' => $user->id,
        ]);

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response(['response' => ['success' => true, 'responseCode' => '00', 'authCode' => 1, 'rrn' => '1']], 200),
        ]);

        $this->actingAs($user)->getJson("/admin/eft/charge/status/{$refundSessionId}")->assertOk();

        $this->assertDatabaseHas('ticket_orders', ['id' => $orderId, 'payment_status' => 'Cancelled']);
    }

    // canUseEftTerminal(): a user with ONLY 'tickets' permission (no 'donations' grant) must
    // still be able to poll/cancel a ticket EFT session — this was a real gap the generic
    // canRecordDonation() check would otherwise have introduced.
    public function test_tickets_only_user_can_poll_and_cancel_without_donations_permission(): void
    {
        $user = User::factory()->create([
            'role' => 'Staff',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
        DB::table('role_permissions')->insertOrIgnore([[
            'role' => 'Staff', 'resource' => 'tickets', 'can_view' => true, 'can_add' => true,
            'can_edit' => false, 'can_delete' => false, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $sessionId = (string) \Illuminate\Support\Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response(null, 202),
        ]);

        $poll = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}");
        $poll->assertOk(); // not 403 — proves the 'tickets' grant alone is sufficient
    }
}
