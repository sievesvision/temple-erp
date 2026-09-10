<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationSelection extends Model
{
    protected $table = 'donation_selections';

    protected $fillable = [
        'donation_type',
        'donation_id',
        'event_donation_option_id',
        'option_label',
        'quantity',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];
}
