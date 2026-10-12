<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A locked, point-in-time cash reconciliation snapshot for one scope ('event' + event_id,
 * or 'tickets' + null event_id). Once created, a settlement's figures are historical
 * record — the next settlement's opening_balance is simply this row's closing_balance, so
 * settlements never need to be recomputed from the full transaction history.
 */
class CashSettlement extends Model
{
    protected $fillable = [
        'scope',
        'event_id',
        'period_start',
        'period_end',
        'opening_balance',
        'cash_received',
        'amount_banked',
        'till_count',
        'closing_balance',
        'variance',
        'variance_note',
        'breakdown',
        'performed_by',
        'transaction_refs',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'amount_banked' => 'decimal:2',
        'till_count' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'variance' => 'decimal:2',
        'breakdown' => 'array',
        // The exact {type, id} pairs (donation_type-style 'devotee'|'guest'|'ticket_order',
        // plus 'banking' for the CashBanking row this settlement's own deposit created) this
        // settlement claimed at the moment it was run — see CashSettlementService's own
        // docblock on why period membership is decided by this exact snapshot rather than by
        // comparing timestamps (a plain `timestamp` column only has one-second resolution,
        // nowhere near enough to order two things recorded moments apart).
        'transaction_refs' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
