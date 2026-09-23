<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One independently-pairable EFT terminal (a physical PIN pad, or a virtual test one) — each
 * holds its own pairing state for whichever provider it speaks (Linkly Cloud, or CBA Smart
 * Terminal via mx51's Simple Cloud Integration), so several can be paired and used at once
 * (e.g. one station on the Ticket Kiosk, another on an event's donation POS, running
 * simultaneously without interfering) regardless of provider. `provider` says which set of
 * columns below is meaningful for a given row: Linkly's own username/password/posVendorId/
 * base URLs stay global on LinklyConfigService (only the pairing secret and posId differ
 * per terminal), and SCI's Pairing API Key + Signing Secret Part A stay global on
 * CbaSciConfigService the same way — only the sci_* columns here (all returned by mx51's own
 * pairing response) differ per terminal.
 */
class EftTerminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'provider',
        'pos_id',
        'secret_sandbox',
        'secret_live',
        'is_default',
        'sci_pairing_id',
        'sci_key_id',
        'sci_signing_secret_part_b',
        'sci_api_base_url',
        'sci_confirmation_code',
        'sci_tid',
        'sci_pairing_nickname',
        'sci_terminal_nickname',
        'sci_paired_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        // The only genuinely secret column here — the merchant-held Signing Secret Part A
        // never touches the database at all (see config('services.cba_sci')) — encrypted at
        // rest, transparently decrypted on read, and excluded from array/JSON output below
        // so it can never leak into a view or an API response by accident.
        'sci_signing_secret_part_b' => 'encrypted',
        'sci_paired_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_sandbox',
        'secret_live',
        'sci_signing_secret_part_b',
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

    public function sciTransactions()
    {
        return $this->hasMany(SciTransaction::class);
    }

    public function isSciPaired(): bool
    {
        return $this->provider === 'cba_sci' && $this->sci_pairing_id !== null;
    }

    /**
     * "Online" isn't a persistent flag — neither provider has a standing connection to poll
     * — so this infers it from the most recent transaction that actually got a definitive
     * response from the terminal, branching on which protocol this row speaks.
     *
     * @return array{state: 'online'|'offline'|'unknown', at: ?\Illuminate\Support\Carbon, via: ?string}
     */
    public function lastKnownStatus(): array
    {
        if ($this->provider === 'cba_sci') {
            return $this->lastKnownSciStatus();
        }

        // Linkly: 'approved' or 'declined' both mean the terminal was reached and responded
        // (a decline is still a real response, just not a successful one); 'failed' (Linkly's
        // own bucket for a timeout/system error — see LinklyEftService::mapResponseToStatus())
        // means it wasn't. Purely in-flight/inconclusive statuses (initiated/in_progress/
        // unknown) are skipped since they prove nothing either way.
        $txn = $this->linklyTransactions()
            ->whereIn('status', ['approved', 'declined', 'failed'])
            ->latest('id')
            ->first();

        if (!$txn) {
            return ['state' => 'unknown', 'at' => null, 'via' => null];
        }

        return [
            'state' => $txn->status === 'failed' ? 'offline' : 'online',
            'at' => $txn->created_at,
            'via' => $txn->txn_type,
        ];
    }

    /**
     * SCI's own vocabulary: a FINALISED transaction (whatever its financial outcome) proves
     * the terminal was reached and responded; a DEVICE_NOT_CONNECTED failure (recorded onto
     * meta.error_code by CbaSciService — see its class docblock) proves it wasn't.
     */
    private function lastKnownSciStatus(): array
    {
        $txn = $this->sciTransactions()
            ->where(function ($q) {
                $q->where('status', 'FINALISED')
                    ->orWhereJsonContains('meta->error_code', 'device_not_connected');
            })
            ->latest('id')
            ->first();

        if (!$txn) {
            return ['state' => 'unknown', 'at' => null, 'via' => null];
        }

        $isDeviceError = ($txn->meta['error_code'] ?? null) === 'device_not_connected';

        return [
            'state' => $isDeviceError ? 'offline' : 'online',
            'at' => $txn->updated_at,
            'via' => $txn->txn_type,
        ];
    }
}
