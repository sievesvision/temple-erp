<?php

namespace Tests\Feature;

use App\Models\LinklyTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the Linkly EFT terminal live-status feature: the public webhook that receives
 * Linkly's async "display" postbacks, and the authenticated poll endpoint the POS page's
 * browser JS calls every ~1.2s. See app/Services/LinklyEftService.php and
 * DonationController::startEftCharge()/pollEftCharge()/linklyWebhook().
 */
class LinklyWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        Setting::set('linkly_secret_sandbox', 'test-secret');
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'Admin',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
    }

    private function seedWebhookToken(string $sessionId, string $token): void
    {
        Cache::put("linkly_webhook_token_{$sessionId}", $token, 300);
    }

    private function fakeLinklyToken(): void
    {
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
        ]);
    }

    // 9. Unauthorised status request — no logged-in user at all.
    public function test_poll_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/admin/eft/charge/status/' . Str::uuid());

        $response->assertStatus(401);
    }

    // 9 (continued) — an Event Coordinator has no general RolePermission grid grant at all
    // (their access is purely per-event, via the event_coordinators pivot) and no event_id
    // is supplied here, so canRecordDonation() itself must reject it. This specifically
    // exercises DonationController's own authorization, distinct from the route-level
    // role:Admin,Committee,Event Coordinator middleware that runs before it.
    public function test_poll_endpoint_rejects_role_without_donation_permission(): void
    {
        $coordinator = User::factory()->create([
            'role' => 'Event Coordinator',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->actingAs($coordinator)->getJson('/admin/eft/charge/status/' . Str::uuid());

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Unauthorized access.']);
    }

    // Webhook auth: missing bearer token entirely.
    public function test_webhook_rejects_missing_bearer_token(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'correct-token');

        $response = $this->postJson('/admin/eft/webhook/display', [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['SWIPE CARD']],
        ]);

        $response->assertStatus(401);
    }

    // Webhook auth: wrong bearer token.
    public function test_webhook_rejects_wrong_bearer_token(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'correct-token');

        $response = $this->postJson('/admin/eft/webhook/display', [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['SWIPE CARD']],
        ], ['Authorization' => 'Bearer wrong-token']);

        $response->assertStatus(401);
    }

    public function test_webhook_rejects_payload_missing_session_id(): void
    {
        $response = $this->postJson('/admin/eft/webhook/display', [
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['SWIPE CARD']],
        ], ['Authorization' => 'Bearer whatever']);

        $response->assertStatus(400);
    }

    // 1 & 5. Single meaningful line + CancelKeyFlag, surfaced correctly via the poll endpoint.
    public function test_display_notification_is_visible_via_poll_with_controls(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'webhook-token-abc');

        // This is the exact shape captured from real Linkly sandbox traffic — PascalCase,
        // fixed-width, space-padded DisplayText.
        $webhook = $this->postJson('/admin/eft/webhook/display', [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => [
                'NumberOfLines' => 2,
                'LineLength' => 20,
                'DisplayText' => ['     SWIPE CARD     ', '                    '],
                'CancelKeyFlag' => true,
                'AcceptYesKeyFlag' => false,
                'DeclineNoKeyFlag' => false,
                'AuthoriseKeyFlag' => false,
                'OKKeyFlag' => false,
            ],
        ], ['Authorization' => 'Bearer webhook-token-abc']);

        $webhook->assertOk()->assertExactJson(['received' => true]);

        $this->fakeLinklyToken();
        Http::fake(['*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200), '*/sessions/*' => Http::response(null, 202)]);

        $poll = $this->actingAs($this->adminUser())->getJson("/admin/eft/charge/status/{$sessionId}");

        $poll->assertOk();
        $poll->assertJson([
            'payment_status' => 'in_progress',
            'done' => false,
            'display' => ['SWIPE CARD'],
            'display_text' => 'SWIPE CARD',
        ]);
        $poll->assertJsonPath('controls.cancel', true);
        $poll->assertJsonPath('controls.ok', false);
    }

    // 2. Two meaningful lines both preserved end-to-end through the webhook + poll.
    public function test_two_line_display_preserves_both_lines(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'tok');

        $this->postJson('/admin/eft/webhook/display', [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['SELECT ACCOUNT', 'SAV CHQ CR']],
        ], ['Authorization' => 'Bearer tok'])->assertOk();

        $this->assertSame(['SELECT ACCOUNT', 'SAV CHQ CR'], Cache::get("linkly_display_{$sessionId}"));
    }

    // 3. Blank display lines are dropped before they ever reach the cache.
    public function test_blank_lines_are_not_stored(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'tok');

        $this->postJson('/admin/eft/webhook/display', [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['', '   ', 'PROCESSING']],
        ], ['Authorization' => 'Bearer tok'])->assertOk();

        $this->assertSame(['PROCESSING'], Cache::get("linkly_display_{$sessionId}"));
    }

    // 4. Duplicate notifications are idempotent — the second identical postback just
    // overwrites with the same value, never duplicating or erroring.
    public function test_duplicate_display_notification_is_idempotent(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'tok');
        $payload = [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['ENTER PIN'], 'CancelKeyFlag' => true],
        ];

        $this->postJson('/admin/eft/webhook/display', $payload, ['Authorization' => 'Bearer tok'])->assertOk();
        $this->postJson('/admin/eft/webhook/display', $payload, ['Authorization' => 'Bearer tok'])->assertOk();

        $this->assertSame(['ENTER PIN'], Cache::get("linkly_display_{$sessionId}"));
    }

    // 6. Approved final response — resolved from the authoritative GET poll, not any
    // display text.
    public function test_approved_final_response_via_poll(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response([
                'responseType' => 'transaction',
                'response' => ['success' => true, 'responseCode' => '00', 'authCode' => 123456, 'rrn' => '000001000009', 'txnRef' => 'TESTREF'],
            ], 200),
        ]);

        $poll = $this->actingAs($this->adminUser())->getJson("/admin/eft/charge/status/{$sessionId}");

        $poll->assertOk();
        $poll->assertJson(['payment_status' => 'approved', 'done' => true, 'success' => true, 'rrn' => '000001000009']);
    }

    // 7. Declined final response.
    public function test_declined_final_response_via_poll(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response([
                'responseType' => 'transaction',
                'response' => ['success' => false, 'responseCode' => '05', 'responseText' => 'DO NOT HONOUR'],
            ], 200),
        ]);

        $poll = $this->actingAs($this->adminUser())->getJson("/admin/eft/charge/status/{$sessionId}");

        $poll->assertOk();
        $poll->assertJson(['payment_status' => 'declined', 'done' => true, 'success' => false]);
    }

    // 8. A display notification followed immediately by the final response — the final
    // result (done: true) must take priority over the lingering display text, but the
    // last-seen display text is still returned alongside it for the modal's own use.
    public function test_display_notification_then_final_response(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'tok');

        $this->postJson('/admin/eft/webhook/display', [
            'SessionId' => $sessionId,
            'ResponseType' => 'display',
            'Response' => ['DisplayText' => ['PROCESSING']],
        ], ['Authorization' => 'Bearer tok'])->assertOk();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*' => Http::response([
                'responseType' => 'transaction',
                'response' => ['success' => true, 'responseCode' => '00', 'authCode' => 1, 'rrn' => '1', 'txnRef' => 'X'],
            ], 200),
        ]);

        $poll = $this->actingAs($this->adminUser())->getJson("/admin/eft/charge/status/{$sessionId}");

        $poll->assertOk();
        $poll->assertJson(['done' => true, 'payment_status' => 'approved', 'display' => ['PROCESSING']]);
    }

    // "transaction"-type postbacks (which can carry card data) must never populate the
    // display cache — only "display" type does.
    public function test_non_display_postback_does_not_touch_display_cache(): void
    {
        $sessionId = (string) Str::uuid();
        $this->seedWebhookToken($sessionId, 'tok');

        $response = $this->postJson('/admin/eft/webhook/transaction', [
            'SessionId' => $sessionId,
            'ResponseType' => 'transaction',
            'Response' => ['Success' => true, 'Pan' => '4111111111111111'],
        ], ['Authorization' => 'Bearer tok']);

        $response->assertOk();
        $this->assertNull(Cache::get("linkly_display_{$sessionId}"));
    }

    // Cancel endpoint: unauthenticated requests are rejected before ever reaching Linkly.
    public function test_cancel_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->postJson('/admin/eft/charge/cancel/' . Str::uuid());

        $response->assertStatus(401);
    }

    // Cancel endpoint: a role with no donation permission and no event_id is rejected, same
    // authorization rule as starting/polling a charge.
    public function test_cancel_endpoint_rejects_role_without_donation_permission(): void
    {
        $coordinator = User::factory()->create([
            'role' => 'Event Coordinator',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->actingAs($coordinator)->postJson('/admin/eft/charge/cancel/' . Str::uuid());

        $response->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Unauthorized access.']);
    }

    // Cancel endpoint: an authorized user's cancel click sends a sendkey "0" request to
    // Linkly and reports success back to the browser.
    public function test_cancel_endpoint_sends_cancel_key_to_linkly(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/sendkey*' => Http::response(['response' => ['success' => true]], 200),
        ]);

        $response = $this->actingAs($this->adminUser())->postJson("/admin/eft/charge/cancel/{$sessionId}");

        $response->assertOk()->assertJson(['success' => true]);

        Http::assertSent(function ($request) use ($sessionId) {
            return str_contains($request->url(), "/sessions/{$sessionId}/sendkey")
                && $request['Request']['Key'] === '0';
        });
    }

    // Cancel endpoint: if Linkly rejects the sendkey (e.g. the transaction already moved
    // past the point where a cancel is possible), the endpoint reports failure rather than
    // pretending it worked.
    public function test_cancel_endpoint_reports_failure_when_linkly_rejects_it(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/sendkey*' => Http::response(null, 409),
        ]);

        $response = $this->actingAs($this->adminUser())->postJson("/admin/eft/charge/cancel/{$sessionId}");

        $response->assertOk()->assertJson(['success' => false]);
    }

    // sendEftKey(): each semantic key name maps to the correct Linkly sendkey code, and an
    // unrecognised name is rejected before ever reaching Linkly.
    public function test_send_key_endpoint_maps_semantic_names_to_linkly_codes(): void
    {
        $sessionId = (string) Str::uuid();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/sendkey*' => Http::response(['response' => ['success' => true]], 200),
        ]);
        $user = $this->adminUser();

        foreach (['ok' => '1', 'yes' => '1', 'no' => '2', 'authorise' => '3'] as $name => $code) {
            $response = $this->actingAs($user)->postJson("/admin/eft/charge/sendkey/{$sessionId}", ['key' => $name]);
            $response->assertOk()->assertJson(['success' => true]);

            Http::assertSent(function ($request) use ($sessionId, $code) {
                return str_contains($request->url(), "/sessions/{$sessionId}/sendkey")
                    && $request['Request']['Key'] === $code;
            });
        }
    }

    public function test_send_key_endpoint_rejects_unrecognised_key_names(): void
    {
        $sessionId = (string) Str::uuid();

        $response = $this->actingAs($this->adminUser())->postJson("/admin/eft/charge/sendkey/{$sessionId}", ['key' => 'cancel']);

        $response->assertStatus(422);
    }

    public function test_send_key_endpoint_rejects_unauthorised_users(): void
    {
        $sessionId = (string) Str::uuid();
        $coordinator = User::factory()->create([
            'role' => 'Event Coordinator',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);

        $response = $this->actingAs($coordinator)->postJson("/admin/eft/charge/sendkey/{$sessionId}", ['key' => 'ok']);

        $response->assertStatus(403);
    }

    // A button click that arrives after the transaction has already resolved (a display
    // notification's key flag was still showing when the operator clicked, but Linkly
    // resolved the transaction in the same ~1.2s poll window) gets a clear "already
    // finished" message from our own already-synced ledger, instead of forwarding a stale
    // click to Linkly and surfacing its generic, confusing rejection.
    public function test_send_key_rejects_locally_once_ledger_shows_transaction_already_terminal(): void
    {
        $sessionId = (string) Str::uuid();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTALREADY1',
            'linkly_session_id' => $sessionId,
            'txn_type' => 'purchase',
            'amount' => 10,
            'status' => 'approved',
            'initiated_by' => $this->adminUser()->id,
        ]);

        $response = $this->actingAs($this->adminUser())->postJson("/admin/eft/charge/sendkey/{$sessionId}", ['key' => 'ok']);

        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('already finished', $response->json('message'));
        Http::assertNothingSent();
    }

    public function test_cancel_rejects_locally_once_ledger_shows_transaction_already_terminal(): void
    {
        $sessionId = (string) Str::uuid();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTALREADY2',
            'linkly_session_id' => $sessionId,
            'txn_type' => 'purchase',
            'amount' => 10,
            'status' => 'declined',
            'initiated_by' => $this->adminUser()->id,
        ]);

        $response = $this->actingAs($this->adminUser())->postJson("/admin/eft/charge/cancel/{$sessionId}");

        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('already finished', $response->json('message'));
        Http::assertNothingSent();
    }
}
