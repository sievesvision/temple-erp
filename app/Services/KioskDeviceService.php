<?php

namespace App\Services;

use App\Models\KioskDevice;
use App\Models\KioskPairingCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Kiosk device registration, pairing, and credential lifecycle. Mirrors CbaSciService's
 * all-static shape — see that class for the closest existing precedent this was modeled on
 * (pair a device via a one-time code, store a revocable credential, support rotation/
 * revocation) — with one deliberate divergence: the device credential is hashed
 * (compare-only, like PosPin.pin), not encrypted, since it's never read back in plaintext
 * the way mx51's own signing secret must be to sign outgoing requests.
 */
class KioskDeviceService
{
    private const PAIRING_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O/1/I/L — unambiguous on a touchscreen
    private const PAIRING_CODE_LENGTH = 8;
    private const PAIRING_CODE_TTL_MINUTES = 10;

    public static function register(
        string $name,
        ?string $label,
        ?string $module,
        ?int $eventId,
        ?int $eftTerminalId,
        ?array $enabledPaymentMethods,
        User $admin
    ): KioskDevice {
        return KioskDevice::create([
            'device_uuid' => (string) Str::uuid(),
            'name' => $name,
            'label' => $label,
            'module' => $module,
            'event_id' => $eventId,
            'eft_terminal_id' => $eftTerminalId,
            'enabled_payment_methods' => $enabledPaymentMethods,
            'status' => 'pending',
            'registered_by' => $admin->id,
        ]);
    }

    /**
     * Module/event/terminal/payment-methods only — kept separate from the lifecycle methods
     * below so the controller can gate it through KioskAccess::canConfigure() (module/event
     * scoped) rather than the broader canManageRegistry() every other action uses.
     */
    public static function updateConfiguration(KioskDevice $device, array $attrs, User $actor): void
    {
        $device->update([
            'module' => $attrs['module'] ?? $device->module,
            'event_id' => $attrs['event_id'] ?? null,
            'eft_terminal_id' => $attrs['eft_terminal_id'] ?? null,
            'enabled_payment_methods' => $attrs['enabled_payment_methods'] ?? null,
        ]);
    }

    /**
     * Generates a short, human-enterable one-time code, stores only its hash, and returns
     * the plaintext exactly once — never logged, never persisted anywhere, never re-shown.
     */
    public static function generatePairingCode(KioskDevice $device, User $admin): string
    {
        $alphabet = self::PAIRING_CODE_ALPHABET;
        $code = '';
        for ($i = 0; $i < self::PAIRING_CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        KioskPairingCode::create([
            'kiosk_device_id' => $device->id,
            'code_hash' => $code,
            'expires_at' => now()->addMinutes(self::PAIRING_CODE_TTL_MINUTES),
            'generated_by' => $admin->id,
        ]);

        AuditLogService::log("Generated a kiosk pairing code for device '{$device->name}' (#{$device->id})", $admin->id);

        return $code;
    }

    /**
     * @return array{success: bool, token?: string, device?: KioskDevice, message: string}
     */
    public static function redeemPairingCode(string $code, string $ip): array
    {
        $candidates = KioskPairingCode::whereNull('redeemed_at')
            ->where('expires_at', '>', now())
            ->get();

        $match = null;
        foreach ($candidates as $candidate) {
            if (Hash::check($code, $candidate->code_hash)) {
                $match = $candidate;
                break;
            }
        }

        if (!$match) {
            AuditLogService::log('Kiosk pairing attempt failed (invalid or expired code)');
            return ['success' => false, 'message' => 'That pairing code is invalid or has expired.'];
        }

        $device = $match->device;
        $token = Str::random(64);

        $match->update(['redeemed_at' => now(), 'redeemed_ip' => $ip]);
        $device->update([
            'credential_token' => $token,
            'credential_issued_at' => now(),
            'status' => 'active',
        ]);

        AuditLogService::log("Paired kiosk device '{$device->name}' (#{$device->id})");

        return ['success' => true, 'token' => $token, 'device' => $device, 'message' => 'Device paired.'];
    }

    /**
     * Bounded Hash::check scan over active, credentialed devices only — at real-world
     * kiosk-device-count scale (a handful per temple) this is trivially cheap.
     */
    public static function authenticate(string $token): ?KioskDevice
    {
        $devices = KioskDevice::where('status', 'active')->whereNotNull('credential_token')->get();

        foreach ($devices as $device) {
            if (Hash::check($token, $device->credential_token)) {
                return $device;
            }
        }

        return null;
    }

    public static function rotateCredential(KioskDevice $device, User $admin): string
    {
        $token = Str::random(64);
        $device->update([
            'credential_token' => $token,
            'credential_rotated_at' => now(),
        ]);

        AuditLogService::log("Rotated credential for kiosk device '{$device->name}' (#{$device->id})", $admin->id);

        return $token;
    }

    public static function activate(KioskDevice $device, User $admin): void
    {
        $device->update(['status' => 'active']);
        AuditLogService::log("Activated kiosk device '{$device->name}' (#{$device->id})", $admin->id);
    }

    public static function deactivate(KioskDevice $device, User $admin): void
    {
        $device->update(['status' => 'disabled']);
        AuditLogService::log("Deactivated kiosk device '{$device->name}' (#{$device->id})", $admin->id);
    }

    /**
     * Nulls the credential (not just a status flag) so a revoked device is locked out on the
     * status check alone in authenticate(), before any hash comparison even runs.
     */
    public static function revoke(KioskDevice $device, User $admin): void
    {
        $device->update([
            'status' => 'revoked',
            'credential_token' => null,
            'revoked_by' => $admin->id,
            'revoked_at' => now(),
        ]);

        AuditLogService::log("Revoked kiosk device '{$device->name}' (#{$device->id})", $admin->id);
    }
}
