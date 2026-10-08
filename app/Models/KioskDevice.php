<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A registered self-service kiosk device. Created `pending` by an admin (see
 * App\Services\KioskDeviceService::register()), becomes `active` once paired via a
 * short-lived one-time code (see KioskPairingCode), and can be deactivated/revoked by
 * whoever App\Services\KioskAccess::canManageRegistry() trusts with the registry.
 *
 * module/event_id/eft_terminal_id/enabled_payment_methods are the kiosk's own payment/scope
 * configuration — admin-set at registration or afterwards, never self-configured by the
 * device. Editing them specifically is gated by the finer KioskAccess::canConfigure() check
 * (module/event-scoped), not the broader canManageRegistry() every other action uses.
 */
class KioskDevice extends Model
{
    protected $fillable = [
        'device_uuid', 'name', 'label', 'type', 'status', 'module', 'event_id',
        'eft_terminal_id', 'enabled_payment_methods', 'credential_token',
        'credential_issued_at', 'credential_rotated_at', 'last_activity_at',
        'last_activity_ip', 'registered_by', 'revoked_by', 'revoked_at',
    ];

    protected $casts = [
        // Compare-only (Hash::check against the X-Kiosk-Device-Token header value), never
        // decrypted/reused — unlike EftTerminal.sci_signing_secret_part_b, which must be
        // read back in plaintext to sign outgoing requests. Same choice as PosPin.pin.
        'credential_token' => 'hashed',
        'enabled_payment_methods' => 'array',
        'credential_issued_at' => 'datetime',
        'credential_rotated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['credential_token'];

    public function pairingCodes()
    {
        return $this->hasMany(KioskPairingCode::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function eftTerminal()
    {
        return $this->belongsTo(EftTerminal::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function touchActivity(string $ip): void
    {
        $this->update(['last_activity_at' => now(), 'last_activity_ip' => $ip]);
    }

    /**
     * Falls back to the terminal registry's own existing default-resolution exactly the way
     * every other EFT-capable surface in this app already does — "default options can be
     * auto set" rather than forcing an admin to pick one at registration time.
     */
    public function effectiveEftTerminal(): ?EftTerminal
    {
        return $this->eftTerminal ?? EftTerminal::resolveOrDefault(null, null);
    }

    /**
     * Falls back to the same global Setting-driven default every other payment-method
     * surface uses when this kiosk has no override of its own.
     */
    public function effectivePaymentMethods(): array
    {
        return $this->enabled_payment_methods
            ?? json_decode(Setting::get('enabled_payment_methods', '["Cash","Bank Transfer","Cheque"]'), true);
    }
}
