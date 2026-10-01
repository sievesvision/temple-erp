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
 * at all. The separate EFTPOS pane is gone entirely now — every transaction (Linkly + mx51
 * combined) lives on the All Transactions tab, and pairing/adding a terminal is the exact
 * same admin.partials.eft-terminal-registry partial the standalone settings page and Admin
 * Settings also @include, embedded directly (no iframe, no second copy of the form).
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

    // The separate "EFTPOS" pane (pairing status only) is gone — its own "EFT Terminal
    // Settings" pane now embeds the real pairing/add-terminal UI directly (no iframe), and
    // there's exactly one such pane, not a second copy of the form.
    public function test_event_console_eft_settings_pane_embeds_the_real_registry_directly(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
        $eventId = $this->createEvent();

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertSee('data-pane="pane-eft-settings"', false);
        $response->assertDontSee('data-pane="pane-eftpos"', false);
        $response->assertDontSee('eftSettingsPaneFrame', false);
        $response->assertSee('name="pair_code"', false);
        // The old always-visible "key"-named add-terminal form was replaced by the one-step
        // Add Terminal wizard (see eft-terminal-add-wizard.blade.php), which posts its fields
        // via fetch() rather than named form inputs — assert the wizard's own entry point and
        // field instead.
        $response->assertSee('id="addTerminalToggleBtn"', false);
        $response->assertSee('id="wizardTerminalKey"', false);
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

    public function test_ticket_console_eft_settings_pane_embeds_the_real_registry_directly(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();

        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('data-pane="pane-eft-settings"', false);
        $response->assertDontSee('data-pane="pane-eftpos"', false);
        $response->assertDontSee('eftSettingsPaneFrame', false);
        $response->assertSee('name="pair_code"', false);
        $response->assertSee('id="addTerminalToggleBtn"', false);
        $response->assertSee('id="wizardTerminalKey"', false);
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
