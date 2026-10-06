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
            'provider' => 'cba_sci', 'pairing_code' => '123456',
            'key' => 'wizard-sci-1', 'pairing_nickname' => 'Front Counter',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'requires_confirmation' => true, 'confirmation_code' => '5927']);

        $terminal = EftTerminal::where('key', 'wizard-sci-1')->first();
        $this->assertNotNull($terminal);
        $this->assertSame('cba_sci', $terminal->provider);
        $this->assertSame('Front Counter', $terminal->label);
        $this->assertTrue($terminal->isSciPaired());
    }

    public function test_add_and_pair_creates_and_pairs_a_linkly_terminal_with_no_confirmation_step(): void
    {
        $admin = $this->adminUser();
        Http::fake([
            'auth.sandbox.cloud.pceftpos.com/*' => Http::response(['secret' => 'linkly-secret-abc'], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'linkly', 'pairing_code' => '654321', 'key' => 'wizard-linkly-1',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'requires_confirmation' => false]);

        $terminal = EftTerminal::where('key', 'wizard-linkly-1')->first();
        $this->assertNotNull($terminal);
        $this->assertSame('linkly', $terminal->provider);
        // No pairing_nickname given — falls back to the unique terminal code as the label.
        $this->assertSame('wizard-linkly-1', $terminal->label);
        $this->assertTrue($terminal->isPaired('sandbox'));
    }

    public function test_add_and_pair_rolls_back_the_terminal_when_pairing_fails(): void
    {
        $admin = $this->adminUser();
        Http::fake([
            'sci-pairing-api.integrations.mx51.io/*' => Http::response(['error' => ['code' => 'pairing_not_found']], 404),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '000000', 'key' => 'wizard-fail-1',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
        $this->assertDatabaseMissing('eft_terminals', ['key' => 'wizard-fail-1']);
    }

    // The same "roll back the just-created row" path as a genuine pairing failure above, but
    // for a different reason: mx51's TID here belongs to an ALREADY-registered terminal, so
    // this attempt is the same physical device being added a second time, not a new one.
    public function test_add_and_pair_refuses_a_terminal_whose_tid_is_already_registered(): void
    {
        $admin = $this->adminUser();
        $existing = EftTerminal::create([
            'key' => 'already-registered', 'label' => 'Main Counter', 'provider' => 'cba_sci',
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
            'provider' => 'cba_sci', 'pairing_code' => '999999', 'key' => 'wizard-duplicate-tid',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => false]);
        $response->assertJsonFragment(['message' => 'This terminal is already registered as "Main Counter" — use that one instead of adding a new entry.']);
        // The just-created duplicate row is gone entirely (forceDelete() — nothing ever
        // succeeded on it); the original registration is completely untouched.
        $this->assertDatabaseMissing('eft_terminals', ['key' => 'wizard-duplicate-tid']);
        $this->assertDatabaseHas('eft_terminals', ['id' => $existing->id, 'sci_tid' => 'tid_dupe']);
    }

    public function test_add_and_pair_rejects_a_duplicate_unique_terminal_code_without_calling_the_pairing_api(): void
    {
        $admin = $this->adminUser();
        EftTerminal::factory()->create(['key' => 'already-taken']);
        Http::fake();

        $response = $this->actingAs($admin)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '123456', 'key' => 'already-taken',
        ]);

        $response->assertStatus(422);
        Http::assertNothingSent();
        $this->assertSame(1, EftTerminal::where('key', 'already-taken')->count());
    }

    public function test_add_and_pair_requires_registry_access(): void
    {
        $user = $this->entryLevelCoordinator();
        Http::fake();

        $response = $this->actingAs($user)->postJson(route('admin.eft-terminals.addAndPair'), [
            'provider' => 'cba_sci', 'pairing_code' => '123456', 'key' => 'should-not-exist',
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('eft_terminals', ['key' => 'should-not-exist']);
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
