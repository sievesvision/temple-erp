<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One independently-pairable EFT terminal (a physical PIN pad, or a virtual test one) — each
 * holds its own pairing state for whichever provider it speaks (Linkly Cloud, or CBA Smart
 * Terminal via mx51's Simple Cloud Integration), so several can be paired and used at once
 * (e.g. one station on the Ticket POS, another on an event's donation POS, running
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
        'sci_last_checked_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        // The only genuinely secret column here — the merchant-held Signing Secret Part A
        // never touches the database at all (see config('services.cba_sci')) — encrypted at
        // rest, transparently decrypted on read, and excluded from array/JSON output below
        // so it can never leak into a view or an API response by accident.
        'sci_signing_secret_part_b' => 'encrypted',
        'sci_paired_at' => 'datetime',
        'sci_last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_sandbox',
        'secret_live',
        'sci_signing_secret_part_b',
    ];

    /**
     * The terminal used whenever a caller doesn't specify one. Checks the given user's own
     * `preferred_eft_terminal_id` first (set whenever they pick a terminal in a POS page's
     * terminal picker — see resolveOrDefault() below) — this is what makes the default
     * genuinely per-operator rather than per-device, so two staff sharing one POS computer,
     * or one staff member moving between computers, each still land on their own terminal.
     * Falls back to the registry's own is_default row (an old cached POS page, a
     * webhook-driven lookup that finds no ledger row, a user with no preference yet, or a
     * fresh install with only one terminal ever paired) — exactly one row should carry
     * is_default=true (enforced by EftTerminalController's own logic, not a DB constraint,
     * mirroring how Setting defaults work elsewhere in this app).
     */
    public static function default(?\App\Models\User $user = null): ?self
    {
        $user = $user ?? \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->preferred_eft_terminal_id) {
            $preferred = self::find($user->preferred_eft_terminal_id);
            if ($preferred) {
                return $preferred;
            }
        }

        return self::where('is_default', true)->first() ?? self::orderBy('id')->first();
    }

    /**
     * Resolves a terminal by id if given and valid, falling back to the default terminal —
     * the one place every EFT-starting controller action decides "which terminal", so a
     * missing/invalid id never silently 500s but always degrades to the default.
     *
     * When $user is given and an explicit, valid $terminalId was passed (i.e. the operator
     * actually chose one, rather than this being a fallback resolution), that choice is
     * remembered as their new preferred terminal — this is the one place that write happens
     * for every charge-starting flow, so the POS terminal picker's own "save my pick" fetch
     * (see event-pos-donation.blade.php/ticket-pos.blade.php) is a nice-to-have fast path,
     * not the only path: even picking via an older client that skips that fetch still
     * remembers correctly the next time a charge actually starts.
     */
    public static function resolveOrDefault($terminalId, ?\App\Models\User $user = null): ?self
    {
        $user = $user ?? \Illuminate\Support\Facades\Auth::user();

        if ($terminalId) {
            $terminal = self::find($terminalId);
            if ($terminal) {
                if ($user && $user->preferred_eft_terminal_id !== $terminal->id) {
                    $user->update(['preferred_eft_terminal_id' => $terminal->id]);
                }

                return $terminal;
            }
        }

        return self::default($user);
    }

    public function secret(string $mode): ?string
    {
        return $mode === 'live' ? $this->secret_live : $this->secret_sandbox;
    }

    public function isPaired(string $mode): bool
    {
        return $this->secret($mode) !== null;
    }

    /**
     * Provider-agnostic "is this terminal usable right now" check for the terminal picker —
     * branches on $provider the same way lastKnownStatus() does, so a CBA SCI row is never
     * judged by Linkly's secret_live/secret_sandbox columns (which it never populates).
     */
    public function isPairedFor(string $linklyMode): bool
    {
        return $this->provider === 'cba_sci' ? $this->isSciPaired() : $this->isPaired($linklyMode);
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

        // An unpaired terminal has no live connection to report on — without this check,
        // evidence from before it was unpaired (a past approved transaction, say) would keep
        // making it look reachable indefinitely, even though there is currently nothing to
        // reach it through.
        if (!$this->isPaired(\App\Services\LinklyConfigService::mode())) {
            return ['state' => 'unknown', 'at' => null, 'via' => null];
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
     *
     * A terminal with no transactions yet (the common case right after pairing) has nothing
     * to infer from there, so a successful pairing-info check (CbaSciService::testPairing(),
     * via the Test button or the proactive self-heal) is also considered — whichever reading
     * is more recent wins. Only a *successful* check is ever recorded this way: a failed one
     * means the pairing is the problem, not proof the device is offline, so it's never used
     * to claim "offline" here (that stays exclusively DEVICE_NOT_CONNECTED's job).
     */
    private function lastKnownSciStatus(): array
    {
        // Same reasoning as the Linkly branch above — once unpaired, a past FINALISED
        // transaction or successful pairing check no longer proves anything about whether
        // this terminal is reachable right now (a fresh re-pair would need its own fresh
        // evidence anyway), so it must never be read as still "online".
        if (!$this->isSciPaired()) {
            return ['state' => 'unknown', 'at' => null, 'via' => null];
        }

        $txn = $this->sciTransactions()
            ->where(function ($q) {
                $q->where('status', 'FINALISED')
                    ->orWhereJsonContains('meta->error_code', 'device_not_connected');
            })
            ->latest('id')
            ->first();

        $txnReading = null;
        if ($txn) {
            $isDeviceError = ($txn->meta['error_code'] ?? null) === 'device_not_connected';
            $txnReading = ['state' => $isDeviceError ? 'offline' : 'online', 'at' => $txn->updated_at, 'via' => $txn->txn_type];
        }

        $checkReading = $this->sci_last_checked_at
            ? ['state' => 'online', 'at' => $this->sci_last_checked_at, 'via' => 'pairing check']
            : null;

        if ($txnReading && $checkReading) {
            return $txnReading['at']->gte($checkReading['at']) ? $txnReading : $checkReading;
        }

        return $txnReading ?? $checkReading ?? ['state' => 'unknown', 'at' => null, 'via' => null];
    }
}
