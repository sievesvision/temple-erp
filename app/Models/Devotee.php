<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devotee extends Model
{
protected $primaryKey = 'devotee_id';

protected $fillable = [
    'user_id',
    'address',
    'gothra',
    'nakshatra',
    'gender',
    'dob',
    'verified'
];

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
}
