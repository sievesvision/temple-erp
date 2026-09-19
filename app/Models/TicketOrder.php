<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One completed ticket sale (a "cart" of one or more ticket types + quantities) — the
 * standalone-module counterpart to a guest donation. See TicketOrderItem for the line items
 * and TicketStub for the individually-printed physical stubs each item's quantity produces.
 */
class TicketOrder extends Model
{
    protected $fillable = [
        'customer_name',
        'email',
        'mobile',
        'total_amount',
        'payment_method',
        'payment_status',
        'transaction_id',
        'order_date',
        'sold_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'order_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by');
    }
}
