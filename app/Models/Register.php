<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A physical counter/station, identified by a long-lived signed cookie on its own browser
 * (see App\Http\Middleware\IdentifyRegister) rather than by whichever staff member is
 * currently PIN-logged in there. Paired once via a short numeric code (see
 * App\Services\RegisterService), then serves whichever event or ticket-sales context it's
 * pointed at over time — each with its own configured default EFT terminal (see
 * registerEftTerminalDefaults() and EftTerminal::defaultForContext()).
 */
class Register extends Model
{
    protected $fillable = [
        'name', 'token', 'paired_at', 'last_seen_at', 'created_by',
    ];

    protected $casts = [
        // Compare-only (Hash::check against the register_token cookie value), never
        // decrypted/reused — same choice as kiosk_devices.credential_token / users.pos_pin.
        'token' => 'hashed',
        'paired_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function pairingCodes()
    {
        return $this->hasMany(RegisterPairingCode::class);
    }

    public function isPaired(): bool
    {
        return $this->token !== null;
    }

    public function touchActivity(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * This register's configured default terminal for one context (an event, or ticket
     * sales) — null if nothing's been configured for it, in which case
     * EftTerminal::defaultForContext() falls through to the existing resolution chain.
     */
    public function defaultTerminalFor(string $contextType, ?int $contextId): ?EftTerminal
    {
        $terminalId = \Illuminate\Support\Facades\DB::table('register_eft_terminal_defaults')
            ->where('register_id', $this->id)
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->value('eft_terminal_id');

        return $terminalId ? EftTerminal::find($terminalId) : null;
    }
}
