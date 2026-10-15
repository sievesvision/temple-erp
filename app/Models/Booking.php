<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'pooja_bookings';

    protected $primaryKey = 'booking_id';

    protected $guarded = [];

    public function devotee()
    {
        return $this->belongsTo(Devotee::class, 'devotee_id', 'devotee_id');
    }

    public function pooja()
    {
        return $this->belongsTo(Pooja::class, 'pooja_id', 'pooja_id');
    }

    public function priest()
    {
        return $this->belongsTo(Priest::class, 'priest_id', 'priest_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(BookingStatusLog::class, 'booking_id', 'booking_id');
    }

    public function payments()
    {
        return $this->hasMany(BookingPayment::class, 'booking_id', 'booking_id');
    }

    public function scopeActive($query)
    {
        return $query->where('booking_status', '!=', 'Cancelled');
    }
}
