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

    public function test_unpair_clears_all_local_pairing_fields(): void
    {
        $terminal = $this->makeTerminal();
        $terminal->update([
            'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_pairing_nickname' => 'Front Bar',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(null, 204)]);

        $result = CbaSciService::unpair($terminal);

        $this->assertTrue($result['success']);
        $terminal->refresh();
        $this->assertNull($terminal->sci_pairing_id);
        $this->assertNull($terminal->sci_signing_secret_part_b);
        $this->assertFalse($terminal->isSciPaired());
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
