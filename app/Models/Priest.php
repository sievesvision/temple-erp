<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Priest extends Model
{
    protected $table = 'priests';

    protected $primaryKey = 'priest_id';

    // priest_id is a free-text varchar PK (e.g. "PRIEST0001", assigned by the caller at
    // creation time, see SystemUserController's own priest_id generator) — without these,
    // Eloquent assumes an auto-incrementing integer key and silently fails to read back a
    // string PK's real value after create(), which breaks any Booking->priest() lookup.
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}