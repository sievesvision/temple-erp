<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Talks to Linkly's Cloud REST API to drive a physically-paired EFTPOS PIN pad from this
 * app — pairing (one-time per terminal), token exchange (per session), and the actual
 * purchase request. See LinklyConfigService for how Sandbox vs Live credentials/secrets
 * are resolved; this class only ever asks that service for them, never reads config()/
 * Setting directly, so switching modes never requires touching this file.
 *
 * Reference: https://linkly.com.au/apidoc/REST/ — AmtPurchase (and every Amt* field) is in
 * CENTS, not dollars; getting that wrong would over/undercharge donors by 100x.
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
            return ['success' => false, 'message' => 'Pairing failed: ' . ($response->json('message') ?? $response->body())];
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

    /**
     * Drives a purchase transaction on the paired PIN pad and waits for the result —
     * synchronous (async=false), since the POS page's "Save Donation" flow already waits
     * for a response from the server either way. $amount is in dollars; Linkly's API wants
     * cents.
     *
     * @return array{success: bool, message: string, response_code: ?string, auth_code: ?string, rrn: ?string, txn_ref: string}
     */
    public static function purchase(float $amount, string $txnRef, string $currencyCode = 'AUD'): array
    {
        if (!LinklyConfigService::isPaired()) {
            return ['success' => false, 'message' => 'No EFT terminal is paired yet.', 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => $txnRef];
        }

        $token = self::getToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Could not authenticate with the EFT terminal service.', 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => $txnRef];
        }

        $sessionId = (string) Str::uuid();
        // TxnRef is capped at 16 characters by the terminal.
        $txnRef = substr($txnRef, 0, 16);

        try {
            $response = Http::timeout(90)
                ->withToken($token)
                ->post(LinklyConfigService::apiBaseUrl() . "/v1/sessions/{$sessionId}/transaction?async=false", [
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
                ]);
        } catch (\Exception $e) {
            Log::error('Linkly purchase request exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Could not reach the EFT terminal service: ' . $e->getMessage(), 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => $txnRef];
        }

        if (!$response->successful()) {
            Log::warning('Linkly purchase failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'message' => 'EFT terminal request failed (' . $response->status() . ').', 'response_code' => null, 'auth_code' => null, 'rrn' => null, 'txn_ref' => $txnRef];
        }

        // The live API returns lowercase-first camelCase keys ("response", "success",
        // "responseCode", "authCode", "rrn", "txnRef") — confirmed directly against a real
        // sandbox transaction; Linkly's own docs page rendered these as PascalCase when
        // fetched, which does not match what the API actually sends.
        $txnResponse = $response->json('response') ?? [];
        $success = (bool) ($txnResponse['success'] ?? false);

        return [
            'success' => $success,
            'message' => $success
                ? 'Approved' . (!empty($txnResponse['authCode']) ? ' — Auth ' . $txnResponse['authCode'] : '')
                : trim($txnResponse['responseText'] ?? 'Declined'),
            'response_code' => $txnResponse['responseCode'] ?? null,
            'auth_code' => !empty($txnResponse['authCode']) ? $txnResponse['authCode'] : null,
            'rrn' => !empty(trim($txnResponse['rrn'] ?? '')) ? trim($txnResponse['rrn']) : null,
            'txn_ref' => trim($txnResponse['txnRef'] ?? $txnRef),
        ];
    }
}
