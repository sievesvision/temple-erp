<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\SciTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Purchase creation, polling through to a final outcome, and the transaction-recovery
 * override flow — covering the certification checklist's "Transactions" and "Transaction
 * recovery" sections (Approved/Declined/Cancelled handling, NO_ACTIVE_PAIRINGS_FOUND,
 * TRANSACTION_REFUSED, DEVICE_NOT_CONNECTED, and the manual override when polling can't
 * get an answer).
 */
class CbaSciTransactionTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-txn-' . uniqid(),
            'label' => 'SCI Txn Terminal',
            'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(),
            'is_default' => true,
            'sci_pairing_id' => 'pid_txn',
            'sci_key_id' => 'kid_txn',
            'sci_signing_secret_part_b' => 'secret-b',
            'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
    }

    public function test_starting_a_purchase_persists_a_pending_transaction(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'transaction_id' => 'txn_123', 'version' => 1, 'status' => 'PENDING', 'message' => 'Processing',
        ]], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 25.50, 'client_ref' => 'ref-1', 'terminal_id' => $terminal->id,
            'donor_name' => 'Jane Donor', 'purpose' => 'General Donation',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'transaction_id' => 'txn_123', 'status' => 'PENDING']);
        $this->assertDatabaseHas('sci_transactions', ['client_ref' => 'ref-1', 'sci_transaction_id' => 'txn_123', 'status' => 'PENDING', 'amount' => 25.5]);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/v1/transactions')
                && json_decode($request->body(), true)['purchase_details']['purchase_amount'] === 2550;
        });
    }

    public function test_polling_to_finalised_approved_creates_the_donation(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $txn = SciTransaction::create([
            'client_ref' => 'ref-2', 'sci_transaction_id' => 'txn_456', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 40, 'status' => 'PENDING',
            'meta' => ['record_type' => 'donation', 'donor_name' => 'Amit Devotee', 'email' => 'amit@example.com', 'purpose' => 'General Donation'],
            'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'status' => 'FINALISED', 'version' => 2, 'result_financial_status' => 'APPROVED',
            'result_amounts' => ['purchase_amount' => 4000], 'merchant_receipt' => 'MERCHANT COPY', 'customer_receipt' => 'CUSTOMER COPY',
        ]], 200)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => true, 'success' => true, 'result_financial_status' => 'APPROVED']);
        $donationId = $response->json('donation_id');
        $this->assertNotNull($donationId);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donor_name' => 'Amit Devotee', 'amount' => 40, 'payment_method' => 'EFT Terminal', 'payment_status' => 'Paid']);

        $txn->refresh();
        $this->assertSame('FINALISED', $txn->status);
        $this->assertSame($donationId, $txn->donation_id);
    }

    public function test_polling_to_finalised_declined_does_not_create_a_donation(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $txn = SciTransaction::create([
            'client_ref' => 'ref-3', 'sci_transaction_id' => 'txn_789', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 15, 'status' => 'PENDING',
            'meta' => ['record_type' => 'donation', 'donor_name' => 'Declined Donor'],
            'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'status' => 'FINALISED', 'version' => 2, 'result_financial_status' => 'DECLINED', 'message' => 'Card declined',
        ]], 200)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => true, 'success' => false, 'result_financial_status' => 'DECLINED', 'donation_id' => null]);
        $this->assertDatabaseMissing('donations_without_logins', ['donor_name' => 'Declined Donor']);
    }

    public function test_no_active_pairings_found_is_surfaced_when_starting_a_purchase(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 10, 'client_ref' => 'ref-4', 'terminal_id' => $terminal->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('not currently paired', $response->json('message'));
    }

    public function test_transaction_refused_terminal_busy_is_surfaced(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'transaction_refused']], 409)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 10, 'client_ref' => 'ref-5', 'terminal_id' => $terminal->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('busy with another transaction', $response->json('message'));
    }

    public function test_device_not_connected_is_surfaced_while_polling(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-6', 'sci_transaction_id' => 'txn_dnc', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'PENDING', 'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'device_not_connected']], 424)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => true, 'success' => false, 'status' => 'DEVICE_NOT_CONNECTED']);
    }

    public function test_transaction_not_found_within_timeout_keeps_polling(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-7', 'sci_transaction_id' => 'txn_timeout', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'PENDING', 'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'transaction_not_found_within_timeout']], 404)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => false]);
        $txn->refresh();
        $this->assertSame('PENDING', $txn->status);
    }

    public function test_override_approved_records_the_donation_when_polling_never_resolves(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-8', 'sci_transaction_id' => 'txn_override', 'sci_version' => 3,
            'eft_terminal_id' => $terminal->id, 'amount' => 60, 'status' => 'PENDING',
            'meta' => ['record_type' => 'donation', 'donor_name' => 'Override Donor'],
            'initiated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.override', $txn->sci_transaction_id), [
            'outcome' => 'approved',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $donationId = $response->json('donation_id');
        $this->assertNotNull($donationId);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'donor_name' => 'Override Donor', 'amount' => 60]);

        $txn->refresh();
        $this->assertSame('FINALISED', $txn->status);
        $this->assertSame('APPROVED', $txn->result_financial_status);
        $this->assertSame('approved', $txn->meta['override']['outcome']);
    }

    public function test_override_unresolved_does_not_record_a_donation(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-9', 'sci_transaction_id' => 'txn_override_no', 'sci_version' => 3,
            'eft_terminal_id' => $terminal->id, 'amount' => 60, 'status' => 'PENDING',
            'meta' => ['record_type' => 'donation', 'donor_name' => 'Unresolved Donor'],
            'initiated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.override', $txn->sci_transaction_id), [
            'outcome' => 'unresolved',
        ]);

        $response->assertOk();
        $this->assertNull($response->json('donation_id'));
        $this->assertDatabaseMissing('donations_without_logins', ['donor_name' => 'Unresolved Donor']);

        $txn->refresh();
        $this->assertSame('UNKNOWN', $txn->result_financial_status);
    }

    public function test_override_does_not_run_once_a_real_result_already_arrived(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-10', 'sci_transaction_id' => 'txn_already_done', 'sci_version' => 3,
            'eft_terminal_id' => $terminal->id, 'amount' => 60, 'status' => 'FINALISED',
            'result_financial_status' => 'DECLINED',
            'meta' => ['record_type' => 'donation', 'donor_name' => 'Already Declined'],
            'initiated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.override', $txn->sci_transaction_id), [
            'outcome' => 'approved',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('donations_without_logins', ['donor_name' => 'Already Declined']);
        $txn->refresh();
        $this->assertSame('DECLINED', $txn->result_financial_status);
    }
}
