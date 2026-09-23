<?php

namespace App\Services;

use App\Models\EftTerminal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to mx51's "Simple Cloud Integration" (SCI) API to drive a CBA Smart Terminal —
 * pairing (one-time per terminal), then signed transaction requests. See
 * CbaSciConfigService for how the merchant-held credentials (Pairing API Key, Signing
 * Secret Part A) are resolved; this class only ever asks that service for them, never
 * reads config()/Setting directly.
 *
 * Two authentication schemes, per mx51's own docs
 * (https://developer.mx51.io/docs/sci-api-credentials-authentication):
 * - Pairing API: a single `Authorization: ApiKey {key}` header.
 * - SCI API (everything after pairing): RFC 9421 HTTP Message Signatures, signed per
 *   request with the pairing's own Signing Secret Part A + Part B concatenated — see
 *   signedRequest() below, implemented exactly per the documented header/signature-base
 *   format, not approximated.
 *
 * Amounts are in CENTS on the wire (purchase_amount, refund_amount, tip_amount,
 * surcharge_amount) — this class's own public methods take dollars (float) and convert,
 * matching this codebase's existing convention everywhere else (donations/tickets are
 * stored in dollars) and mirroring how LinklyEftService hides Linkly's own cents
 * convention from its callers.
 */
class CbaSciService
{
    /**
     * Exchanges a terminal-displayed pairing code for a permanent per-terminal pairing,
     * saved onto the given terminal. See https://developer.mx51.io/docs/sci-pairing —
     * POST {pairing_base}/progress-pairing, simple ApiKey auth (not signed — there's no
     * per-pairing secret yet at this point).
     *
     * @return array{success: bool, message: string}
     */
    public static function pair(string $pairingCode, ?string $nickname, EftTerminal $terminal): array
    {
        $apiKey = CbaSciConfigService::pairingApiKey();
        if (!$apiKey) {
            return ['success' => false, 'message' => 'CBA Smart Terminal is not configured on this server yet — the Pairing API Key is missing.'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Authorization' => 'ApiKey ' . $apiKey])
                ->post(CbaSciConfigService::pairingApiBaseUrl() . '/progress-pairing', array_filter([
                    'pairing_code' => $pairingCode,
                    'pairing_nickname' => $nickname,
                ]));
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI pairing request could not reach mx51', ['terminal' => $terminal->key, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the CBA Smart Terminal pairing service — check network and terminal connections and try again.'];
        }

        if (!$response->successful()) {
            return ['success' => false, 'message' => self::pairingErrorMessage($response)];
        }

        $data = $response->json('data') ?? [];
        if (empty($data['pairing_id']) || empty($data['signing_secret_part_b'])) {
            Log::warning('CBA SCI pairing succeeded but response was missing expected fields', ['terminal' => $terminal->key, 'body' => $response->body()]);
            return ['success' => false, 'message' => 'Pairing succeeded but the terminal did not return the expected pairing details.'];
        }

        $terminal->update([
            'provider' => 'cba_sci',
            'sci_pairing_id' => $data['pairing_id'],
            'sci_key_id' => $data['key_id'] ?? null,
            'sci_signing_secret_part_b' => $data['signing_secret_part_b'],
            'sci_api_base_url' => $data['sci_api_base_url'] ?? null,
            'sci_confirmation_code' => $data['confirmation_code'] ?? null,
            'sci_tid' => $data['tid'] ?? null,
            'sci_pairing_nickname' => $data['pairing_nickname'] ?? $nickname,
            'sci_terminal_nickname' => $data['terminal_nickname'] ?? null,
            'sci_paired_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Terminal paired successfully.'];
    }

    private static function pairingErrorMessage($response): string
    {
        $code = $response->json('error.code') ?? $response->json('code');
        $map = [
            'invalid_request' => 'The pairing code (or nickname) was invalid — check what was entered and try again.',
            'api_key_invalid' => 'This server\'s CBA Smart Terminal credentials were rejected — contact whoever manages the integration.',
            'api_key_missing' => 'This server\'s CBA Smart Terminal credentials are missing — contact whoever manages the integration.',
            'pairing_route_forbidden' => 'This terminal is not in an authorised environment for these credentials.',
            'test_api_key_forbidden_for_live_pairing' => 'A test (sandbox) key cannot pair a live terminal — switch this integration to live mode first.',
            'pairing_not_found' => 'That pairing code was not recognised — double-check it on the terminal and try again.',
            'pairing_not_initial' => 'That pairing code has already been used or has expired — generate a fresh one on the terminal and try again.',
        ];

        if ($code && isset($map[$code])) {
            return $map[$code];
        }

        return $response->json('error.message') ?? $response->json('message') ?? ('Pairing failed (HTTP ' . $response->status() . ').');
    }

    /**
     * The pairing UI's "Test" button — confirms the pairing is still active on mx51's side
     * without starting a transaction. GET {sci_api_base_url}/pairing-info, signed.
     *
     * @return array{success: bool, message: string, still_paired: bool}
     */
    public static function testPairing(EftTerminal $terminal): array
    {
        if (!$terminal->isSciPaired()) {
            return ['success' => false, 'message' => 'This terminal has not been paired yet.', 'still_paired' => false];
        }

        try {
            $response = self::signedRequest('GET', $terminal->sci_api_base_url . '/pairing-info', null, $terminal);
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI test-pairing request could not reach mx51', ['terminal' => $terminal->key, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the CBA Smart Terminal service — check network and terminal connections and try again.', 'still_paired' => true];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Pairing is active.', 'still_paired' => true];
        }

        if ($response->status() === 401 && ($response->json('error.code') ?? $response->json('code')) === 'no_active_pairings_found') {
            return ['success' => false, 'message' => 'This pairing is no longer active on the terminal — please unpair and pair again.', 'still_paired' => false];
        }

        return ['success' => false, 'message' => self::pairingErrorMessage($response), 'still_paired' => true];
    }

