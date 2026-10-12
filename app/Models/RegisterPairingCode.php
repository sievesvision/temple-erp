<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A short-lived, single-use code an admin generates (App\Services\RegisterService::
 * generatePairingCode()) and the register's own browser redeems (pair()) to receive its
 * long-lived cookie token. Mirrors KioskPairingCode exactly.
 */
class RegisterPairingCode extends Model
{
    protected $fillable = [
        'register_id', 'code_hash', 'expires_at', 'redeemed_at', 'redeemed_ip', 'generated_by',
    ];

    protected $casts = [
        'code_hash' => 'hashed',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected $hidden = ['code_hash'];

    public function register()
    {
        return $this->belongsTo(Register::class);
    }
}
