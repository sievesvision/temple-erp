<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Minimum amount an operator can start an EFT Terminal purchase for — was a hardcoded
 * `min:1` on both CbaSciController::startPurchase() (mx51) and DonationController::
 * startEftCharge() (Linkly), duplicated as a magic number in two controllers. Common storage
 * across both providers, same reasoning as EftReceiptSettings.
 */
class EftTransactionLimits
{
    public static function minimumAmount(): float
    {
        return (float) Setting::get('eft_minimum_transaction_amount', 1);
    }
}
