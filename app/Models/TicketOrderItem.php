<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a TicketOrder — a ticket type + quantity, with the name/price snapshotted at
 * sale time so a later catalog edit never rewrites what a past order actually charged.
 */
class TicketOrderItem extends Model
{
    protected $fillable = [
        'ticket_order_id',
        'ticket_id',
        'ticket_name',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function stubs()
    {
        return $this->hasMany(TicketStub::class);
    }
}
