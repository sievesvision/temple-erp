<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Linkly Cloud session this app has started (Purchase, Refund or Logon) — the
 * accreditation-grade audit trail described in app/Services/LinklyEftService.php and
 * database/migrations/2026_09_30_000000_create_linkly_transactions_table.php. Never holds
 * PAN/PIN/track data; see those files for what is and isn't persisted.
 */
class LinklyTransaction extends Model
{
    protected $fillable = [
        'pos_txn_ref',
        'client_ref',
        'linkly_session_id',
        'txn_type',
        'event_id',
        'eft_terminal_id',
        'donation_type',
        'donation_id',
        'amount',
        'currency_code',
        'status',
        'response_code',
        'response_text',
        'auth_code',
        'rrn',
        'original_transaction_id',
        'initiated_by',
        'authorised_by',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * Statuses that mean "Linkly has given its final word" — used to decide whether a poll
     * result should overwrite this row (never overwrite a terminal outcome with a stale
     * in-progress one) and whether a purchase is eligible to be refunded again is blocked.
     */
    public const TERMINAL_STATUSES = ['approved', 'declined', 'cancelled', 'failed'];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function originalTransaction()
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    /**
     * Which physical terminal this session ran on — resolved once at start time and then
     * reused for every follow-up action against the same session (poll/cancel/sendkey/
     * reprint/refund), since a session is permanently tied to whichever terminal opened it.
     */
    public function eftTerminal()
    {
        return $this->belongsTo(EftTerminal::class);
    }

    public function refunds()
    {
        return $this->hasMany(self::class, 'original_transaction_id');
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function authoriser()
    {
        return $this->belongsTo(User::class, 'authorised_by');
    }
}
