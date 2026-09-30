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
            return ['success' => false, 'message' => 'mx51 Cloud is not configured on this server yet — the Pairing API Key is missing.'];
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
            return ['success' => false, 'message' => 'Could not reach the mx51 Cloud pairing service — check network and terminal connections and try again.'];
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
            'api_key_invalid' => 'This server\'s mx51 Cloud credentials were rejected — contact whoever manages the integration.',
            'api_key_missing' => 'This server\'s mx51 Cloud credentials are missing — contact whoever manages the integration.',
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
            $response = self::signedRequest('GET', $terminal->sci_api_base_url . '/v1/pairing-info', null, $terminal);
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI test-pairing request could not reach mx51', ['terminal' => $terminal->key, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the mx51 Cloud service — check network and terminal connections and try again.', 'still_paired' => true];
        }

        if ($response->successful()) {
            // The only proof of life a terminal with no transactions yet can offer — see
            // EftTerminal::lastKnownSciStatus(), which folds this into the Connection reading.
            $terminal->update(['sci_last_checked_at' => now()]);
            return ['success' => true, 'message' => 'Pairing is active.', 'still_paired' => true];
        }

        if ($response->status() === 401 && ($response->json('error.code') ?? $response->json('code')) === 'no_active_pairings_found') {
            return ['success' => false, 'message' => 'This pairing is no longer active on the terminal — it has been cleared here too. Pair again when ready.', 'still_paired' => false];
        }

        return ['success' => false, 'message' => self::pairingErrorMessage($response), 'still_paired' => true];
    }

    /**
     * Proactively confirms a locally-paired terminal is still actually paired on mx51's own
     * side, self-healing immediately if it isn't — per mx51's own certification checklist
     * (SCIPAIRING10/SCIMULTI03), the POS must check GET /pairing-info both when the pairing
     * section is viewed and before a transaction is started, rather than trusting a stale
     * local flag indefinitely (e.g. the terminal was re-paired to a different POS on mx51's
     * side without this one ever being told). A transient network failure or any other
     * unexpected response is treated as "assume still paired" — see testPairing() — since a
     * momentary blip is never grounds to silently unpair a terminal that's actually fine.
     *
     * mx51's own backend has a brief propagation lag between the (separate, unsigned) Pairing
     * API completing a pair and the signed SCI API's own pairing-info reflecting it as active —
     * observed directly as a real no_active_pairings_found response mere seconds after a
     * successful pair. Checking within that window would immediately self-heal a pairing that
     * only *looks* dead, so a freshly-created pairing gets a short grace period before this
     * check is trusted at all.
     *
     * @return bool whether the terminal is (still) genuinely paired after this check
     */
    public static function refreshPairingStatus(EftTerminal $terminal): bool
    {
        if (!$terminal->isSciPaired()) {
            return false;
        }

        if ($terminal->sci_paired_at && $terminal->sci_paired_at->gt(now()->subSeconds(60))) {
            return true;
        }

        $result = self::testPairing($terminal);
        if (!$result['still_paired']) {
            self::unpair($terminal);
        }

        return $result['still_paired'];
    }

    /**
     * Removes a pairing on mx51's side, then clears every sci_* column locally. Called
     * either from the merchant's own "Unpair"/"Cancel" button, or after testPairing()/
     * refreshPairingStatus() reports the pairing is already gone externally — in that second
     * case the mx51-side call will itself fail harmlessly (nothing to unpair), so the local
     * cleanup still runs.
     *
     * @return array{success: bool, message: string}
     */
    public static function unpair(EftTerminal $terminal): array
    {
        if ($terminal->sci_api_base_url && $terminal->sci_pairing_id) {
            try {
                $response = self::signedRequest('POST', $terminal->sci_api_base_url . '/v1/unpair', null, $terminal);
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
            'sci_last_checked_at' => null,
        ]);

        return ['success' => true, 'message' => 'Terminal unpaired.'];
    }

    /**
     * Starts a purchase — POST {sci_api_base_url}/v1/transactions, signed. Returns the
     * transaction's *initial* state (almost always PENDING) for the caller to persist onto
     * an SciTransaction row and begin polling — see pollTransaction().
     *
     * @return array{success: bool, message: string, transaction_id: ?string, version: int, status: ?string, pos_instructions: ?array}
     */
    public static function createPurchase(EftTerminal $terminal, float $amount): array
    {
        return self::createTransaction($terminal, [
            'purchase_details' => ['purchase_amount' => self::toCents($amount)],
        ]);
    }

    /**
     * Starts a refund against a previous SCI transaction. mx51's docs note that when a
     * surcharge was applied to the original purchase, the refund amount should reference
     * that original transaction's own result_amounts.surcharge_amount rather than a
     * recomputed figure — left to the caller (CbaSciController) to pass in already-resolved.
     */
    public static function createRefund(EftTerminal $terminal, float $amount): array
    {
        return self::createTransaction($terminal, [
            'refund_details' => ['refund_amount' => self::toCents($amount)],
        ]);
    }

    private static function createTransaction(EftTerminal $terminal, array $details): array
    {
        try {
            $response = self::signedRequest('POST', $terminal->sci_api_base_url . '/v1/transactions', $details, $terminal);
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI transaction creation could not reach mx51', ['terminal' => $terminal->key, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach mx51 Cloud — check network and terminal connections and try again.', 'transaction_id' => null, 'version' => 0, 'status' => null, 'pos_instructions' => null];
        }

        if (!$response->successful()) {
            return array_merge(
                ['transaction_id' => null, 'version' => 0, 'status' => null, 'pos_instructions' => null],
                ['success' => false, 'message' => self::transactionErrorMessage($response)]
            );
        }

        $data = $response->json('data') ?? [];

        return [
            'success' => true,
            'message' => $data['message'] ?? 'Transaction started.',
            // mx51's actual response field is `id`, not `transaction_id` (confirmed against
            // both their docs' worked example and their own Postman collection's own
            // pre/post-request scripts, which read res.data.id and only THEN rename it to
            // their own local "transaction_id" environment variable — that renaming is
            // Postman-side convenience, not the API's real field name). Reading the wrong
            // key here meant this was always null against the real API, so the frontend's
            // poll() could never even start (it bails immediately with no transaction id),
            // which is why nothing ever appeared to happen after the initial create.
            'transaction_id' => $data['id'] ?? null,
            'version' => $data['version'] ?? 1,
            'status' => $data['status'] ?? 'PENDING',
            'pos_instructions' => $data['pos_instructions'] ?? null,
        ];
    }

    /**
     * GET {sci_api_base_url}/v1/transactions/{id}?min_version={minVersion}, signed.
     *
     * mx51's own documented rule: the NEXT request's min_version is always the response's
     * own `data.version` PLUS ONE — never self-incremented from what was last requested,
     * since the API can skip versions between polls (their own worked example: poll with
     * min_version=2, get back version:4, next request uses min_version=5, not 3). The
     * caller (CbaSciController::poll()) does that +1 arithmetic when it stores this method's
     * returned `version` as the next call's `$minVersion` — this method itself always
     * returns the transaction's actual current version (or null when nothing new arrived,
     * e.g. a still-pending timeout or a network failure), never a guessed-at min_version, so
     * a "nothing changed" response can never be mistaken for a real advance.
     *
     * The two documented 404 shapes are deliberately distinguished: `transaction_not_found_
     * within_timeout` means the requested version simply isn't available *yet* — mx51's docs
     * call this "expected behaviour, not an error": immediately re-poll the SAME min_version
     * with no artificial delay, since the API's own long-poll hold already did the waiting
     * (`done: false`) — while `transaction_not_found` means the id itself is wrong (a
     * genuine error, `done: true, success: false`).
     *
     * @return array{done: bool, success: ?bool, status: ?string, message: ?string, version: ?int, pos_instructions: ?array, result_financial_status: ?string, result_amounts: ?array, result_card_details: ?array, merchant_receipt: ?string, customer_receipt: ?string, transient_error: bool}
     */
    public static function pollTransaction(EftTerminal $terminal, string $transactionId, int $minVersion): array
    {
        $base = [
            'done' => false, 'success' => null, 'status' => null, 'message' => null, 'version' => null,
            'pos_instructions' => null, 'result_financial_status' => null, 'result_amounts' => null,
            'result_card_details' => null, 'merchant_receipt' => null, 'customer_receipt' => null, 'transient_error' => false,
        ];

        try {
            $response = self::signedRequest(
                'GET',
                $terminal->sci_api_base_url . '/v1/transactions/' . $transactionId . '?min_version=' . $minVersion,
                null,
                $terminal
            );
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI poll could not reach mx51', ['terminal' => $terminal->key, 'transaction_id' => $transactionId, 'error' => $e->getMessage()]);
            return array_merge($base, ['transient_error' => true, 'message' => 'Could not reach mx51 Cloud — retrying.']);
        }

        if ($response->status() === 404) {
            $code = $response->json('error.code') ?? $response->json('code');
            if ($code === 'transaction_not_found_within_timeout') {
                return $base; // expected — re-poll the same min_version immediately, no backoff
            }
            return array_merge($base, ['done' => true, 'success' => false, 'message' => 'That transaction could not be found.']);
        }

        if ($response->status() === 424) {
            return array_merge($base, ['done' => true, 'success' => false, 'status' => 'DEVICE_NOT_CONNECTED', 'message' => 'Please check network and terminal connections and try again.']);
        }

        if (!$response->successful()) {
            return array_merge($base, ['done' => true, 'success' => false, 'message' => self::transactionErrorMessage($response)]);
        }

        $data = $response->json('data') ?? [];
        $status = $data['status'] ?? null;
        // The transaction's actual current version, per this response — never a guess at
        // what min_version to ask for next (that +1 arithmetic is the caller's job).
        $version = $data['version'] ?? null;

        if (!in_array($status, ['AWAITING_POS', 'FINALISED'], true)) {
            // PENDING (or anything else in-flight) — keep polling.
            return array_merge($base, ['version' => $version, 'message' => $data['message'] ?? null, 'pos_instructions' => $data['pos_instructions'] ?? null]);
        }

        $resultFinancialStatus = $data['result_financial_status'] ?? null;

        return [
            'done' => true,
            'success' => $status === 'FINALISED' ? ($resultFinancialStatus === 'APPROVED') : null,
            'status' => $status,
            'message' => $data['message'] ?? null,
            'version' => $version,
            'pos_instructions' => $data['pos_instructions'] ?? null,
            'result_financial_status' => $resultFinancialStatus,
            'result_amounts' => $data['result_amounts'] ?? null,
            'result_card_details' => $data['result_card_details'] ?? null,
            'merchant_receipt' => $data['merchant_receipt'] ?? null,
            'customer_receipt' => $data['customer_receipt'] ?? null,
            'transient_error' => false,
        ];
    }

    /**
     * POST {sci_api_base_url}/v1/transactions/{id}/cancel, signed, no body — asks mx51 to
     * cancel a transaction still in progress on the terminal. This does NOT resolve the
     * outcome by itself: per mx51's own transaction-recovery guidance, the POS must keep
     * polling afterward for the eventual FINALISED result (CANCELLED if it succeeded before
     * the card was charged, APPROVED if the cancel came too late to stop it), falling back
     * to the same manual override dialog as an unresponsive transaction if no result arrives
     * within a short cancel-specific timeout. See CbaSciController::cancel().
     *
     * @return array{success: bool, message: string}
     */
    public static function cancelTransaction(EftTerminal $terminal, string $transactionId): array
    {
        try {
            $response = self::signedRequest('POST', $terminal->sci_api_base_url . '/v1/transactions/' . $transactionId . '/cancel', null, $terminal);
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI cancel request could not reach mx51', ['terminal' => $terminal->key, 'transaction_id' => $transactionId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the mx51 Cloud service — check network and terminal connections.'];
        }

        if (!$response->successful()) {
            return ['success' => false, 'message' => self::transactionErrorMessage($response)];
        }

        return ['success' => true, 'message' => 'Cancel requested — waiting for the terminal to confirm.'];
    }

    /**
     * Sends a button/input Action Framework element's interaction back to mx51 — `$submitUrl`
     * is already a complete URL (mx51 returns it fully-qualified in pos_instructions), so
     * this only needs to sign and POST it, optionally with input element values as the body.
     */
    public static function submitAction(EftTerminal $terminal, string $submitUrl, array $formValues = []): array
    {
        try {
            $response = self::signedRequest('POST', $submitUrl, $formValues ?: null, $terminal);
        } catch (ConnectionException $e) {
            Log::warning('CBA SCI action submission could not reach mx51', ['terminal' => $terminal->key, 'url' => $submitUrl, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach mx51 Cloud — check network and terminal connections and try again.'];
        }

        if (!$response->successful()) {
            return ['success' => false, 'message' => self::transactionErrorMessage($response)];
        }

        return ['success' => true, 'message' => 'OK'];
    }

    private static function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private static function transactionErrorMessage($response): string
    {
        $code = $response->json('error.code') ?? $response->json('code');
        $map = [
            'no_active_pairings_found' => 'This terminal is not currently paired — pair it again from EFT Terminal Settings.',
            'transaction_refused' => 'The terminal is busy with another transaction — wait for it to finish and try again.',
            'device_not_connected' => 'Please check network and terminal connections and try again.',
        ];

        if ($code && isset($map[$code])) {
            return $map[$code];
        }

        return $response->json('error.message') ?? $response->json('message') ?? ('Request failed (HTTP ' . $response->status() . ').');
    }

    /**
     * Signs and sends one SCI API request per RFC 9421 HTTP Message Signatures, exactly as
     * documented at https://developer.mx51.io/docs/sci-api-credentials-authentication:
     *
     *   1. Content-Digest: "sha-256=:" + base64(sha256(rawBody)) + ":" — POST/PUT/PATCH only.
     *   2. Signature-Input: sig1=("@method" "@authority" "@request-target" ["content-digest"]);
     *      created={unix_ts};alg="hmac-sha256";keyid="{key_id}" — the content-digest
     *      component is included for every POST/PUT/PATCH request, even a bodyless one (using
     *      the SHA-256 digest of an empty string), and omitted only for GET — this exactly
     *      matches mx51's own Postman collection's pre-request signing script, which computes
     *      it unconditionally for those methods rather than only when a body is present.
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

        $isBodyMethod = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        $rawBody = $isBodyMethod ? ($body !== null ? json_encode($body) : '') : null;

        $components = ['"@method"', '"@authority"', '"@request-target"'];
        $baseLines = [
            '"@method": ' . $method,
            '"@authority": ' . $host,
            '"@request-target": ' . $path,
        ];

        $headers = ['Accept' => 'application/json'];

        if ($isBodyMethod) {
            $digest = 'sha-256=:' . base64_encode(hash('sha256', $rawBody, true)) . ':';
            $components[] = '"content-digest"';
            $baseLines[] = '"content-digest": ' . $digest;
            $headers['Content-Digest'] = $digest;
            if ($body !== null) {
                $headers['Content-Type'] = 'application/json';
            }
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
            ->send($method, $url, $isBodyMethod ? ['body' => $rawBody] : []);
    }
}
