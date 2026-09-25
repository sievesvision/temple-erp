<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One PIN per (user, destination) — see the create_kiosk_pins_table migration's docblock
 * for why a destination-scoped PIN replaced the old single-PIN-per-account model.
 */
class KioskPin extends Model
{
    protected $fillable = [
        'user_id',
        'destination_type',
        'destination_id',
        'pin',
        'pin_set_at',
    ];

    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'pin_set_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
