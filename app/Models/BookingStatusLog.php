<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingStatusLog extends Model
{
    protected $table = 'booking_status_logs';

    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }
}