    /**
     * Removes a pairing on mx51's side, then clears every sci_* column locally. Called
     * either from the merchant's own "Unpair" button, or after testPairing()/a transaction
     * reports the pairing is already gone externally — in that second case the mx51-side
     * call will itself fail harmlessly (nothing to unpair), so the local cleanup still runs.
     *
     * @return array{success: bool, message: string}
     */
    public static function unpair(EftTerminal $terminal): array
    {
        if ($terminal->sci_api_base_url && $terminal->sci_pairing_id) {
            try {
                $response = self::signedRequest('POST', $terminal->sci_api_base_url . '/unpair', null, $terminal);
                if (!$response->successful() && $response->status() !== 404) {
                    Log::warning('CBA SCI unpair call failed — clearing local pairing state anyway', ['terminal' => $terminal->key, 'status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (ConnectionException $e) {
                Log::warning('CBA SCI unpair request could not reach mx51 — clearing local pairing state anyway', ['terminal' => $terminal->key, 'error' => $e->getMessage()]);
            }
        }

        $terminal->update([
            'sci_pairing_id' => null,
            'sci_key_id' => null,
            'sci_signing_secret_part_b' => null,
            'sci_api_base_url' => null,
            'sci_confirmation_code' => null,
            'sci_tid' => null,
            'sci_pairing_nickname' => null,
            'sci_terminal_nickname' => null,
            'sci_paired_at' => null,
        ]);

        return ['success' => true, 'message' => 'Terminal unpaired.'];
    }

    /**
     * Signs and sends one SCI API request per RFC 9421 HTTP Message Signatures, exactly as
     * documented at https://developer.mx51.io/docs/sci-api-credentials-authentication:
     *
     *   1. Content-Digest: "sha-256=:" + base64(sha256(rawBody)) + ":" — POST/PUT/PATCH only.
     *   2. Signature-Input: sig1=("@method" "@authority" "@request-target" ["content-digest"]);
     *      created={unix_ts};alg="hmac-sha256";keyid="{key_id}" — the content-digest
     *      component is included only when a body is present (i.e. never for GET).
     *   3. Signature base: newline-joined `"@component": value` lines for each component in
     *      the same order as (2), plus a final `"@signature-params": {the part of (2) after
     *      "sig1="}` line.
     *   4. Signature: HMAC-SHA256 of that base, keyed on signing_secret_part_a . part_b,
     *      base64-encoded, wrapped as `sig1=:{base64}:`.
     *
     * This is the one piece with zero tolerance for approximation — every string below is
     * built to match the documented format character-for-character, not paraphrased from it.
     */
    private static function signedRequest(string $method, string $url, ?array $body, EftTerminal $terminal)
    {
        $method = strtoupper($method);
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        if ($query = parse_url($url, PHP_URL_QUERY)) {
            $path .= '?' . $query;
        }

        $hasBody = $body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true);
        $rawBody = $hasBody ? json_encode($body) : null;

        $components = ['"@method"', '"@authority"', '"@request-target"'];
        $baseLines = [
            '"@method": ' . $method,
            '"@authority": ' . $host,
            '"@request-target": ' . $path,
        ];

        $headers = ['Accept' => 'application/json'];

        if ($hasBody) {
            $digest = 'sha-256=:' . base64_encode(hash('sha256', $rawBody, true)) . ':';
            $components[] = '"content-digest"';
            $baseLines[] = '"content-digest": ' . $digest;
            $headers['Content-Digest'] = $digest;
            $headers['Content-Type'] = 'application/json';
        }

        $created = now()->timestamp;
        $secret = CbaSciConfigService::signingSecretPartA() . $terminal->sci_signing_secret_part_b;
        $signatureParams = '(' . implode(' ', $components) . ');created=' . $created . ';alg="hmac-sha256";keyid="' . $terminal->sci_key_id . '"';

        $baseLines[] = '"@signature-params": ' . $signatureParams;
        $signatureBase = implode("\n", $baseLines);

        $signature = base64_encode(hash_hmac('sha256', $signatureBase, $secret, true));

        $headers['Signature-Input'] = 'sig1=' . $signatureParams;
        $headers['Signature'] = 'sig1=:' . $signature . ':';

        // Sent via the low-level send() with an explicit 'body' option (Guzzle's raw-string
        // request option) rather than post()/put()/patch()'s own array-to-JSON convenience
        // path — that convenience path re-serializes the array itself, which risks producing
        // slightly different bytes (key order, escaping) than what was actually hashed for
        // Content-Digest above, which would make the signature fail verification server-side
        // despite being computed "correctly" against a body that was never actually sent.
        return Http::timeout(30)
            ->withHeaders($headers)
            ->send($method, $url, $hasBody ? ['body' => $rawBody] : []);
    }
}
