<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Receipt printing / signature-verification preferences — genuinely common, provider-wide
 * storage (not per-terminal, not per-provider) even though only mx51's Simple Cloud
 * Integration actually sends these to the gateway today (see CbaSciService::
 * createTransaction()) — Linkly's own transaction requests don't take them yet.
 *
 * Mirrors mx51's own Create Transaction request fields and recommended defaults exactly:
 *   print_merchant_receipt          — terminal prints the merchant receipt instead of the POS
 *   prompt_customer_receipt         — terminal prompts the customer about their receipt preference
 *   verify_signature_on_terminal    — terminal prints the signature receipt and handles all
 *                                     signature verification steps, instead of the POS
 *   pos_auto_print_signature_receipt — POS automatically prints the merchant receipt when a
 *                                     signature is required, rather than rendering a Print
 *                                     button
 * The first three default Off; pos_auto_print_signature_receipt defaults On — that's mx51's
 * own recommended default, not the raw API's (which is Off), so a merchant receipt for a
 * signature transaction is never silently skipped just because this was never configured.
 */
class EftReceiptSettings
{
    public static function printMerchantReceiptOnTerminal(): bool
    {
        return (bool) Setting::get('eft_print_merchant_receipt_on_terminal', false);
    }

    public static function promptCustomerReceiptOnTerminal(): bool
    {
        return (bool) Setting::get('eft_prompt_customer_receipt_on_terminal', false);
    }

    public static function verifySignatureOnTerminal(): bool
    {
        return (bool) Setting::get('eft_verify_signature_on_terminal', false);
    }

    public static function posAutoPrintSignatureReceipt(): bool
    {
        return (bool) Setting::get('eft_pos_auto_print_signature_receipt', true);
    }
}
