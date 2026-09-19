<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LinklyConfigService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the specific items from Linkly's official Core Payments accreditation script
 * (tests/Linkly-Cloud-Accreditation-10-08-2026_.xlsx) that aren't already exercised by
 * LinklyWebhookTest/LinklyCorePaymentsTest: the mandatory NME/VER/VND Purchase Analysis Data
 * tags (requirement 1.0.1) and the transient-error signal Error Recovery/Exponential Back
 * Off (1.2.1/1.2.2) depends on.
 */
class LinklyAccreditationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'Admin',
            'mobile' => fake()->unique()->numerify('04########'),
        ]);
    }

    // 1.0.1: every Purchase must carry NME (POS name), VER (POS version) and VND (POS vendor
    // id) in PurchaseAnalysisData.
    public function test_purchase_includes_mandatory_nme_ver_vnd_pad_tags(): void
    {
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $this->actingAs($this->adminUser())->postJson('/admin/eft/charge/start', [
            'amount' => '10.00',
            'client_ref' => 'pad-tag-test',
        ])->assertOk();

        $expectedVnd = LinklyConfigService::posVendorId();

        Http::assertSent(function ($request) use ($expectedVnd) {
            if (!str_contains($request->url(), '/transaction')) {
                return false;
            }
            $pad = $request['Request']['PurchaseAnalysisData'] ?? [];
            return ($pad['NME'] ?? null) === 'sievespos'
                && ($pad['VER'] ?? null) === 'ver2.0'
                && ($pad['VND'] ?? null) === $expectedVnd;
        });
    }

    // The Auth Token request's posName/posVersion must match what's declared on the
    // accreditation submission (and what's sent as the NME/VER PAD tags above), not a generic
    // placeholder.
    public function test_auth_token_request_uses_declared_pos_name_and_version(): void
    {
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $this->actingAs($this->adminUser())->postJson('/admin/eft/charge/start', [
            'amount' => '10.00',
            'client_ref' => 'auth-name-test',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/tokens/cloudpos')
                && $request['posName'] === 'sievespos'
                && $request['posVersion'] === 'ver2.0';
        });
    }

    // 1.2.2: a 500-599 or 408 response must be flagged as a transient error so the browser
    // backs off exponentially rather than polling at the normal fixed rate.
    public function test_poll_flags_5xx_and_408_as_transient_errors(): void
    {
        $eventId = \Illuminate\Support\Facades\DB::table('events')->insertGetId([
            'event_name' => 'Test Event', 'event_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = $this->adminUser();

        foreach ([500, 503, 408] as $status) {
            Http::fake([
                '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
                '*/sessions/*' => Http::response(null, $status),
            ]);

            $sessionId = (string) \Illuminate\Support\Str::uuid();
            $response = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");

            $response->assertOk()->assertJson(['transient_error' => true, 'done' => false]);
        }
    }

    // 400/401/404 are explicitly excluded from the error-recovery requirement — no backoff
    // flag, ordinary handling only.
    public function test_poll_does_not_flag_400_401_404_as_transient_errors(): void
    {
        $eventId = \Illuminate\Support\Facades\DB::table('events')->insertGetId([
            'event_name' => 'Test Event', 'event_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = $this->adminUser();

        foreach ([400, 401, 404] as $status) {
            Http::fake([
                '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
                '*/sessions/*' => Http::response(null, $status),
            ]);

            $sessionId = (string) \Illuminate\Support\Str::uuid();
            $response = $this->actingAs($user)->getJson("/admin/eft/charge/status/{$sessionId}?event_id={$eventId}");

            $response->assertOk()->assertJson(['transient_error' => false]);
        }
    }

    // 1.6: TxnRef must be unique and up to 16 characters.
    public function test_generated_txn_ref_is_within_16_characters(): void
    {
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response('', 202),
        ]);

        $this->actingAs($this->adminUser())->postJson('/admin/eft/charge/start', [
            'amount' => '10.00',
            'client_ref' => 'txnref-length-test',
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/transaction')) {
                return false;
            }
            $ref = $request['Request']['TxnRef'] ?? '';
            return strlen($ref) > 0 && strlen($ref) <= 16;
        });
    }
}
