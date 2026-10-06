<?php

namespace App\Services;

use App\Models\EftTerminal;

/**
 * The terminal-registry data behind admin.partials.eft-terminal-registry — the shared
 * pairing/registry UI @include'd verbatim on the standalone EFT Terminal Settings page,
 * both the event and ticket consoles' own EFT Terminal Settings pane, and Admin Settings'
 * EFT Terminal panel. Kept in one place so all four call sites group terminals into
 * active/inactive and compute "all systems operational" the exact same way.
 */
class EftTerminalRegistryView
{
    /**
     * @return array{eftTerminals: \Illuminate\Support\Collection, linklyMode: string, cbaSciMode: string}
     */
    public static function fetch(): array
    {
        $eftTerminals = EftTerminal::orderByDesc('is_default')->orderBy('label')->get();
        $linklyMode = LinklyConfigService::mode();
        // Each provider has its own independent sandbox/live switch — a terminal's own "Mode"
        // badge must read whichever one actually applies to it, not always Linkly's.
        $cbaSciMode = CbaSciConfigService::mode();

        return compact('eftTerminals', 'linklyMode', 'cbaSciMode');
    }

    /**
     * @return array{activeTerminals: \Illuminate\Support\Collection, inactiveTerminals: \Illuminate\Support\Collection, allOperational: bool}
     */
    public static function groups(\Illuminate\Support\Collection $eftTerminals, string $linklyMode): array
    {
        // A terminal that isn't currently paired can't take a payment, so it's grouped apart
        // from the terminals actually usable right now rather than mixed in with them.
        $activeTerminals = $eftTerminals->filter(fn ($t) => $t->isPairedFor($linklyMode))->values();
        $inactiveTerminals = $eftTerminals->reject(fn ($t) => $t->isPairedFor($linklyMode))->values();

        // "Not checked" isn't a known-bad state — only a genuine "offline" reading on an
        // active terminal should ever hold this back, an unqueried terminal is optimistically
        // assumed fine until proven otherwise, same as the per-terminal badge does.
        $allOperational = $activeTerminals->isNotEmpty()
            && $activeTerminals->every(fn ($t) => $t->lastKnownStatus()['state'] !== 'offline');

        return compact('activeTerminals', 'inactiveTerminals', 'allOperational');
    }

    /**
     * Live-checks every currently-SCI-paired terminal against mx51's own GET /pairing-info
     * (see CbaSciService::refreshPairingStatus()), silently self-correcting one that's
     * actually been unpaired on mx51's own side — nothing pushes an unpair notification to
     * this app, so without this the "Paired" badge can only ever go stale, never recover, on
     * whichever page skips it. Must run BEFORE groups() partitions into active/inactive, since
     * a self-heal can move a terminal from one bucket to the other.
     */
    public static function selfHealSciPairings(\Illuminate\Support\Collection $eftTerminals): void
    {
        foreach ($eftTerminals as $eftTerminal) {
            if ($eftTerminal->provider === 'cba_sci' && $eftTerminal->isSciPaired()) {
                CbaSciService::refreshPairingStatus($eftTerminal);
            }
        }
    }

    /**
     * fetch() + groups() together. Pass $selfHeal = true wherever an operator is specifically
     * looking at pairing status right now — the standalone EFT Terminal Settings page, and
     * each event's/Tickets' own console EFT Terminal Settings pane — so a terminal unpaired on
     * mx51's own side (e.g. from the POS's own terminal picker) is never shown as still
     * "Paired" there just because this particular page never re-checked. Left false (the
     * default) anywhere that merely needs the list/counts incidentally, since a live mx51 API
     * round-trip per terminal isn't free.
     */
    public static function data(bool $selfHeal = false): array
    {
        $fetched = self::fetch();

        if ($selfHeal) {
            self::selfHealSciPairings($fetched['eftTerminals']);
        }

        return array_merge($fetched, self::groups($fetched['eftTerminals'], $fetched['linklyMode']));
    }
}
