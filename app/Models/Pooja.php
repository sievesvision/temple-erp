<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pooja extends Model
{
    protected $table = 'poojas';

    protected $primaryKey = 'pooja_id';

    protected $guarded = [];

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'pooja_id', 'pooja_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
