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

        // mx51's real field is 'id', not 'transaction_id' (see CbaSciService::createTransaction()'s
        // docblock) — this mock previously used the wrong key, matching the bug it should
        // have caught rather than mx51's actual response shape.
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_123', 'version' => 1, 'status' => 'PENDING', 'message' => 'Processing',
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

    // startPurchase() now proactively re-checks pairing-info before starting (mx51's own
    // certification checklist, SCIPAIRING10) — a terminal mx51 already considers unpaired is
    // now caught and self-healed by that pre-check, before ever reaching the transactions
    // endpoint at all. The surfaced message is mx51's own certification-required exact text
    // (SCITX01) — never a paraphrase, and never the terminal's own label, which could itself
    // carry mx51's name (as "mx51 Certification Terminal" did in this exact scenario) and leak
    // it into a customer-facing error.
    public function test_no_active_pairings_found_is_surfaced_when_starting_a_purchase(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 10, 'client_ref' => 'ref-4', 'terminal_id' => $terminal->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'No active pairings found']);
        $this->assertFalse($terminal->fresh()->isSciPaired());
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
        // A "nothing new yet" response must never overwrite the last CONFIRMED version — the
        // next poll has to re-request the exact same min_version, not silently advance past
        // it (see CbaSciService::pollTransaction()'s docblock on this).
        $this->assertSame(1, $txn->sci_version);
    }

    // mx51's own documented rule: the next request's min_version is the response's version
    // PLUS ONE, computed fresh from what the response actually said — never the client's own
    // running counter. Poll with sci_version=1 (so min_version=2 is requested); mx51 reports
    // back version 4 (having skipped 2 and 3); the NEXT poll must ask for min_version=5, not
    // 2 (self-incrementing from the stale local value) or 5-but-then-drift over more polls.
    public function test_poll_computes_the_next_min_version_from_the_actual_response_not_the_stale_local_value(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-version-skip', 'sci_transaction_id' => 'txn_version_skip', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'PENDING', 'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'version' => 4, 'status' => 'PENDING', 'message' => 'Waiting for card',
        ]], 200)]);

        $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'min_version=2'));
        $txn->refresh();
        $this->assertSame(4, $txn->sci_version);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'version' => 4, 'status' => 'PENDING', 'message' => 'Still waiting',
        ]], 200)]);
        $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'min_version=5'));
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

    // A stray/late poll on an already-finalised transaction (a manual override, or a normal
    // poll that already reached FINALISED) must never re-query mx51 at all — a late response
    // like DEVICE_NOT_CONNECTED could otherwise silently un-finalise an already-correct result
    // and reopen the exact stuck-popup/resume-loop class of bug this guards against.
    public function test_poll_never_re_queries_mx51_for_an_already_finalised_transaction(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $txn = SciTransaction::create([
            'client_ref' => 'ref-finalised-poll', 'sci_transaction_id' => 'txn_finalised_poll', 'sci_version' => 5,
            'eft_terminal_id' => $terminal->id, 'amount' => 60, 'status' => 'FINALISED',
            'result_financial_status' => 'UNKNOWN',
            'meta' => ['record_type' => 'donation', 'donor_name' => 'Manually Overridden Donor'],
            'initiated_by' => $admin->id,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => []], 424)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $txn->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => true, 'status' => 'FINALISED', 'result_financial_status' => 'UNKNOWN']);
        Http::assertNothingSent();

        $txn->refresh();
        $this->assertSame('FINALISED', $txn->status);
        $this->assertSame('UNKNOWN', $txn->result_financial_status);
    }

    // mx51's own Postman collection documents POST /v1/transactions/{id}/cancel — an earlier
    // version of this codebase incorrectly believed no such endpoint existed, so the Cancel
    // button never actually told the terminal anything.
    public function test_cancel_calls_the_real_cancel_endpoint(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        SciTransaction::create([
            'client_ref' => 'ref-cancel-1', 'sci_transaction_id' => 'txn_cancel_1', 'sci_version' => 2,
            'eft_terminal_id' => $terminal->id, 'amount' => 15, 'status' => 'PENDING', 'initiated_by' => $admin->id,
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => ['version' => 3]], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.cancel', 'txn_cancel_1'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        Http::assertSent(fn ($request) => $request->url() === 'https://sci-api.tenant.example/v1/transactions/txn_cancel_1/cancel'
            && $request->method() === 'POST');
    }

    public function test_cancel_requires_eft_terminal_permission(): void
    {
        // The route group's own role:... middleware gate (not this controller) is what
        // rejects a role like Devotee — it redirects rather than returning JSON, same as
        // every other endpoint in this group when called by a role outside the allowed list.
        $entryUser = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = $this->pairedTerminal();
        SciTransaction::create([
            'client_ref' => 'ref-cancel-2', 'sci_transaction_id' => 'txn_cancel_2', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 15, 'status' => 'PENDING',
        ]);

        $response = $this->actingAs($entryUser)->postJson(route('admin.cba-sci.charge.cancel', 'txn_cancel_2'));

        $response->assertRedirect();
    }

    public function test_cancel_reports_an_unreachable_terminal_but_does_not_error_out(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        SciTransaction::create([
            'client_ref' => 'ref-cancel-3', 'sci_transaction_id' => 'txn_cancel_3', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 15, 'status' => 'PENDING', 'initiated_by' => $admin->id,
        ]);
        Http::fake(['sci-api.tenant.example/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.cancel', 'txn_cancel_3'));

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    private function approvedPurchase(EftTerminal $terminal, float $amount = 40, array $overrides = []): SciTransaction
    {
        return SciTransaction::create(array_merge([
            'client_ref' => 'ref-purchase-' . uniqid(),
            'sci_transaction_id' => 'txn_purchase_' . uniqid(),
            'sci_version' => 3,
            'eft_terminal_id' => $terminal->id,
            'txn_type' => 'purchase',
            'amount' => $amount,
            'status' => 'FINALISED',
            'result_financial_status' => 'APPROVED',
        ], $overrides));
    }

    public function test_refund_starts_and_creates_a_refund_transaction_linked_to_the_original(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $original = $this->approvedPurchase($terminal, 40);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_refund_1', 'version' => 1, 'status' => 'PENDING', 'message' => 'Processing refund',
        ]], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-1',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'transaction_id' => 'txn_refund_1']);
        $this->assertDatabaseHas('sci_transactions', [
            'client_ref' => 'refund-ref-1', 'sci_transaction_id' => 'txn_refund_1', 'txn_type' => 'refund',
            'original_transaction_id' => $original->id, 'amount' => 40,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/transactions')
            && ($request['refund_details']['refund_amount'] ?? null) === 4000);
    }

    // mx51's own reference POS lets the operator pick which paired terminal a refund runs
    // through — it's not forced back through whatever terminal took the original payment.
    public function test_refund_can_be_processed_through_a_different_terminal_than_the_original(): void
    {
        $admin = $this->adminUser();
        $originalTerminal = $this->pairedTerminal();
        $otherTerminal = EftTerminal::create([
            'key' => 'sci-txn-other-' . uniqid(), 'label' => 'SCI Other Terminal', 'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(), 'sci_pairing_id' => 'pid_other', 'sci_key_id' => 'kid_other',
            'sci_signing_secret_part_b' => 'secret-other', 'sci_api_base_url' => 'https://sci-api-other.tenant.example',
        ]);
        $original = $this->approvedPurchase($originalTerminal, 40);

        Http::fake(['sci-api-other.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_refund_other', 'version' => 1, 'status' => 'PENDING', 'message' => 'Processing refund',
        ]], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-other', 'terminal_id' => $otherTerminal->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('sci_transactions', [
            'sci_transaction_id' => 'txn_refund_other', 'txn_type' => 'refund', 'eft_terminal_id' => $otherTerminal->id,
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sci-api-other.tenant.example'));
    }

    public function test_refund_rejects_an_unpaired_terminal(): void
    {
        $admin = $this->adminUser();
        $originalTerminal = $this->pairedTerminal();
        $unpairedTerminal = EftTerminal::create([
            'key' => 'sci-unpaired-' . uniqid(), 'label' => 'SCI Unpaired', 'provider' => 'cba_sci', 'pos_id' => (string) Str::uuid(),
        ]);
        $original = $this->approvedPurchase($originalTerminal, 40);
        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-unpaired', 'terminal_id' => $unpairedTerminal->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'That terminal is not currently paired.']);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('sci_transactions', ['client_ref' => 'refund-ref-unpaired']);
    }

    public function test_refund_rejects_a_non_mx51_terminal(): void
    {
        $admin = $this->adminUser();
        $originalTerminal = $this->pairedTerminal();
        $linklyTerminal = EftTerminal::create([
            'key' => 'linkly-' . uniqid(), 'label' => 'Linkly Terminal', 'provider' => 'linkly', 'pos_id' => (string) Str::uuid(),
        ]);
        $original = $this->approvedPurchase($originalTerminal, 40);
        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-linkly', 'terminal_id' => $linklyTerminal->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Select a valid mx51 terminal for this refund.']);
        Http::assertNothingSent();
    }

    public function test_refund_amount_cannot_exceed_the_original_purchase_amount(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $original = $this->approvedPurchase($terminal, 40);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40.01, 'client_ref' => 'refund-ref-2',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('sci_transactions', ['client_ref' => 'refund-ref-2']);
    }

    public function test_only_an_approved_purchase_can_be_refunded(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $pending = $this->approvedPurchase($terminal, 40, ['status' => 'PENDING', 'result_financial_status' => null]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $pending->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-3',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Only an approved purchase', $response->json('message'));
    }

    public function test_duplicate_refund_is_blocked_once_one_has_already_succeeded(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $original = $this->approvedPurchase($terminal, 40);
        SciTransaction::create([
            'client_ref' => 'refund-ref-existing', 'sci_transaction_id' => 'txn_refund_existing', 'sci_version' => 2,
            'eft_terminal_id' => $terminal->id, 'txn_type' => 'refund', 'amount' => 40,
            'status' => 'FINALISED', 'result_financial_status' => 'APPROVED', 'original_transaction_id' => $original->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-4',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('already been refunded', $response->json('message'));
    }

    public function test_a_refund_still_in_flight_blocks_another_attempt(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $original = $this->approvedPurchase($terminal, 40);
        SciTransaction::create([
            'client_ref' => 'refund-ref-inflight', 'sci_transaction_id' => 'txn_refund_inflight', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'txn_type' => 'refund', 'amount' => 40,
            'status' => 'PENDING', 'original_transaction_id' => $original->id,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-5',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('already in progress', $response->json('message'));
    }

    // Refunds are deliberately more tightly restricted than purchases (see
    // CbaSciController::canManageRefund()'s docblock) — Staff can start a purchase but not
    // authorise a refund, exactly mirroring the existing Linkly refund gate.
    public function test_refund_requires_admin_level_permission(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = $this->pairedTerminal();
        $original = $this->approvedPurchase($terminal, 40);

        $response = $this->actingAs($staff)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-6',
        ]);

        $response->assertStatus(403);
    }

    public function test_polling_a_refund_to_finalised_approved_marks_the_donation_cancelled(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $donationId = app(\App\Http\Controllers\DonationController::class)->insertGuestDonationRecord([
            'donor_name' => 'Refund Donor', 'event_id' => null, 'amount' => 40,
            'purpose' => 'General Donation', 'payment_method' => 'EFT Terminal', 'payment_status' => 'Paid',
            'transaction_id' => 'txn_purchase_refunded', 'donation_date' => now()->toDateString(),
        ]);

        $original = $this->approvedPurchase($terminal, 40, [
            'sci_transaction_id' => 'txn_purchase_refunded',
            'donation_type' => 'guest', 'donation_id' => $donationId,
        ]);

        $refund = SciTransaction::create([
            'client_ref' => 'refund-ref-7', 'sci_transaction_id' => 'txn_refund_poll', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'txn_type' => 'refund', 'amount' => 40, 'status' => 'PENDING',
            'original_transaction_id' => $original->id, 'donation_type' => 'guest', 'donation_id' => $donationId,
        ]);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'status' => 'FINALISED', 'version' => 2, 'result_financial_status' => 'APPROVED',
        ]], 200)]);

        $response = $this->actingAs($admin)->getJson(route('admin.cba-sci.charge.status', $refund->sci_transaction_id));

        $response->assertOk();
        $response->assertJson(['done' => true, 'success' => true, 'result_financial_status' => 'APPROVED']);
        $this->assertDatabaseHas('donations_without_logins', ['id' => $donationId, 'payment_status' => 'Cancelled']);
    }

    // mx51's own documented "supported on all transaction requests" receipt/signature fields
    // (see App\Services\EftReceiptSettings) — mx51's own recommended defaults are all off
    // except pos_auto_print_signature_receipt, which defaults on, so a merchant receipt for a
    // signature transaction is never silently skipped just because this was never configured.
    public function test_a_new_purchase_sends_mx51s_recommended_default_receipt_settings(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_receipt_defaults', 'version' => 1, 'status' => 'PENDING',
        ]], 200)]);

        $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 10, 'client_ref' => 'ref-receipt-defaults', 'terminal_id' => $terminal->id,
        ])->assertOk();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || !str_contains($request->url(), '/v1/transactions')) {
                return false;
            }
            $body = json_decode($request->body(), true);
            return $body['print_merchant_receipt'] === false
                && $body['prompt_customer_receipt'] === false
                && $body['verify_signature_on_terminal'] === false
                && $body['pos_auto_print_signature_receipt'] === true;
        });
    }

    public function test_receipt_settings_changes_are_reflected_on_the_next_purchase(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $this->actingAs($admin)->post(route('admin.eft-terminals.updateReceiptSettings'), [
            'print_merchant_receipt_on_terminal' => '1',
            'pos_auto_print_signature_receipt' => '0',
        ])->assertSessionHas('success');

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_receipt_custom', 'version' => 1, 'status' => 'PENDING',
        ]], 200)]);

        $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 10, 'client_ref' => 'ref-receipt-custom', 'terminal_id' => $terminal->id,
        ])->assertOk();

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || !str_contains($request->url(), '/v1/transactions')) {
                return false;
            }
            $body = json_decode($request->body(), true);
            return $body['print_merchant_receipt'] === true && $body['pos_auto_print_signature_receipt'] === false;
        });
    }

    // A refund is a transaction request too, per mx51's own "supported on all transaction
    // requests" wording — must carry the same fields, not just purchases.
    public function test_a_refund_also_sends_the_receipt_settings(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();
        $original = $this->approvedPurchase($terminal, 40);

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_refund_receipt', 'version' => 1, 'status' => 'PENDING',
        ]], 200)]);

        $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.refund', $original->sci_transaction_id), [
            'amount' => 40, 'client_ref' => 'refund-ref-receipt',
        ])->assertOk();

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return array_key_exists('print_merchant_receipt', $body) && array_key_exists('pos_auto_print_signature_receipt', $body);
        });
    }

    // The minimum transaction amount used to be a hardcoded "must be at least 1" — now
    // admin-configurable (see App\Services\EftTransactionLimits), same default as before so
    // nothing changes unless it's explicitly configured.
    public function test_starting_a_purchase_below_the_default_minimum_is_rejected(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 0.50, 'client_ref' => 'ref-below-min', 'terminal_id' => $terminal->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('sci_transactions', ['client_ref' => 'ref-below-min']);
    }

    public function test_lowering_the_minimum_transaction_amount_allows_a_smaller_purchase(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $this->actingAs($admin)->post(route('admin.eft-terminals.updateTransactionLimits'), [
            'minimum_transaction_amount' => '0.20',
        ])->assertSessionHas('success');

        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => [
            'id' => 'txn_below_old_min', 'version' => 1, 'status' => 'PENDING',
        ]], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 0.50, 'client_ref' => 'ref-lowered-min', 'terminal_id' => $terminal->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sci_transactions', ['client_ref' => 'ref-lowered-min']);
    }

    public function test_raising_the_minimum_transaction_amount_rejects_a_previously_valid_purchase(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedTerminal();

        $this->actingAs($admin)->post(route('admin.eft-terminals.updateTransactionLimits'), [
            'minimum_transaction_amount' => '5',
        ])->assertSessionHas('success');

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.charge.start'), [
            'amount' => 2, 'client_ref' => 'ref-raised-min', 'terminal_id' => $terminal->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('sci_transactions', ['client_ref' => 'ref-raised-min']);
    }
}
