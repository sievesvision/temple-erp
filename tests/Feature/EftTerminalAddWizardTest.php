<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\SciTransaction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The one-step "Add Terminal" wizard (EftTerminalController::addAndPair/cancelNewTerminal) —
 * combines terminal creation with an immediate pairing attempt for both providers, mirroring
 * mx51's own merchant-portal UX. See resources/views/admin/partials/eft-terminal-add-wizard.
 * blade.php and public/js/eft-terminal-registry.js for the client side.
 */
class EftTerminalAddWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cba_sci.test_pairing_api_key' => 'test-pairing-key', 'services.cba_sci.test_signing_secret_part_a' => 'part-a-secret']);
        Setting::set('linkly_mode', 'sandbox');
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function entryLevelCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Wizard Access Test Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'entry',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    public function test_add_and_pair_creates_and_pairs_an_mx51_terminal_requiring_confirmation(): void
    {
        $admin = $this->adminUser();
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_wiz', 'key_id' => 'kid_wiz', 'confirmation_code' => '5927',
                'signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
                'tid' => 'tid_wiz', 'pairing_nickname' => 'Front Counter',
            ]], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '123456', 'pairing_nickname' => 'Front Counter',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'requires_confirmation' => true, 'confirmation_code' => '5927', 'tid' => 'tid_wiz']);

        // No admin-typed "Unique Terminal Code" any more — mx51's own TID becomes the key.
        $terminal = EftTerminal::find($response->json('terminal_id'));
        $this->assertNotNull($terminal);
        $this->assertSame('cba_sci', $terminal->provider);
        $this->assertSame('Front Counter', $terminal->label);
        $this->assertSame('sci-tid_wiz', $terminal->key);
        $this->assertTrue($terminal->isSciPaired());
    }

    public function test_add_and_pair_creates_and_pairs_a_linkly_terminal_with_no_confirmation_step(): void
    {
        $admin = $this->adminUser();
        Http::fake([
            'auth.sandbox.cloud.pceftpos.com/*' => Http::response(['secret' => 'linkly-secret-abc'], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'linkly', 'pairing_code' => '654321',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'requires_confirmation' => false]);

        // Linkly has no server-verified device id at pairing time, so its key stays an
        // internal, auto-generated value — never shown to or typed by an admin.
        $terminal = EftTerminal::find($response->json('terminal_id'));
        $this->assertNotNull($terminal);
        $this->assertSame('linkly', $terminal->provider);
        // No pairing_nickname given either — falls back to a generic label.
        $this->assertSame('New Terminal', $terminal->label);
        $this->assertTrue($terminal->isPaired('sandbox'));
    }

    public function test_add_and_pair_rolls_back_the_terminal_when_pairing_fails(): void
    {
        $admin = $this->adminUser();
        $countBefore = EftTerminal::count();
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['error' => ['code' => 'pairing_not_found']], 404),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '000000',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
        $this->assertSame($countBefore, EftTerminal::count());
    }

    // The same "roll back the just-created row" path as a genuine pairing failure above no
    // longer applies here — mx51's TID is the terminal's real identity now, so a TID that
    // already belongs to another row means this IS that same physical device, re-registered.
    // The old row is retired (soft-deleted, history intact) rather than refused.
    public function test_add_and_pair_replaces_a_terminal_whose_tid_is_already_registered(): void
    {
        $admin = $this->adminUser();
        $existing = EftTerminal::create([
            'key' => 'sci-tid_dupe', 'label' => 'Main Counter', 'provider' => 'cba_sci',
            'pos_id' => 'pos-existing', 'sci_tid' => 'tid_dupe',
        ]);
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['data' => [
                'pairing_id' => 'pid_dupe', 'key_id' => 'kid_dupe', 'confirmation_code' => '4242',
                'signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
                'tid' => 'tid_dupe',
            ]], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '999999',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $newTerminal = EftTerminal::find($response->json('terminal_id'));
        $this->assertNotNull($newTerminal);
        $this->assertSame('tid_dupe', $newTerminal->sci_tid);
        $this->assertSame('sci-tid_dupe', $newTerminal->key);

        // The old row is retired, not deleted outright — soft-deleted and renamed so it never
        // collides with the key the new row just claimed.
        $this->assertNull(EftTerminal::find($existing->id));
        $existing->refresh();
        $this->assertNotNull($existing->deleted_at);
        $this->assertStringStartsWith('sci-tid_dupe-retired-', $existing->key);
    }

    public function test_add_and_pair_requires_registry_access(): void
    {
        $user = $this->entryLevelCoordinator();
        $countBefore = EftTerminal::count();
        Http::fake();

        $response = $this->actingAs($user)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '123456',
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
        $this->assertSame($countBefore, EftTerminal::count());
    }

    public function test_cancel_new_terminal_unpairs_and_deletes_an_mx51_terminal(): void
    {
        $admin = $this->adminUser();
        $terminal = EftTerminal::factory()->create([
            'key' => 'wizard-cancel-1', 'provider' => 'cba_sci',
            'sci_pairing_id' => 'pid_cancel', 'sci_key_id' => 'kid_cancel',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(null, 204)]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.cancelNew', $terminal));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        // forceDelete()'d, not soft-deleted — this row never had any recorded activity (the
        // guard above proves it), so there's no history to preserve, and the 'key' field needs
        // to be genuinely free again for a retry (see cancelNewTerminal()'s own comment).
        $this->assertDatabaseMissing('eft_terminals', ['id' => $terminal->id]);
    }

    public function test_cancel_new_terminal_refuses_a_terminal_with_recorded_transactions(): void
    {
        $admin = $this->adminUser();
        $terminal = EftTerminal::factory()->create([
            'key' => 'wizard-cancel-protected', 'provider' => 'cba_sci',
            'sci_pairing_id' => 'pid_protected', 'sci_key_id' => 'kid_protected',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
        SciTransaction::create([
            'client_ref' => 'wizard-ref-1', 'sci_transaction_id' => 'txn_wizard', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 20, 'status' => 'PENDING',
        ]);
        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.cancelNew', $terminal));

        $response->assertStatus(422);
        Http::assertNothingSent();
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id]);
    }

    public function test_cancel_new_terminal_requires_registry_access(): void
    {
        $user = $this->entryLevelCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'wizard-cancel-noauth']);

        $response = $this->actingAs($user)->postJson(route('admin.eft-terminals.cancelNew', $terminal));

        $response->assertStatus(403);
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id]);
    }
}
