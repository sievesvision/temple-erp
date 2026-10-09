<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A short-lived, single-use code an admin generates (App\Services\KioskDeviceService::
 * generatePairingCode()) and the kiosk device itself redeems (redeemPairingCode()) to
 * receive its long-lived credential. See that service for the full generate/redeem flow.
 */
class KioskPairingCode extends Model
{
    protected $fillable = [
        'kiosk_device_id', 'code_hash', 'expires_at', 'redeemed_at', 'redeemed_ip', 'generated_by',
    ];

    protected $casts = [
        'code_hash' => 'hashed',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected $hidden = ['code_hash'];

    public function device()
    {
        return $this->belongsTo(KioskDevice::class, 'kiosk_device_id');
    }
}
