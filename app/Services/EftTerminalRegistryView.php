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
        // from the terminals actually usable right now rather than mixed in with them — this
        // is also where a retired terminal with recorded transaction history ends up once
        // unpaired, since EftTerminalController::destroy() refuses to delete it outright.
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
     * fetch() + groups() together, for the (common) case of a page that just wants to render
     * the registry as it stands right now without also running the mx51 self-heal check that
     * only EftTerminalController::index() performs (see that method's own docblock for why
     * that check stays a one-place-only side effect rather than something every hosting page
     * repeats on every load).
     */
    public static function data(): array
    {
        $fetched = self::fetch();

        return array_merge($fetched, self::groups($fetched['eftTerminals'], $fetched['linklyMode']));
    }
}
