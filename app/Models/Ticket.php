<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One sellable ticket type in the standalone ticket catalog (e.g. "Adult Entry", "Prasadam
 * Coupon") — not tied to any Event. Sold via TicketOrder/TicketOrderItem; see TicketController.
 */
class Ticket extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
