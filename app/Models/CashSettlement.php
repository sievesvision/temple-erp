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
        'closing_balance',
        'breakdown',
        'performed_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'opening_balance' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'amount_banked' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'breakdown' => 'array',
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
