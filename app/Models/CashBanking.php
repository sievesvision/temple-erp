<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One physical cash deposit to the bank — always a single lump sum (never split across
 * donation options/ticket types), logged against a scope ('event' + event_id, or
 * 'tickets' + null event_id). See CashSettlementService for how this feeds the running
 * "remaining cash to bank" figure.
 */
class CashBanking extends Model
{
    protected $fillable = [
        'scope',
        'event_id',
        'amount',
        'banked_date',
        'reference',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'banked_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
