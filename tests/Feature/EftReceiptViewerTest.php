<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\SciTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "soft copy" receipt viewer on the event/ticket consoles' All Transactions tab — mx51
 * stores the exact printed receipt text on sci_transactions.merchant_receipt/customer_receipt
 * once a transaction finalises (see CbaSciController::poll()), and this just surfaces whichever
 * copies exist for a given transaction, verbatim, in a "View Receipt" modal. Covers both an
 * approved purchase (a normal donation row) and a declined purchase/refund (an "orphan" row
 * with no donation record of its own) — a transaction with no stored receipt text gets no
 * button at all, since there would be nothing to show.
 */
class EftReceiptViewerTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function createEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Receipt Viewer Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sciTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-receipt-' . uniqid(), 'label' => 'Receipt Test Terminal', 'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(),
        ]);
    }

    public function test_an_approved_purchase_with_stored_receipts_shows_a_view_receipt_button(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $terminal = $this->sciTerminal();
        $eventId = $this->createEvent();
        $donationId = DB::table('donations_without_logins')->insertGetId([
            'donor_name' => 'Receipt Donor', 'amount' => 20, 'purpose' => 'General Donation', 'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid', 'event_id' => $eventId, 'donation_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $txn = SciTransaction::create([
            'client_ref' => 'receipt-approved-1', 'sci_transaction_id' => 'txn_receipt_approved_1',
            'event_id' => $eventId, 'eft_terminal_id' => $terminal->id, 'amount' => 20,
            'status' => 'FINALISED', 'result_financial_status' => 'APPROVED',
            'donation_type' => 'guest', 'donation_id' => $donationId,
            'merchant_receipt' => "MERCHANT COPY\nAMOUNT: AUD 20.00\nAPPROVED",
            'customer_receipt' => "CUSTOMER COPY\nAMOUNT: AUD 20.00\nAPPROVED",
        ]);

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertSee('viewSciReceipt(' . $txn->id . ')', false);
        $response->assertSee('MERCHANT COPY\\nAMOUNT: AUD 20.00\\nAPPROVED', false);
        $response->assertSee('CUSTOMER COPY\\nAMOUNT: AUD 20.00\\nAPPROVED', false);
    }

    public function test_a_declined_purchase_with_stored_receipts_still_shows_a_view_receipt_button(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $terminal = $this->sciTerminal();
        $eventId = $this->createEvent();
        // Declined, never became a donation — rendered as an "orphan" row, not a normal one.
        $txn = SciTransaction::create([
            'client_ref' => 'receipt-declined-1', 'sci_transaction_id' => 'txn_receipt_declined_1',
            'event_id' => $eventId, 'eft_terminal_id' => $terminal->id, 'amount' => 15,
            'status' => 'FINALISED', 'result_financial_status' => 'DECLINED',
            'merchant_receipt' => "MERCHANT COPY\nAMOUNT: AUD 15.00\nDECLINED",
        ]);

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertSee('viewSciReceipt(' . $txn->id . ')', false);
        $response->assertSee('MERCHANT COPY\\nAMOUNT: AUD 15.00\\nDECLINED', false);
    }

    public function test_a_transaction_with_no_stored_receipt_gets_no_receipt_button(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $terminal = $this->sciTerminal();
        $eventId = $this->createEvent();
        $txn = SciTransaction::create([
            'client_ref' => 'receipt-none-1', 'sci_transaction_id' => 'txn_receipt_none_1',
            'event_id' => $eventId, 'eft_terminal_id' => $terminal->id, 'amount' => 42,
            'status' => 'DEVICE_NOT_CONNECTED',
        ]);

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertDontSee('viewSciReceipt(' . $txn->id . ')', false);
    }

    public function test_ticket_console_shows_a_view_receipt_button_for_an_approved_order(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $terminal = $this->sciTerminal();
        $orderId = DB::table('ticket_orders')->insertGetId([
            'customer_name' => 'Receipt Buyer', 'total_amount' => 40, 'payment_method' => 'EFT Terminal',
            'payment_status' => 'Paid', 'order_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $txn = SciTransaction::create([
            'client_ref' => 'receipt-ticket-1', 'sci_transaction_id' => 'txn_receipt_ticket_1',
            'donation_type' => 'ticket_order', 'donation_id' => $orderId,
            'eft_terminal_id' => $terminal->id, 'amount' => 40,
            'status' => 'FINALISED', 'result_financial_status' => 'APPROVED',
            'merchant_receipt' => "MERCHANT COPY\nAMOUNT: AUD 40.00\nAPPROVED",
        ]);

        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('viewSciReceipt(' . $txn->id . ')', false);
    }
}
