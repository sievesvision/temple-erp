<?php

namespace App\Services;

use App\Models\Register;
use App\Models\RegisterPairingCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Register pairing and per-context EFT terminal default lifecycle. Mirrors
 * KioskDeviceService's own pairing-code shape (generate a short code, store only its hash,
 * redeem once for a long-lived credential) — the one deliberate divergence is that a
 * register's credential is read back as a browser cookie on every request (see
 * IdentifyRegister), not an API header, since a register is a normal staff-operated page
 * load, not a fetch-driven device.
 */
class RegisterService
{
    private const PAIRING_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no 0/O/1/I/L — unambiguous on a touchscreen
    private const PAIRING_CODE_LENGTH = 8;
    private const PAIRING_CODE_TTL_MINUTES = 10;
    public const COOKIE_NAME = 'register_token';
    public const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365 * 5; // ~5 years — a register is meant to stay paired indefinitely

    public static function create(string $name, User $admin): Register
    {
        $register = Register::create(['name' => $name, 'created_by' => $admin->id]);

        AuditLogService::log("Created register '{$register->name}' (#{$register->id})", $admin->id);

        return $register;
    }

    public static function rename(Register $register, string $name, User $admin): void
    {
        $register->update(['name' => $name]);
        AuditLogService::log("Renamed register #{$register->id} to '{$name}'.", $admin->id);
    }

    /**
     * Generates a short, human-enterable one-time code, stores only its hash, and returns
     * the plaintext exactly once — never logged, never persisted anywhere, never re-shown.
     */
    public static function generatePairingCode(Register $register, User $admin): string
    {
        $alphabet = self::PAIRING_CODE_ALPHABET;
        $code = '';
        for ($i = 0; $i < self::PAIRING_CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        RegisterPairingCode::create([
            'register_id' => $register->id,
            'code_hash' => $code,
            'expires_at' => now()->addMinutes(self::PAIRING_CODE_TTL_MINUTES),
            'generated_by' => $admin->id,
        ]);

        AuditLogService::log("Generated a pairing code for register '{$register->name}' (#{$register->id})", $admin->id);

        return $code;
    }

    /**
     * @return array{success: bool, token?: string, register?: Register, message: string}
     */
    public static function redeemPairingCode(string $code, string $ip): array
    {
        $candidates = RegisterPairingCode::whereNull('redeemed_at')
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
            AuditLogService::log('Register pairing attempt failed (invalid or expired code)');
            return ['success' => false, 'message' => 'That pairing code is invalid or has expired.'];
        }

        $register = $match->register;
        $token = Str::random(64);

        $match->update(['redeemed_at' => now(), 'redeemed_ip' => $ip]);
        $register->update(['token' => $token, 'paired_at' => now(), 'last_seen_at' => now()]);

        AuditLogService::log("Paired register '{$register->name}' (#{$register->id})");

        return ['success' => true, 'token' => $token, 'register' => $register, 'message' => 'Register paired.'];
    }

    /**
     * Bounded Hash::check scan over paired registers only — at real-world register-count
     * scale (a handful of counters per temple) this is trivially cheap, same reasoning as
     * KioskDeviceService::authenticate().
     */
    public static function authenticate(string $token): ?Register
    {
        foreach (Register::whereNotNull('token')->get() as $register) {
            if (Hash::check($token, $register->token)) {
                return $register;
            }
        }

        return null;
    }

    public static function unpair(Register $register, User $admin): void
    {
        $register->update(['token' => null, 'paired_at' => null]);
        AuditLogService::log("Unpaired register '{$register->name}' (#{$register->id})", $admin->id);
    }

    public static function setDefaultTerminal(Register $register, string $contextType, ?int $contextId, ?int $terminalId, User $admin): void
    {
        $scope = ['register_id' => $register->id, 'context_type' => $contextType, 'context_id' => $contextId];

        if ($terminalId === null) {
            DB::table('register_eft_terminal_defaults')->where($scope)->delete();
        } elseif (DB::table('register_eft_terminal_defaults')->where($scope)->exists()) {
            DB::table('register_eft_terminal_defaults')->where($scope)->update(['eft_terminal_id' => $terminalId, 'updated_at' => now()]);
        } else {
            DB::table('register_eft_terminal_defaults')->insert($scope + ['eft_terminal_id' => $terminalId, 'created_at' => now(), 'updated_at' => now()]);
        }

        AuditLogService::log("Set default terminal for register '{$register->name}' / {$contextType}" . ($contextId ? " #{$contextId}" : '') . '.', $admin->id);
    }
}
