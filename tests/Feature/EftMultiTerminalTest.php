<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\LinklyTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers running more than one EFT terminal at once — e.g. one station on the Ticket Kiosk
 * and another on an event's donation POS (or two ticket counters on two computers), each
 * paired independently via App\Models\EftTerminal and never sharing a bearer token or
 * pairing secret. See LinklyEftService (every method now takes an explicit EftTerminal) and
 * DonationController::resolveTerminalForSession() (a session is permanently tied to whichever
 * terminal opened it, resolved from the ledger for every follow-up poll/cancel/sendkey/
 * refund/reprint action).
 */
class EftMultiTerminalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'Admin',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
    }

    private function secondTerminal(?string $secret = 'terminal-b-secret'): EftTerminal
    {
        return EftTerminal::factory()->create([
            'key' => 'terminal-b',
            'label' => 'Terminal B',
            'secret_sandbox' => $secret,
            'is_default' => false,
        ]);
    }

    // Starting a purchase against a non-default terminal (terminal_id explicitly given)
    // records that terminal on the ledger row and authenticates using ITS secret, not the
    // default terminal's — proven by asserting the actual token request body sent to Linkly.
    public function test_starting_a_charge_on_a_specific_terminal_uses_that_terminals_secret(): void
    {
        $default = $this->defaultEftTerminal('default-secret');
        $terminalB = $this->secondTerminal('terminal-b-secret');
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'token-for-b', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $response = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '10.00',
            'client_ref' => 'terminal-b-charge',
            'terminal_id' => $terminalB->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $sessionId = $response->json('session_id');

        $this->assertDatabaseHas('linkly_transactions', [
            'linkly_session_id' => $sessionId,
            'eft_terminal_id' => $terminalB->id,
        ]);

        Http::assertSent(function ($request) use ($terminalB) {
            return str_contains($request->url(), '/tokens/cloudpos')
                && ($request['secret'] ?? null) === $terminalB->secret_sandbox
                && ($request['posId'] ?? null) === $terminalB->pos_id;
        });
    }

    // Omitting terminal_id falls back to the registry's default terminal — existing
    // single-terminal deployments (and any old cached POS page) keep working unchanged.
    public function test_omitting_terminal_id_falls_back_to_the_default_terminal(): void
    {
        $default = $this->defaultEftTerminal('default-secret');
        $this->secondTerminal();
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'token-for-default', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $response = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '10.00',
            'client_ref' => 'no-terminal-given',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $sessionId = $response->json('session_id');

        $this->assertDatabaseHas('linkly_transactions', [
            'linkly_session_id' => $sessionId,
            'eft_terminal_id' => $default->id,
        ]);
    }

    // Two purchases started on two DIFFERENT terminals at once don't interfere — each
    // resolves and caches its own bearer token (see LinklyEftService::tokenCacheKey()), so
    // pairing/using one never disturbs the other's session.
    public function test_two_terminals_run_concurrent_sessions_independently(): void
    {
        $terminalA = $this->defaultEftTerminal('secret-a');
        $terminalB = $this->secondTerminal('secret-b');
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => function ($request) {
                $secret = $request['secret'] ?? null;
                $token = $secret === 'secret-a' ? 'token-a' : 'token-b';
                return Http::response(['token' => $token, 'expirySeconds' => 300], 200);
            },
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $respA = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '15.00', 'client_ref' => 'concurrent-a', 'terminal_id' => $terminalA->id,
        ]);
        $respB = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '25.00', 'client_ref' => 'concurrent-b', 'terminal_id' => $terminalB->id,
        ]);

        $respA->assertOk()->assertJson(['success' => true]);
        $respB->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('linkly_transactions', ['linkly_session_id' => $respA->json('session_id'), 'eft_terminal_id' => $terminalA->id, 'amount' => 15]);
        $this->assertDatabaseHas('linkly_transactions', ['linkly_session_id' => $respB->json('session_id'), 'eft_terminal_id' => $terminalB->id, 'amount' => 25]);

        // Each transaction call used the token minted for ITS OWN terminal's secret.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transaction')
                && $request->hasHeader('Authorization', 'Bearer token-a')
                && ($request['Request']['TxnRef'] ?? null) !== null;
        });
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transaction')
                && $request->hasHeader('Authorization', 'Bearer token-b');
        });
    }

    // Polling a session resolves the SAME terminal it was started on — never whatever the
    // caller happens to have selected right now — proven by using a token that only the
    // originating terminal's secret would produce.
    public function test_polling_a_session_uses_the_terminal_it_was_started_on(): void
    {
        $default = $this->defaultEftTerminal('secret-a');
        $terminalB = $this->secondTerminal('secret-b');
        $user = $this->adminUser();

        Http::fake([
            '*/tokens/cloudpos' => function ($request) {
                $secret = $request['secret'] ?? null;
                return Http::response(['token' => $secret === 'secret-b' ? 'token-b' : 'token-a', 'expirySeconds' => 300], 200);
            },
            '*/sessions/*/transaction*' => Http::response('', 202),
            '*/sessions/*' => Http::response(['response' => ['success' => true, 'responseCode' => '00']], 200),
        ]);

        $start = $this->actingAs($user)->postJson('/admin/eft/charge/start', [
            'amount' => '12.00', 'client_ref' => 'poll-terminal-b', 'terminal_id' => $terminalB->id,
        ]);
        $sessionId = $start->json('session_id');

        $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}")->assertOk();

        Http::assertSent(function ($request) use ($sessionId) {
            return str_contains($request->url(), "/sessions/{$sessionId}/transaction")
                && strtoupper($request->method()) === 'GET'
                && $request->hasHeader('Authorization', 'Bearer token-b');
        });
    }

    // A refund must go back through the SAME terminal that took the original payment, even
    // if a completely different terminal is the current default/selected one.
    public function test_refund_uses_the_original_purchases_terminal_not_the_default(): void
    {
        $default = $this->defaultEftTerminal('secret-default');
        $terminalB = $this->secondTerminal('secret-b');
        $user = $this->adminUser();

        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Multi-terminal Test Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $original = LinklyTransaction::create([
            'pos_txn_ref' => 'EFTORIGB1',
            'linkly_session_id' => (string) \Illuminate\Support\Str::uuid(),
            'txn_type' => 'purchase',
            'event_id' => $eventId,
            'eft_terminal_id' => $terminalB->id,
            'amount' => 40,
            'status' => 'approved',
            'initiated_by' => $user->id,
        ]);

        Http::fake([
            '*/tokens/cloudpos' => function ($request) {
                $secret = $request['secret'] ?? null;
                return Http::response(['token' => $secret === 'secret-b' ? 'token-b' : 'token-default', 'expirySeconds' => 300], 200);
            },
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $response = $this->actingAs($user)->postJson("/admin/events/{$eventId}/eft/refund/{$original->id}", [
            'amount' => '40.00', 'client_ref' => 'refund-terminal-b',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('linkly_transactions', [
            'original_transaction_id' => $original->id,
            'txn_type' => 'refund',
            'eft_terminal_id' => $terminalB->id,
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transaction')
                && $request->hasHeader('Authorization', 'Bearer token-b');
        });
    }

    // Pairing one terminal never touches another's secret.
    public function test_pairing_one_terminal_does_not_affect_another(): void
    {
        $default = $this->defaultEftTerminal('secret-a');
        $terminalB = $this->secondTerminal(null);
        $admin = $this->adminUser();

        Http::fake([
            '*/pairing/cloudpos' => Http::response(['secret' => 'brand-new-secret-for-b'], 200),
        ]);

        $response = $this->actingAs($admin)->post('/admin/eft/pair', [
            'pair_code' => '123456',
            'terminal_id' => $terminalB->id,
        ]);

        $response->assertRedirect();
        $this->assertSame('brand-new-secret-for-b', $terminalB->fresh()->secret_sandbox);
        $this->assertSame('secret-a', $default->fresh()->secret_sandbox);
    }
}
