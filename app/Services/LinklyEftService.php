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
     * The three mandatory Core Payments Purchase Analysis Data tags (per Linkly's REST
     * documentation): OPR (operator reference — "id|name"), AMT (total sale amount in cents,
     * same value as AmtPurchase since this app never adds tips/surcharges), and PCM (POS
     * Capabilities Matrix — "0000" is Linkly's own documented default for a POS with none of
     * the optional capabilities the matrix's digits represent). LINKLY CONFIRMATION REQUIRED:
     * the exact meaning of each PCM digit isn't in the pages this was built from — "0000"
     * should be re-confirmed against the current official spec before accreditation testing.
     *
     * @return array<string, string>
     */
    private static function corePad(int $amtCents, ?int $operatorId, ?string $operatorName): array
    {
        return [
            'OPR' => trim(($operatorId ?? '0') . '|' . ($operatorName ?? 'POS')),
            'AMT' => (string) $amtCents,
            'PCM' => '0000',
        ];
    }

    /**
     * Shared by startPurchase()/startRefund() — both are just a TxnType difference (and a
     * refund additionally carries the original purchase's TxnRef back as the PAD "RFN" tag,
     * per Linkly's "all refunds must be matched to an original purchase" requirement) on the
     * same session-based async transaction call.
     *
     * @return array{success: bool, message: string, session_id: ?string}
     */
    private static function startSession(
        string $txnType,
        float $amount,
        string $txnRef,
        string $currencyCode,
        string $notificationUri,
        ?int $operatorId,
        ?string $operatorName,
        ?string $refundOfTxnRef = null
    ): array {
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
        $amtCents = (int) round($amount * 100);
        $pad = self::corePad($amtCents, $operatorId, $operatorName);
        if ($refundOfTxnRef !== null) {
            $pad['RFN'] = substr($refundOfTxnRef, 0, 16);
        }

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->post(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/transaction?async=true", [
                    'Request' => [
                        'Merchant' => '00',
                        'TxnType' => $txnType,
                        'AmtPurchase' => $amtCents,
                        'TxnRef' => $txnRef,
                        'CurrencyCode' => $currencyCode,
                        'CutReceipt' => '0',
                        'ReceiptAutoPrint' => '0',
                        'Application' => '00',
                        'PurchaseAnalysisData' => $pad,
                    ],
                    'Notification' => [
                        'Uri' => $notificationUri,
                        'AuthorizationHeader' => 'Bearer ' . $webhookToken,
                    ],
                ]);
        } catch (\Exception $e) {
            Log::error('Linkly startSession request exception', ['txn_type' => $txnType, 'message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service: ' . $e->getMessage(), 'session_id' => null];
        }

        if (!$response->successful()) {
            Log::warning('Linkly startSession failed', ['txn_type' => $txnType, 'status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'EFT terminal request failed (' . $response->status() . ').', 'session_id' => null];
        }

        return ['success' => true, 'message' => 'Transaction started.', 'session_id' => $sessionId];
    }

    /**
     * Starts a purchase transaction in async mode and returns immediately — the operator
     * sees the terminal's live prompts via pollTransaction() (fed by the webhook) rather
     * than this call blocking until the card is actually tapped/inserted. $amount is in
     * dollars; Linkly's API wants cents.
     *
     * @return array{success: bool, message: string, session_id: ?string}
     */
    public static function startPurchase(
        float $amount,
        string $txnRef,
        string $currencyCode,
        string $notificationUri,
        ?int $operatorId = null,
        ?string $operatorName = null
    ): array {
        return self::startSession('P', $amount, $txnRef, $currencyCode, $notificationUri, $operatorId, $operatorName);
    }

    /**
     * Starts a refund transaction in async mode. $originalTxnRef is the POS-generated TxnRef
     * of the purchase being refunded — sent back to Linkly as the PAD "RFN" tag so the refund
     * is matched to that original sale, per Linkly's Core Payments refund requirement. This
     * never re-sends the original session id: a refund is always its own new Linkly session.
     *
     * @return array{success: bool, message: string, session_id: ?string}
     */
    public static function startRefund(
        float $amount,
        string $txnRef,
        string $originalTxnRef,
        string $currencyCode,
        string $notificationUri,
        ?int $operatorId = null,
        ?string $operatorName = null
    ): array {
        return self::startSession('R', $amount, $txnRef, $currencyCode, $notificationUri, $operatorId, $operatorName, $originalTxnRef);
    }

    /**
     * Logs on to the terminal — confirms the paired terminal is reachable and configured,
     * without moving any money. Run synchronously (no webhook/polling plumbing) since a
     * Logon completes immediately rather than waiting on card/PIN entry.
     * LINKLY CONFIRMATION REQUIRED: Linkly's own accreditation minimums list Purchase,
     * Refund, Reprint Receipt and Transaction Status, but do not clearly list Logon as
     * mandatory for Core Payments — this exists so it's available if requested during
     * testing, not because its absence is assumed to fail accreditation.
     *
     * @return array{success: bool, message: string}
     */
    public static function logon(): array
    {
        if (!LinklyConfigService::isPaired()) {
            return ['success' => false, 'message' => 'No EFT terminal is paired yet.'];
        }

        $token = self::getToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not authenticate with the EFT terminal service.'];
        }

        $sessionId = (string) Str::uuid();

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->post(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/transaction?async=false", [
                    'Request' => [
                        'Merchant' => '00',
                        'TxnType' => 'L',
                        'Application' => '00',
                    ],
                ]);
        } catch (\Exception $e) {
            Log::error('Linkly logon request exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service: ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            Log::warning('Linkly logon failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'Logon request failed (' . $response->status() . ').'];
        }

        $txnResponse = $response->json('response') ?? [];
        $success = (bool) ($txnResponse['success'] ?? false);
        $responseText = trim($txnResponse['responseText'] ?? '') ?: null;

        return [
            'success' => $success,
            'message' => $success ? 'Logon successful.' : ($responseText ?? 'Logon failed.'),
        ];
    }

    /**
     * Asks the terminal to reprint the receipt for a completed session — used from the
     * accreditation/EFTPOS admin pane, never automatically. LINKLY CONFIRMATION REQUIRED:
     * built against the documented /reprintreceipt path with no body; re-confirm the exact
     * request/response shape against the sandbox before relying on it for accreditation.
     *
     * @return array{success: bool, message: string}
     */
    public static function reprintReceipt(string $sessionId): array
    {
        $token = self::getToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not authenticate with the EFT terminal service.'];
        }

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->post(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/reprintreceipt?async=false", [
                    'Request' => new \stdClass(),
                ]);
        } catch (\Exception $e) {
            Log::error('Linkly reprintReceipt request exception', ['session_id' => $sessionId, 'message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service.'];
        }

        if (!$response->successful()) {
            Log::warning('Linkly reprintReceipt failed', ['session_id' => $sessionId, 'status' => $response->status()]);
            return ['success' => false, 'message' => 'The terminal could not reprint that receipt (it may no longer be available).'];
        }

        return ['success' => true, 'message' => 'Receipt reprint requested.'];
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
                        'Merchant' => '00',
                        'Application' => '00',
                        'Key' => $key,
                        'InputData' => '',
                    ],
                ]);
        } catch (\Exception $e) {
            Log::warning('Linkly sendKey request exception', ['session_id' => $sessionId, 'message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service.'];
        }

        if (!$response->successful()) {
            // A sendkey response never carries cardholder data, only an accept/reject of the
            // key press — safe to log the body verbatim, unlike the transaction/receipt
            // postbacks, so a genuine "terminal already moved past that step" rejection can
            // be told apart from a malformed-request 400.
            Log::warning('Linkly sendKey failed', ['session_id' => $sessionId, 'status' => $response->status(), 'body' => $response->body()]);
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

    private static function receiptCacheKey(string $sessionId): string
    {
        return "linkly_receipt_{$sessionId}";
    }

    /**
     * Defense in depth for the "never log/store sensitive cardholder data" requirement:
     * Linkly's own receipt text is expected to already carry a masked PAN (industry-standard
     * truncated form), but this blanks any run of 8+ consecutive digits anyway before the
     * text is ever cached or logged, in case a misconfigured terminal/merchant profile were
     * to emit an unmasked one.
     */
    private static function maskPossiblePan(string $text): string
    {
        return preg_replace('/\d{8,}/', '[MASKED]', $text) ?? '[MASKED]';
    }

    /**
     * Records a "receipt" postback from Linkly (the merchant/customer receipt text for a
     * completed transaction) — kept only so the accreditation admin pane has something to
     * show for "receipt handling", per Core Payments' receipt requirement. Never used to
     * decide payment_status; that still only ever comes from pollTransaction()'s GET.
     *
     * @param array<mixed> $lines
     */
    public static function recordReceipt(string $sessionId, array $lines): void
    {
        $cleaned = array_map(
            fn ($l) => self::maskPossiblePan(trim((string) $l)),
            self::cleanDisplayLines($lines)
        );
        Cache::put(self::receiptCacheKey($sessionId), $cleaned, 900);
    }

    /**
     * @return array<int, string>
     */
    public static function getReceipt(string $sessionId): array
    {
        return Cache::get(self::receiptCacheKey($sessionId), []);
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
