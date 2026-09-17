<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Talks to Linkly's Cloud REST API to drive a physically-paired EFTPOS PIN pad from this
 * app — pairing (one-time per terminal), token exchange (per session), and purchase
 * transactions. See LinklyConfigService for how Sandbox vs Live credentials/secrets are
 * resolved; this class only ever asks that service for them, never reads config()/Setting
 * directly, so switching modes never requires touching this file.
 *
 * Purchases run in Linkly's *async* mode rather than a single blocking call — the operator
 * needs to see the PIN pad's live prompts ("ENTER PIN", "APPROVED", etc.), which only
 * arrive as postback notifications to a webhook while the synchronous call would just sit
 * blocked with no visibility. See DonationController::startEftCharge()/pollEftCharge()/
 * linklyWebhook() for how the three pieces (start, poll, webhook) fit together.
 *
 * Reference: https://linkly.com.au/apidoc/REST/ — AmtPurchase (and every Amt* field) is in
 * CENTS, not dollars; getting that wrong would over/undercharge donors by 100x. Response
 * field names are lowercase-first camelCase ("success", "responseCode", "authCode") in the
 * real API, confirmed directly against a sandbox transaction — Linkly's own docs page
 * rendered these as PascalCase, which does not match what the API actually sends.
 */
class LinklyEftService
{
    /**
     * Exchanges a PIN pad's freshly-displayed pair code for a permanent secret. The pair
     * code is only valid for ~180 seconds from when the terminal generated it, so this must
     * be called immediately after the operator reads it off the terminal/virtual PIN pad.
     *
     * @return array{success: bool, message: string}
     */
    public static function pair(string $pairCode): array
    {
        $response = Http::timeout(30)->post(LinklyConfigService::authBaseUrl() . '/v1/pairing/cloudpos', [
            'username' => LinklyConfigService::username(),
            'password' => LinklyConfigService::password(),
            'pairCode' => $pairCode,
        ]);

        if (!$response->successful()) {
            Log::warning('Linkly pairing failed', ['status' => $response->status(), 'body' => $response->body()]);
            // A 401 with no body is Linkly's response to an invalid or expired pair code —
            // the common case, since the code is only valid for ~180 seconds — so it gets a
            // clearer message than an empty string tacked onto "Pairing failed:".
            $detail = $response->json('message') ?: ($response->body() ?: null);
            $message = $detail ?? ($response->status() === 401
                ? 'Invalid or expired pairing code — generate a fresh one on the terminal and try again within about 3 minutes.'
                : 'Pairing failed (HTTP ' . $response->status() . ').');
            return ['success' => false, 'message' => $message];
        }

        $secret = $response->json('secret');
        if (!$secret) {
            return ['success' => false, 'message' => 'Pairing succeeded but no secret was returned.'];
        }

        LinklyConfigService::setSecret($secret);
        Cache::forget(self::tokenCacheKey());

        return ['success' => true, 'message' => 'PIN pad paired successfully.'];
    }

    private static function tokenCacheKey(): string
    {
        return 'linkly_token_' . LinklyConfigService::mode();
    }

    /**
     * A bearer token for the transaction API, cached until shortly before it expires so a
     * burst of donations doesn't re-request one every time.
     */
    private static function getToken(): ?string
    {
        return Cache::remember(self::tokenCacheKey(), 240, function () {
            $secret = LinklyConfigService::secret();
            if (!$secret) {
                return null;
            }

            $response = Http::timeout(30)->post(LinklyConfigService::authBaseUrl() . '/v1/tokens/cloudpos', [
                'secret' => $secret,
                'posName' => 'SSVK ERP',
                'posVersion' => '1.0',
                'posId' => LinklyConfigService::posId(),
                'posVendorId' => LinklyConfigService::posVendorId(),
            ]);

            if (!$response->successful()) {
                Log::warning('Linkly token request failed', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $token = $response->json('token');
            $expiry = (int) ($response->json('expirySeconds') ?? 300);

            if ($token) {
                // Re-cache with the token's real expiry (minus a safety margin) now that we
                // know it — the outer remember() call's TTL above is just a starting guess.
                Cache::put(self::tokenCacheKey(), $token, max(30, $expiry - 30));
            }

            return $token;
        });
    }

    private static function displayCacheKey(string $sessionId): string
    {
        return "linkly_display_{$sessionId}";
    }

    private static function displayFlagsCacheKey(string $sessionId): string
    {
        return "linkly_display_flags_{$sessionId}";
    }

    private static function displayUpdatedAtCacheKey(string $sessionId): string
    {
        return "linkly_display_updated_at_{$sessionId}";
    }

    private static function emptyControls(): array
    {
        return ['cancel' => false, 'ok' => false, 'yes' => false, 'no' => false, 'authorise' => false];
    }

    private static function webhookTokenCacheKey(string $sessionId): string
    {
        return "linkly_webhook_token_{$sessionId}";
    }

    /**
     * Starts a purchase transaction in async mode and returns immediately — the operator
     * sees the terminal's live prompts via pollTransaction() (fed by the webhook) rather
     * than this call blocking until the card is actually tapped/inserted. $amount is in
     * dollars; Linkly's API wants cents.
     *
     * @return array{success: bool, message: string, session_id: ?string}
     */
    public static function startPurchase(float $amount, string $txnRef, string $currencyCode, string $notificationUri): array
    {
        if (!LinklyConfigService::isPaired()) {
            return ['success' => false, 'message' => 'No EFT terminal is paired yet.', 'session_id' => null];
        }

        $token = self::getToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not authenticate with the EFT terminal service.', 'session_id' => null];
        }

        $sessionId = (string) Str::uuid();
        $webhookToken = Str::random(40);
        // Short TTL — a single transaction attempt should resolve within Linkly's own
        // ~3-minute window, never left around indefinitely.
        Cache::put(self::webhookTokenCacheKey($sessionId), $webhookToken, 300);

        // TxnRef is capped at 16 characters by the terminal.
        $txnRef = substr($txnRef, 0, 16);

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->post(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/transaction?async=true", [
                    'Request' => [
                        'Merchant' => '00',
                        'TxnType' => 'P',
                        'AmtPurchase' => (int) round($amount * 100),
                        'TxnRef' => $txnRef,
                        'CurrencyCode' => $currencyCode,
                        'CutReceipt' => '0',
                        'ReceiptAutoPrint' => '0',
                        'Application' => '00',
                    ],
                    'Notification' => [
                        'Uri' => $notificationUri,
                        'AuthorizationHeader' => 'Bearer ' . $webhookToken,
                    ],
                ]);
        } catch (\Exception $e) {
            Log::error('Linkly startPurchase request exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service: ' . $e->getMessage(), 'session_id' => null];
        }

