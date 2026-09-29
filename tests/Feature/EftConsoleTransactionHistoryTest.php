<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\LinklyTransaction;
use App\Models\SciTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The event and ticket consoles' EFTPOS panes used to duplicate the Linkly-only pairing UI
 * already on the standalone EFT Terminal Settings page, and never showed mx51 transactions
 * at all. Both panes now link out to the real settings page instead of re-implementing
 * pairing, and their transaction history combines Linkly + mx51 into one list.
 */
class EftConsoleTransactionHistoryTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function createEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Combined History Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sciTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-console-' . uniqid(), 'label' => 'mx51 Console Terminal', 'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(),
        ]);
    }

    public function test_event_console_eftpos_pane_shows_both_linkly_and_mx51_transactions(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $linklyTerminal = $this->defaultEftTerminal();
        $sciTerminal = $this->sciTerminal();
        $eventId = $this->createEvent();

        LinklyTransaction::create([
            'pos_txn_ref' => 'CONSOLELINK1', 'txn_type' => 'purchase', 'event_id' => $eventId,
            'eft_terminal_id' => $linklyTerminal->id, 'status' => 'approved', 'amount' => 12,
        ]);
        SciTransaction::create([
            'client_ref' => 'console-sci-1', 'sci_transaction_id' => 'txn_console_1', 'event_id' => $eventId,
            'eft_terminal_id' => $sciTerminal->id, 'amount' => 34, 'status' => 'FINALISED', 'result_financial_status' => 'APPROVED',
        ]);

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertSee('CONSOLELINK1');
        $response->assertSee('txn_console_1');
        $response->assertSee('mx51 Console Terminal');
    }

    // The old per-terminal Pair/Check Status forms and the "Add Terminal" form are gone —
    // replaced by a single link to the real settings page (see admin.eft-terminals.index).
    public function test_event_console_eftpos_pane_links_out_instead_of_duplicating_pairing_ui(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
        $eventId = $this->createEvent();

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertSee('openEftTerminalSettingsModal()', false);
        $response->assertDontSee('name="pair_code"', false);
        // "name=\"key\"" is the Add-Terminal form's own field — distinct from the store()
        // route's URL, which is identical to index()'s (same path, different verb) and so
        // can't be used to tell "a link to the settings page" apart from "a duplicated form".
        $response->assertDontSee('name="key"', false);
    }

    public function test_ticket_console_eftpos_pane_shows_both_linkly_and_mx51_transactions(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $linklyTerminal = $this->defaultEftTerminal();
        $sciTerminal = $this->sciTerminal();

        LinklyTransaction::create([
            'pos_txn_ref' => 'CONSOLETIX1', 'txn_type' => 'purchase', 'donation_type' => 'ticket_order',
            'eft_terminal_id' => $linklyTerminal->id, 'status' => 'approved', 'amount' => 20,
        ]);
        SciTransaction::create([
            'client_ref' => 'console-sci-tix-1', 'sci_transaction_id' => 'txn_console_tix_1', 'donation_type' => 'ticket_order',
            'eft_terminal_id' => $sciTerminal->id, 'amount' => 40, 'status' => 'FINALISED', 'result_financial_status' => 'APPROVED',
        ]);

        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('CONSOLETIX1');
        $response->assertSee('txn_console_tix_1');
        $response->assertSee('mx51 Console Terminal');
    }

    public function test_ticket_console_eftpos_pane_links_out_instead_of_duplicating_pairing_ui(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();

        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('openEftTerminalSettingsModal()', false);
        $response->assertDontSee('name="pair_code"', false);
        $response->assertDontSee('name="key"', false);
    }

    // Both consoles' terminal-picker modals (on the actual purchase screens, not the consoles)
    // must offer a way to reach pairing without leaving the POS flow entirely.
    public function test_event_pos_donation_page_links_to_eft_terminal_settings(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $eventId = $this->createEvent();

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/pos");

        $response->assertOk();
        $response->assertSee('openEftTerminalSettingsModal()', false);
    }

    public function test_ticket_pos_page_links_to_eft_terminal_settings(): void
    {
        Setting::set('linkly_mode', 'sandbox');

        $response = $this->actingAs($this->adminUser())->get('/admin/tickets/pos');

        $response->assertOk();
        $response->assertSee('openEftTerminalSettingsModal()', false);
    }
}
