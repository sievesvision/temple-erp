<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One CBA Smart Terminal (mx51 Simple Cloud Integration) transaction — a purchase or
 * refund taken through an EftTerminal with provider='cba_sci'. Kept separate from
 * LinklyTransaction since SCI's own lifecycle (PENDING/AWAITING_POS/FINALISED, a distinct
 * result_financial_status, and a pos_instructions Action Framework blob) has no Linkly
 * analogue — see the 2026_10_10_000000_add_cba_sci_support migration's docblock.
 */
class SciTransaction extends Model
{
    protected $fillable = [
        'client_ref',
        'sci_transaction_id',
        'sci_version',
        'event_id',
        'eft_terminal_id',
        'donation_type',
        'donation_id',
        'txn_type',
        'amount',
        'currency_code',
        'status',
        'result_financial_status',
        'result_amounts',
        'result_card_details',
        'pos_instructions',
        'message',
        'merchant_receipt',
        'customer_receipt',
        'initiated_by',
        'authorised_by',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'result_amounts' => 'array',
        'result_card_details' => 'array',
        'pos_instructions' => 'array',
        'meta' => 'array',
    ];

    /**
     * mx51's `data.status` reaching this means the transaction is done and will never move
     * again — see CbaSciService::pollTransaction()'s "stop polling" rule.
     */
    public const FINAL_STATUSES = ['FINALISED'];

    public function eftTerminal()
    {
        return $this->belongsTo(EftTerminal::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }
}
