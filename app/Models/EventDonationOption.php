<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventDonationOption extends Model
{
    protected $table = 'event_donation_options';

    protected $fillable = [
        'event_id',
        'label',
        'amount',
        'allow_quantity',
        'sort_order',
    ];

    protected $casts = [
        'allow_quantity' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }
}
