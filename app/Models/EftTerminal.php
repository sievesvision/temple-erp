<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One independently-pairable EFT terminal (a physical PIN pad, or a virtual test one) — each
 * holds its own Linkly Cloud pairing secret per mode (sandbox/live) and its own posId, so
 * several can be paired and used at once (e.g. one station on the Ticket Kiosk, another on an
 * event's donation POS, running simultaneously without interfering). Everything else Linkly
 * needs (username/password/posVendorId/base URLs) stays global on LinklyConfigService — only
 * the pairing secret and posId actually differ per physical terminal.
 */
class EftTerminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'pos_id',
        'secret_sandbox',
        'secret_live',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * The terminal used whenever a caller doesn't specify one (an old cached POS page, a
     * webhook-driven lookup that finds no ledger row, or a fresh install with only one
     * terminal ever paired) — exactly one row should carry is_default=true (enforced by
     * TerminalController's own logic, not a DB constraint, mirroring how Setting defaults
     * work elsewhere in this app).
     */
    public static function default(): ?self
    {
        return self::where('is_default', true)->first() ?? self::orderBy('id')->first();
    }

    /**
     * Resolves a terminal by id if given and valid, falling back to the default terminal —
     * the one place every EFT-starting controller action decides "which terminal", so a
     * missing/invalid id never silently 500s but always degrades to the default.
     */
    public static function resolveOrDefault($terminalId): ?self
    {
        if ($terminalId) {
            $terminal = self::find($terminalId);
            if ($terminal) {
                return $terminal;
            }
        }

        return self::default();
    }

    public function secret(string $mode): ?string
    {
        return $mode === 'live' ? $this->secret_live : $this->secret_sandbox;
    }

    public function isPaired(string $mode): bool
    {
        return $this->secret($mode) !== null;
    }

    public function setSecret(string $mode, string $secret): void
    {
        $this->update($mode === 'live' ? ['secret_live' => $secret] : ['secret_sandbox' => $secret]);
    }

    public function clearSecret(string $mode): void
    {
        $this->update($mode === 'live' ? ['secret_live' => null] : ['secret_sandbox' => null]);
    }

    public static function generatePosId(): string
    {
        return (string) Str::uuid();
    }

    public function linklyTransactions()
    {
        return $this->hasMany(LinklyTransaction::class);
    }
}