        if (!$response->successful()) {
            Log::warning('Linkly startPurchase failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'EFT terminal request failed (' . $response->status() . ').', 'session_id' => null];
        }

        return ['success' => true, 'message' => 'Transaction started.', 'session_id' => $sessionId];
    }

    /**
     * Sends a soft-key press back to the terminal for an in-progress async transaction —
     * used to let the POS operator cancel a wait (e.g. "PRESENT CARD"/"ENTER PIN") from the
     * browser instead of only being able to cancel on the physical terminal itself. Per
     * Linkly's docs, "0" is CANCEL, "1" is YES/OK, "2" is NO, "3" is AUTHORISE — kept as raw
     * key codes here rather than named constants since only cancel() is wired up yet.
     * Whether a cancel actually lands depends on the transaction's current stage (e.g. it
     * can arrive too late once the card has already been read/approved) — either way, the
     * authoritative outcome still only ever comes from pollTransaction()'s GET, never
     * assumed from this call succeeding.
     *
     * @return array{success: bool, message: string}
     */
    public static function sendKey(string $sessionId, string $key): array
    {
        $token = self::getToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not authenticate with the EFT terminal service.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->post(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/sendkey?async=true", [
                    'Request' => [
                        'Key' => $key,
                        'InputData' => '',
                    ],
                ]);
        } catch (\Exception $e) {
            Log::warning('Linkly sendKey request exception', ['session_id' => $sessionId, 'message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service.'];
        }

        if (!$response->successful()) {
            Log::warning('Linkly sendKey failed', ['session_id' => $sessionId, 'status' => $response->status()]);
            return ['success' => false, 'message' => 'The terminal did not accept the cancel request — it may have already moved past that step.'];
        }

        return ['success' => true, 'message' => 'Cancel sent to the terminal.'];
    }

    /**
     * Sends the CANCEL soft-key ("0") for an in-progress async transaction.
     *
     * @return array{success: bool, message: string}
     */
    public static function cancel(string $sessionId): array
    {
        return self::sendKey($sessionId, '0');
    }

    /**
     * Trims each line and drops blank ones, so e.g. Linkly's fixed-width, space-padded
     * ["     SWIPE CARD     ", "                    "] becomes just ["SWIPE CARD"], while a
     * genuinely two-line prompt like ["SELECT ACCOUNT", "SAV CHQ CR"] keeps both lines.
     *
     * @param array<mixed> $lines
     * @return array<int, string>
     */
    public static function cleanDisplayLines(array $lines): array
    {
        return array_values(array_filter(
            array_map(fn ($l) => trim((string) $l), $lines),
            fn ($l) => $l !== ''
        ));
    }

    /**
     * Records a "display" postback from Linkly (the PIN pad's current on-screen prompt, plus
     * which of its soft-key actions are currently offered) so pollTransaction() can hand it
     * to the browser on its next poll. Called from the webhook route —
     * DonationController::linklyWebhook() has already verified the bearer token and resolved
     * the real session id (from the postback body, not the URL — see that method) before
     * this runs. A later call for the same session simply overwrites the previous one, which
     * is exactly the desired behaviour for duplicate or out-of-order postbacks: the browser
     * only ever needs the *latest* prompt, not a history of every one that arrived.
     *
     * @param array<mixed> $lines
     * @param array{cancel?: bool, ok?: bool, yes?: bool, no?: bool, authorise?: bool} $flags
     */
    public static function recordDisplay(string $sessionId, array $lines, array $flags = []): void
    {
        Cache::put(self::displayCacheKey($sessionId), self::cleanDisplayLines($lines), 300);
        Cache::put(self::displayFlagsCacheKey($sessionId), array_merge(self::emptyControls(), $flags), 300);
        Cache::put(self::displayUpdatedAtCacheKey($sessionId), now()->toIso8601String(), 300);
    }

    public static function verifyWebhookToken(string $sessionId, ?string $bearerToken): bool
    {
        $expected = Cache::get(self::webhookTokenCacheKey($sessionId));
        return $expected && $bearerToken && hash_equals($expected, $bearerToken);
    }

    /**
     * Maps a declined/errored Linkly response onto the app's own payment_status vocabulary
     * (initiated/in_progress/approved/declined/cancelled/failed) — kept deliberately separate
     * from the terminal's own free-text responseText/display, per the "don't determine
     * success from DisplayText" rule: this only ever runs on the authoritative GET-polled
     * transaction result, never on a display postback.
     */
    private static function mapResponseToStatus(bool $success, ?string $responseCode, ?string $responseText): string
    {
        if ($success) {
            return 'approved';
        }

        $text = strtoupper((string) $responseText);
        if (str_contains($text, 'CANCEL')) {
            return 'cancelled';
        }
        if (str_contains($text, 'TIMEOUT') || str_contains($text, 'SYSTEM ERROR') || $responseCode === null) {
            return 'failed';
        }

        return 'declined';
    }

    /**
     * Polls Linkly directly for the transaction's real status (the authoritative source —
     * a missed webhook postback must never be the only way the app finds out whether a
     * charge succeeded) and merges in the latest cached "display" text/key-flags for
     * on-screen feedback while it's still in progress.
     *
     * @return array{
     *   payment_status: string, done: bool, display: array<string>, display_text: ?string,
     *   updated_at: ?string, controls: array<string, bool>, success: ?bool, message: ?string,
     *   response_code: ?string, auth_code: ?string, rrn: ?string, txn_ref: ?string
     * }
     */
    public static function pollTransaction(string $sessionId): array
    {
        $display = Cache::get(self::displayCacheKey($sessionId), []);
        $controls = Cache::get(self::displayFlagsCacheKey($sessionId), self::emptyControls());
        $updatedAt = Cache::get(self::displayUpdatedAtCacheKey($sessionId));
        $base = [
            'display' => $display,
            'display_text' => $display[0] ?? null,
            'updated_at' => $updatedAt,
            'controls' => $controls,
        ];

        $token = self::getToken();
        if (!$token) {
            return array_merge($base, ['payment_status' => 'failed', 'done' => true, 'success' => false, 'message' => 'Could not authenticate with the EFT terminal service.', 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => null]);
        }

        try {
            $response = Http::timeout(15)->withToken($token)
                ->get(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/transaction");
        } catch (\Exception $e) {
            // A transient network hiccup while polling isn't fatal — report "still going"
            // so the browser just tries again on its next tick instead of giving up.
            return array_merge($base, ['payment_status' => 'in_progress', 'done' => false, 'success' => null, 'message' => null, 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => null]);
        }

        if ($response->status() === 202) {
            return array_merge($base, ['payment_status' => 'in_progress', 'done' => false, 'success' => null, 'message' => null, 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => null]);
        }

        if ($response->status() === 404) {
            return array_merge($base, ['payment_status' => 'failed', 'done' => true, 'success' => false, 'message' => 'Transaction was not accepted by the terminal service.', 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => null]);
        }

        if (!$response->successful()) {
            return array_merge($base, ['payment_status' => 'in_progress', 'done' => false, 'success' => null, 'message' => null, 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => null]);
        }

        $txnResponse = $response->json('response') ?? [];
        $success = (bool) ($txnResponse['success'] ?? false);
        $responseCode = $txnResponse['responseCode'] ?? null;
        $responseText = trim($txnResponse['responseText'] ?? '') ?: null;

        return array_merge($base, [
            'payment_status' => self::mapResponseToStatus($success, $responseCode, $responseText),
            'done' => true,
            'success' => $success,
            'message' => $success
                ? 'Approved' . (!empty($txnResponse['authCode']) ? ' — Auth ' . $txnResponse['authCode'] : '')
                : ($responseText ?? 'Declined'),
            'response_code' => $responseCode,
            'auth_code' => !empty($txnResponse['authCode']) ? $txnResponse['authCode'] : null,
            'rrn' => !empty(trim($txnResponse['rrn'] ?? '')) ? trim($txnResponse['rrn']) : null,
            'txn_ref' => !empty(trim($txnResponse['txnRef'] ?? '')) ? trim($txnResponse['txnRef']) : null,
        ]);
    }
}
