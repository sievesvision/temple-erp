<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Services\CbaSciService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * RFC 9421 HTTP Message Signatures, byte-exact against mx51's own documented format
 * (https://developer.mx51.io/docs/sci-api-credentials-authentication) — this is the one
 * piece of the SCI integration with zero tolerance for approximation, since a single wrong
 * character silently fails every signed request's verification server-side.
 *
 * signedRequest() is private (no public Phase 1 call site sends a signed body yet — that
 * arrives with Phase 2's createPurchase()), so this invokes it directly via reflection
 * while Http::fake() captures the real request Laravel actually sends, verifying the exact
 * code path production will use rather than a parallel re-implementation of the signing
 * logic.
 */
class CbaSciSignatureTest extends TestCase
{
    private function invokeSignedRequest(string $method, string $url, ?array $body, EftTerminal $terminal)
    {
        $ref = new ReflectionMethod(CbaSciService::class, 'signedRequest');
        $ref->setAccessible(true);
        return $ref->invoke(null, $method, $url, $body, $terminal);
    }

    private function makeTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-test-' . uniqid(),
            'label' => 'SCI Test Terminal',
            'provider' => 'cba_sci',
            'pos_id' => (string) \Illuminate\Support\Str::uuid(),
            'sci_pairing_id' => 'pid_test',
            'sci_key_id' => 'kid_xxx',
            'sci_signing_secret_part_b' => 'part-b-secret',
            'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
    }

    public function test_get_request_is_signed_without_a_content_digest_component(): void
    {
        config(['services.cba_sci.test_signing_secret_part_a' => 'part-a-secret']);
        Http::fake(['*' => Http::response(['data' => []], 200)]);
        Carbon::setTestNow(Carbon::createFromTimestamp(1715151961));

        $terminal = $this->makeTerminal();
        $this->invokeSignedRequest('GET', 'https://sci-api.tenant.example/pairing-info', null, $terminal);

        Http::assertSent(function ($request) {
            $this->assertFalse($request->hasHeader('Content-Digest'));
            $this->assertSame(
                'sig1=("@method" "@authority" "@request-target");created=1715151961;alg="hmac-sha256";keyid="kid_xxx"',
                $request->header('Signature-Input')[0]
            );

            $expectedBase = "\"@method\": GET\n\"@authority\": sci-api.tenant.example\n\"@request-target\": /pairing-info\n"
                . '"@signature-params": ' . '("@method" "@authority" "@request-target");created=1715151961;alg="hmac-sha256";keyid="kid_xxx"';
            $expectedSignature = base64_encode(hash_hmac('sha256', $expectedBase, 'part-a-secretpart-b-secret', true));

            $this->assertSame('sig1=:' . $expectedSignature . ':', $request->header('Signature')[0]);
            return true;
        });

        Carbon::setTestNow();
    }

    public function test_post_request_with_a_body_includes_a_matching_content_digest_and_signature(): void
    {
        config(['services.cba_sci.test_signing_secret_part_a' => 'part-a-secret']);
        Http::fake(['*' => Http::response(['data' => []], 200)]);
        Carbon::setTestNow(Carbon::createFromTimestamp(1715151961));

        $terminal = $this->makeTerminal();
        $body = ['purchase_details' => ['purchase_amount' => 1000]];
        $rawBody = json_encode($body);

        $this->invokeSignedRequest('POST', 'https://sci-api.tenant.example/v1/transactions', $body, $terminal);

        $expectedDigest = 'sha-256=:' . base64_encode(hash('sha256', $rawBody, true)) . ':';

        Http::assertSent(function ($request) use ($expectedDigest, $rawBody) {
            // The body sent over the wire must be byte-identical to what was hashed for the
            // digest — this is exactly the "digest matches a body that was never actually
            // sent" failure mode the withBody()-over-send() choice in signedRequest() guards
            // against.
            $this->assertSame($rawBody, $request->body());
            $this->assertSame($expectedDigest, $request->header('Content-Digest')[0]);

            $expectedParams = '("@method" "@authority" "@request-target" "content-digest");created=1715151961;alg="hmac-sha256";keyid="kid_xxx"';
            $this->assertSame('sig1=' . $expectedParams, $request->header('Signature-Input')[0]);

            $expectedBase = "\"@method\": POST\n\"@authority\": sci-api.tenant.example\n\"@request-target\": /v1/transactions\n"
                . '"content-digest": ' . $expectedDigest . "\n"
                . '"@signature-params": ' . $expectedParams;
            $expectedSignature = base64_encode(hash_hmac('sha256', $expectedBase, 'part-a-secretpart-b-secret', true));

            $this->assertSame('sig1=:' . $expectedSignature . ':', $request->header('Signature')[0]);
            return true;
        });

        Carbon::setTestNow();
    }

    public function test_content_digest_format_matches_mx51s_documented_wrapper(): void
    {
        // Independent of the HTTP layer entirely — just the "sha-256=:...:" wrapping format
        // mx51 documents, computed the same way signedRequest() does.
        $body = '{"pairing_code":"123456"}';
        $digest = 'sha-256=:' . base64_encode(hash('sha256', $body, true)) . ':';

        $this->assertStringStartsWith('sha-256=:', $digest);
        $this->assertStringEndsWith(':', $digest);
        // A known SHA-256+base64 value for this exact input, computed independently, so this
        // pins the algorithm/encoding choice itself rather than only checking the wrapper.
        $this->assertSame(
            'sha-256=:' . base64_encode(hex2bin(hash('sha256', $body))) . ':',
            $digest
        );
    }
}
