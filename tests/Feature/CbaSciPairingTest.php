<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Services\CbaSciService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pairing/Test/Unpair against a faked HTTP client — covers every error code mx51 documents
 * (https://developer.mx51.io/docs/sci-pairing) plus the checklist's "connectivity failure"
 * and "externally unpaired" states.
 */
class CbaSciPairingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cba_sci.test_pairing_api_key' => 'test-pairing-key', 'services.cba_sci.test_signing_secret_part_a' => 'part-a-secret']);
    }

    private function makeTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-' . uniqid(),
            'label' => 'SCI Terminal',
            'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(),
        ]);
    }

    public function test_successful_pairing_saves_every_returned_field(): void
    {
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_123',
                'key_id' => 'kid_123',
                'confirmation_code' => '1234',
                'signing_secret_part_b' => 'secret-b',
                'sci_api_base_url' => 'https://sci-api.tenant.example',
                'tid' => 'tid123',
                'pairing_nickname' => 'Front Bar',
                'terminal_nickname' => 'Terminal 123',
            ]], 200),
        ]);

        $terminal = $this->makeTerminal();
        $result = CbaSciService::pair('123456', 'Front Bar', $terminal);

        $this->assertTrue($result['success']);
        $terminal->refresh();
        $this->assertSame('pid_123', $terminal->sci_pairing_id);
        $this->assertSame('kid_123', $terminal->sci_key_id);
        $this->assertSame('secret-b', $terminal->sci_signing_secret_part_b);
        $this->assertSame('https://sci-api.tenant.example', $terminal->sci_api_base_url);
        $this->assertSame('Front Bar', $terminal->sci_pairing_nickname);
        $this->assertTrue($terminal->isSciPaired());

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'ApiKey test-pairing-key')
                && $request['pairing_code'] === '123456';
        });
    }

    // The physical terminal, not the registry row, is what mx51's TID actually identifies —
    // pairing a brand new row against a TID some OTHER row already carries means the same
    // physical device is being registered twice, which would otherwise show up as two
    // identical-looking entries in every terminal picker.
    // The TID, not an admin-typed code, is what actually identifies a physical terminal — so
    // pairing a brand-new row against a TID some OTHER row already carries means this is that
    // same physical device being registered again, not a genuinely new one. The old row is
    // retired (soft-deleted) rather than left behind as a dead duplicate, and the new row
    // inherits its place, including the TID-derived key.
    public function test_pairing_replaces_a_terminal_already_registered_with_the_same_tid(): void
    {
        $existing = $this->makeTerminal();
        $existing->update(['sci_tid' => 'tid123', 'label' => 'Front Counter', 'key' => 'sci-tid123']);

        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_456', 'key_id' => 'kid_456', 'confirmation_code' => '9999',
                'signing_secret_part_b' => 'secret-b-2', 'sci_api_base_url' => 'https://sci-api.tenant.example',
                'tid' => 'tid123',
            ]], 200),
        ]);

        $newTerminal = $this->makeTerminal();
        $result = CbaSciService::pair('654321', null, $newTerminal);

        $this->assertTrue($result['success']);
        $newTerminal->refresh();
        $this->assertTrue($newTerminal->isSciPaired());
        $this->assertSame('tid123', $newTerminal->sci_tid);
        $this->assertSame('sci-tid123', $newTerminal->key);

        // The old row is gone from the active registry but still fully present (soft-deleted),
        // under a renamed key so it never collides with the one the new row just claimed.
        $this->assertNull(EftTerminal::find($existing->id));
        $existing->refresh();
        $this->assertNotNull($existing->deleted_at);
        $this->assertNull($existing->sci_pairing_id);
        $this->assertStringStartsWith('sci-tid123-retired-', $existing->key);
    }

    public function test_pairing_transfers_default_status_when_replacing_the_default_terminal(): void
    {
        $existing = $this->makeTerminal();
        $existing->update(['sci_tid' => 'tid123', 'is_default' => true]);

        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_456', 'key_id' => 'kid_456', 'confirmation_code' => '9999',
                'signing_secret_part_b' => 'secret-b-2', 'sci_api_base_url' => 'https://sci-api.tenant.example',
                'tid' => 'tid123',
            ]], 200),
        ]);

        $newTerminal = $this->makeTerminal();
        $result = CbaSciService::pair('654321', null, $newTerminal);

        $this->assertTrue($result['success']);
        $this->assertTrue($newTerminal->fresh()->is_default);
    }

    // Re-pairing the SAME row against the same physical device it already represents is the
    // normal case, not a duplicate — the exclusion has to be by id, not just "any match".
    public function test_pairing_allows_re_pairing_the_same_row_with_its_own_tid(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update(['sci_tid' => 'tid123']);

        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_789', 'key_id' => 'kid_789', 'confirmation_code' => '1111',
                'signing_secret_part_b' => 'secret-b-3', 'sci_api_base_url' => 'https://sci-api.tenant.example',
                'tid' => 'tid123',
            ]], 200),
        ]);

        $result = CbaSciService::pair('111111', null, $terminal);

        $this->assertTrue($result['success']);
        $this->assertTrue($terminal->fresh()->isSciPaired());
        $this->assertSame('sci-tid123', $terminal->fresh()->key);
    }

    public function test_secret_part_b_is_never_exposed_in_array_or_json_output(): void
    {
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_123', 'key_id' => 'kid_123', 'signing_secret_part_b' => 'super-secret',
                'sci_api_base_url' => 'https://sci-api.tenant.example',
            ]], 200),
        ]);

        $terminal = $this->makeTerminal();
        CbaSciService::pair('123456', null, $terminal);
        $terminal->refresh();

        $this->assertArrayNotHasKey('sci_signing_secret_part_b', $terminal->toArray());
        $this->assertStringNotContainsString('super-secret', $terminal->toJson());
    }

    public function test_invalid_pairing_code_returns_a_clear_error(): void
    {
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['error' => ['code' => 'pairing_not_found']], 404),
        ]);

        $result = CbaSciService::pair('000000', null, $this->makeTerminal());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not recognised', $result['message']);
    }

    public function test_expired_pairing_code_returns_a_clear_error(): void
    {
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['error' => ['code' => 'pairing_not_initial']], 422),
        ]);

        $result = CbaSciService::pair('123456', null, $this->makeTerminal());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already been used or has expired', $result['message']);
    }

    public function test_connectivity_failure_during_pairing_is_handled(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $result = CbaSciService::pair('123456', null, $this->makeTerminal());

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('check network and terminal connections', $result['message']);
    }

    public function test_missing_pairing_api_key_is_reported_without_making_a_request(): void
    {
        config(['services.cba_sci.test_pairing_api_key' => null]);
        Http::fake();

        $result = CbaSciService::pair('123456', null, $this->makeTerminal());

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_test_pairing_reports_active_pairing(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => []], 200)]);

        $result = CbaSciService::testPairing($terminal);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['still_paired']);
    }

    // The Connection status shown on the settings page has nothing else to go on for a
    // terminal that's never taken a real transaction — see EftTerminal::lastKnownSciStatus().
    public function test_a_successful_test_pairing_records_when_it_was_last_checked(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => []], 200)]);

        CbaSciService::testPairing($terminal);

        $this->assertNotNull($terminal->fresh()->sci_last_checked_at);
    }

    // A failed check means the pairing is the problem, not the device — never recorded as a
    // "last checked" reading, so it can't surface as a misleading "online" state.
    public function test_a_failed_test_pairing_does_not_record_a_last_checked_time(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        CbaSciService::testPairing($terminal);

        $this->assertNull($terminal->fresh()->sci_last_checked_at);
    }

    // mx51's own Postman collection puts every signed SCI API endpoint under /v1 (including
    // pairing-info and unpair) — a missing /v1 here silently 404s ("Route not found") and,
    // because testPairing() fails open on anything but the documented 401, made every
    // externally-unpaired terminal look permanently still-paired.
    public function test_test_pairing_hits_the_versioned_pairing_info_path(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => []], 200)]);

        CbaSciService::testPairing($terminal);

        Http::assertSent(fn ($request) => $request->url() === 'https://sci-api.tenant.example/v1/pairing-info');
    }

    public function test_test_pairing_detects_externally_unpaired_terminal(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $result = CbaSciService::testPairing($terminal);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['still_paired']);
        $this->assertStringContainsString('no longer active', $result['message']);
    }

    // mx51 returns the exact same no_active_pairings_found response both for a pairing that's
    // genuinely gone and one that's still waiting on the admin to confirm the code on the
    // physical terminal — recency of sci_paired_at is the only signal available to tell them
    // apart. still_paired stays false either way (refreshPairingStatus()'s self-heal still
    // needs that), only the message differs, so the manual Test button (which never
    // auto-unpairs, see CbaSciController::testPairing()) can say something accurate instead of
    // "it has been cleared" when nothing was actually cleared.
    public function test_test_pairing_reports_awaiting_confirmation_for_a_just_paired_terminal(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_paired_at' => now(),
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $result = CbaSciService::testPairing($terminal);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['still_paired']);
        $this->assertStringContainsString('confirmed on the terminal', $result['message']);
    }

    public function test_test_pairing_reports_genuinely_stale_once_well_past_pairing(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_paired_at' => now()->subMinutes(10),
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $result = CbaSciService::testPairing($terminal);

        $this->assertFalse($result['still_paired']);
        $this->assertStringContainsString('no longer active', $result['message']);
    }

    // mx51's own certification checklist (SCIPAIRING10/SCIMULTI03) requires the POS to
    // proactively confirm a pairing is still active — refreshPairingStatus() is what both
    // EftTerminalController::index() (viewing the pairing section) and
    // CbaSciController::startPurchase() (before a transaction) call for this.
    public function test_refresh_pairing_status_self_heals_when_mx51_reports_no_active_pairing(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $stillPaired = CbaSciService::refreshPairingStatus($terminal);

        $this->assertFalse($stillPaired);
        $this->assertFalse($terminal->fresh()->isSciPaired());
    }

    // Observed live: mx51's Pairing API and SCI API don't update in perfect lockstep, so a
    // pairing-info check within seconds of a successful pair can genuinely come back
    // no_active_pairings_found even though the pair just succeeded — self-healing on that
    // would undo a pairing that's actually fine. A freshly-paired terminal gets a short grace
    // period before this check is trusted, closing the race without weakening the check for
    // any pairing older than that.
    public function test_refresh_pairing_status_ignores_a_stale_looking_response_right_after_pairing(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_paired_at' => now(),
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $stillPaired = CbaSciService::refreshPairingStatus($terminal);

        $this->assertTrue($stillPaired);
        $this->assertTrue($terminal->fresh()->isSciPaired());
        Http::assertNothingSent();
    }

    public function test_refresh_pairing_status_trusts_the_check_once_the_grace_period_has_passed(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_paired_at' => now()->subMinutes(2),
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $stillPaired = CbaSciService::refreshPairingStatus($terminal);

        $this->assertFalse($stillPaired);
        $this->assertFalse($terminal->fresh()->isSciPaired());
    }

    public function test_refresh_pairing_status_leaves_a_genuinely_active_pairing_untouched(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => []], 200)]);

        $stillPaired = CbaSciService::refreshPairingStatus($terminal);

        $this->assertTrue($stillPaired);
        $this->assertTrue($terminal->fresh()->isSciPaired());
    }

    public function test_refresh_pairing_status_fails_open_on_a_network_error(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => fn () => throw new ConnectionException('timed out')]);

        $stillPaired = CbaSciService::refreshPairingStatus($terminal);

        $this->assertTrue($stillPaired);
        $this->assertTrue($terminal->fresh()->isSciPaired());
    }

    public function test_refresh_pairing_status_is_a_noop_for_an_already_unpaired_terminal(): void
    {
        $terminal = $this->makeTerminal();

        $stillPaired = CbaSciService::refreshPairingStatus($terminal);

        $this->assertFalse($stillPaired);
        Http::assertNothingSent();
    }

    public function test_unpair_clears_all_local_pairing_fields(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_pairing_nickname' => 'Front Bar', 'sci_last_checked_at' => now(),
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(null, 204)]);

        $result = CbaSciService::unpair($terminal);

        $this->assertTrue($result['success']);
        $terminal->refresh();
        $this->assertNull($terminal->sci_pairing_id);
        $this->assertNull($terminal->sci_signing_secret_part_b);
        $this->assertNull($terminal->sci_last_checked_at);
        $this->assertFalse($terminal->isSciPaired());
    }

    public function test_unpair_hits_the_versioned_unpair_path(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(null, 204)]);

        CbaSciService::unpair($terminal);

        Http::assertSent(fn ($request) => $request->url() === 'https://sci-api.tenant.example/v1/unpair');
    }

    public function test_unpair_still_clears_local_state_when_mx51_call_fails(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => 'boom'], 500)]);

        CbaSciService::unpair($terminal);

        $terminal->refresh();
        $this->assertFalse($terminal->isSciPaired());
    }
}
