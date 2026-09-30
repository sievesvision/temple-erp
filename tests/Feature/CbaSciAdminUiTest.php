<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The admin-facing side of Phase 1: adding a CBA Smart Terminal to the registry and
 * rendering its pairing block on the EFT Terminal Settings page.
 */
class CbaSciAdminUiTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_admin_can_add_a_cba_smart_terminal(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.eft-terminals.store'), [
            'key' => 'sci-counter-1',
            'label' => 'SCI Counter 1',
            'provider' => 'cba_sci',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('eft_terminals', ['key' => 'sci-counter-1', 'provider' => 'cba_sci']);
    }

    public function test_terminals_added_without_a_provider_default_to_linkly(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.eft-terminals.store'), [
            'key' => 'linkly-counter-1',
            'label' => 'Linkly Counter 1',
        ]);

        $this->assertDatabaseHas('eft_terminals', ['key' => 'linkly-counter-1', 'provider' => 'linkly']);
    }

    public function test_settings_page_shows_sci_pairing_block_for_a_cba_terminal_and_linkly_form_for_a_linkly_one(): void
    {
        $admin = $this->adminUser();
        EftTerminal::create(['key' => 'sci-1', 'label' => 'SCI One', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid()]);
        EftTerminal::create(['key' => 'linkly-1', 'label' => 'Linkly One', 'provider' => 'linkly', 'pos_id' => \Illuminate\Support\Str::uuid()]);

        $response = $this->actingAs($admin)->get(route('admin.eft-terminals.index'));

        $response->assertOk();
        $response->assertSee('name="pairing_code"', false);
        $response->assertSee('Pairing Code', false);
        $response->assertSee('name="pair_code"', false);
    }

    public function test_settings_page_shows_paired_state_for_a_paired_cba_terminal(): void
    {
        $admin = $this->adminUser();
        EftTerminal::create([
            'key' => 'sci-paired', 'label' => 'SCI Paired', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid(),
            'sci_pairing_id' => 'pid_abc', 'sci_key_id' => 'kid_abc', 'sci_signing_secret_part_b' => 'secret',
            'sci_api_base_url' => 'https://sci-api.tenant.example', 'sci_pairing_nickname' => 'Front Bar',
            'sci_confirmation_code' => '2022',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.eft-terminals.index'));

        $response->assertOk();
        $response->assertSee('Paired successfully', false);
        $response->assertSee('Front Bar', false);
        $response->assertSee('pid_abc', false);
        // The confirmation code mx51 returns during pairing is shown back to the operator so
        // they can cross-check it against what the terminal itself displays.
        $response->assertSee('Confirmation Code', false);
        $response->assertSee('2022', false);
        $response->assertDontSee('secret</strong>', false);
    }

    public function test_pair_endpoint_requires_registry_access(): void
    {
        $devotee = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::create(['key' => 'sci-x', 'label' => 'SCI X', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid()]);

        $response = $this->actingAs($devotee)->post(route('admin.cba-sci.pair'), [
            'terminal_id' => $terminal->id,
            'pairing_code' => '123456',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $terminal->refresh();
        $this->assertFalse($terminal->isSciPaired());
    }

    public function test_pairing_via_the_http_endpoint_end_to_end(): void
    {
        config(['services.cba_sci.test_pairing_api_key' => 'test-pairing-key']);
        $admin = $this->adminUser();
        $terminal = EftTerminal::create(['key' => 'sci-e2e', 'label' => 'SCI E2E', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid()]);

        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_e2e', 'key_id' => 'kid_e2e', 'signing_secret_part_b' => 'secret-e2e',
                'sci_api_base_url' => 'https://sci-api.tenant.example',
            ]], 200),
        ]);

        $response = $this->actingAs($admin)->from(route('admin.eft-terminals.index'))->post(route('admin.cba-sci.pair'), [
            'terminal_id' => $terminal->id,
            'pairing_code' => '654321',
            'pairing_nickname' => 'Kiosk 2',
        ]);

        // Every pairing action redirects back to wherever the form was actually submitted
        // from (plain redirect()->back()) rather than a fixed route — see CbaSciController's
        // own class docblock — since this pairing form is now @include'd verbatim on the
        // standalone settings page, both consoles, and Admin Settings.
        $response->assertRedirect(route('admin.eft-terminals.index'));
        $response->assertSessionHas('success');
        $terminal->refresh();
        $this->assertTrue($terminal->isSciPaired());
        $this->assertSame('Kiosk 2', $terminal->sci_pairing_nickname);
    }

    // SCIPAIRING07 — cancelling a pairing attempt must call the Unpair endpoint too, even
    // though for a terminal that was never paired this is a harmless no-op. It should read
    // as "cancelled" rather than the (misleading, since nothing was ever paired) "unpaired"
    // wording, and shouldn't clutter the audit log for what's essentially a no-op.
    public function test_cancelling_an_unpaired_terminals_pairing_attempt_calls_unpair_harmlessly(): void
    {
        $admin = $this->adminUser();
        $terminal = EftTerminal::create(['key' => 'sci-cancel', 'label' => 'SCI Cancel', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid()]);

        $response = $this->actingAs($admin)->from(route('admin.eft-terminals.index'))->post(route('admin.cba-sci.unpair'), ['terminal_id' => $terminal->id]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $response->assertSessionHas('success', 'Pairing cancelled.');
        $this->assertDatabaseMissing('audit_logs', ['action' => "Unpaired mx51 Cloud terminal 'SCI Cancel' (sci-cancel)"]);
    }

    public function test_unpairing_a_genuinely_paired_terminal_still_shows_the_normal_message_and_logs_it(): void
    {
        $admin = $this->adminUser();
        $terminal = EftTerminal::create([
            'key' => 'sci-real-unpair', 'label' => 'SCI Real Unpair', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid(),
            'sci_pairing_id' => 'pid_x', 'sci_key_id' => 'kid_x', 'sci_signing_secret_part_b' => 'secret',
            'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(null, 204)]);

        $response = $this->actingAs($admin)->post(route('admin.cba-sci.unpair'), ['terminal_id' => $terminal->id]);

        $response->assertSessionHas('success', 'Terminal unpaired.');
        $this->assertDatabaseHas('audit_logs', ['action' => "Unpaired mx51 Cloud terminal 'SCI Real Unpair' (sci-real-unpair)"]);
    }

    // SCIPAIRING10/SCIMULTI03 — viewing the pairing section must itself confirm any locally-
    // paired mx51 terminal is still actually paired on mx51's side.
    public function test_viewing_the_settings_page_self_heals_an_externally_unpaired_terminal(): void
    {
        $admin = $this->adminUser();
        $terminal = EftTerminal::create([
            'key' => 'sci-stale', 'label' => 'SCI Stale', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid(),
            'sci_pairing_id' => 'pid_stale', 'sci_key_id' => 'kid_stale', 'sci_signing_secret_part_b' => 'secret',
            'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $response = $this->actingAs($admin)->get(route('admin.eft-terminals.index'));

        $response->assertOk();
        $response->assertSee('Not Paired', false);
        $this->assertFalse($terminal->fresh()->isSciPaired());
    }

    public function test_removing_a_terminal_with_recorded_sci_transactions_is_blocked(): void
    {
        $admin = $this->adminUser();
        $terminal = EftTerminal::create(['key' => 'sci-hist', 'label' => 'SCI Hist', 'provider' => 'cba_sci', 'pos_id' => \Illuminate\Support\Str::uuid()]);
        \App\Models\SciTransaction::create([
            'client_ref' => 'ref-1', 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'FINALISED',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.eft-terminals.destroy', $terminal));

        $response->assertRedirect();
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id]);
    }
}
