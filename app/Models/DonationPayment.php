<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One actual payment event against a donation/pledge — the ledger of truth for every
 * money-movement event, old and new (see the 2026_11_03_000000_create_donation_payments_table
 * migration's docblock for the full architecture). A donation/donations_without_logins row
 * can have zero (a pure Pledge), one, or many of these; donation_type+donation_id is the same
 * loose, non-FK polymorphic pair sci_transactions/linkly_transactions already use.
 */
class DonationPayment extends Model
{
    public const STATUSES = ['Pending', 'Completed', 'Failed', 'Cancelled', 'Refunded'];

    protected $fillable = [
        'donation_type',
        'donation_id',
        'amount',
        'payment_method',
        'payment_status',
        'transaction_id',
        'sci_transaction_id',
        'linkly_transaction_id',
        'notes',
        'received_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function sciTransaction()
    {
        return $this->belongsTo(SciTransaction::class);
    }

    public function linklyTransaction()
    {
        return $this->belongsTo(LinklyTransaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
