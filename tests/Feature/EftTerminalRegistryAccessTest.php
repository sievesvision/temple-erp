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
 * Who can reach and use the standalone EFT Terminal Settings page (see
 * App\Services\EftTerminalAccess) — deliberately broader than the main Settings page's own
 * 'settings' RolePermission: an event-admin Event Coordinator or admin-level Ticket
 * Controller already has full pairing/refund/logon rights over any terminal from their own
 * console, so they can view the registry, add a new terminal, and set the global default too.
 * Only "remove a terminal" stays System-Admin-only, since that can take a terminal away from
 * another console entirely rather than just changing a preference.
 */
class EftTerminalRegistryAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
    }

    // 'Event Coordinator' is in the route's own role list (broad enough for entry-level
    // coordinators to reach their own console), but only 'admin' level should ever pass
    // EftTerminalAccess::canManageRegistry() — this is the controller doing the real
    // narrowing, not the route.
    private function entryLevelCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Access Test Event (entry)', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'entry',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function eventAdminCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Access Test Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function ticketAdminController(): User
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id, 'level' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    // Lands only on the counter-style Donation POS page (never the full console) — see
    // AuthController::login()'s level==='pos' branch and EventCoordinatorLevel's docblock.
    private function posLevelCoordinator(): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Access Test Event (pos)', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    // 'view' lands on the Ticket POS page exactly like 'entry' does — only 'admin' reaches the
    // full Ticket Console — see TicketControllerLevel's docblock.
    private function ticketViewController(): User
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id, 'level' => 'view', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    public function test_event_admin_coordinator_can_view_and_add_a_terminal(): void
    {
        $user = $this->eventAdminCoordinator();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertOk();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'event-admin-added', 'label' => 'Added by Event Admin',
        ]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'event-admin-added']);
    }

    public function test_ticket_admin_controller_can_view_and_add_a_terminal(): void
    {
        $user = $this->ticketAdminController();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertOk();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'ticket-admin-added', 'label' => 'Added by Ticket Admin',
        ]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'ticket-admin-added']);
    }

    public function test_entry_level_coordinator_cannot_view_or_add_a_terminal(): void
    {
        $user = $this->entryLevelCoordinator();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertStatus(403);

        $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'should-not-exist', 'label' => 'Should Not Exist',
        ]);
        $this->assertDatabaseMissing('eft_terminals', ['key' => 'should-not-exist']);
    }

    // A pos-level coordinator only ever reaches the counter-style Donation POS page, which shows
    // an "Open EFT Terminal Settings" link unconditionally — if their own station's terminal
    // ever goes unpaired, they need to be able to re-pair it without an Admin on hand, since
    // there's no other page they can even get to.
    public function test_pos_level_coordinator_can_view_and_add_a_terminal(): void
    {
        $user = $this->posLevelCoordinator();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertOk();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'pos-level-added', 'label' => 'Added by POS Level',
        ]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'pos-level-added']);
    }

    // view/entry Ticket Controllers both land on the Ticket POS page the same way (only
    // 'admin' reaches the full Ticket Console) — same reasoning as the pos-level coordinator
    // case above.
    public function test_ticket_view_controller_can_view_and_add_a_terminal(): void
    {
        $user = $this->ticketViewController();

        $this->actingAs($user)->get('/admin/eft-terminals')->assertOk();

        $response = $this->actingAs($user)->post('/admin/eft-terminals', [
            'key' => 'ticket-view-added', 'label' => 'Added by Ticket View',
        ]);

        $response->assertRedirect(route('admin.eft-terminals.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'ticket-view-added']);
    }

    // "Set default" and "remove" both share the page's own broader canManageRegistry() access
    // — anyone trusted to add/pair a terminal is trusted to change or remove any terminal in
    // the registry too, since it's still one shared, global list rather than scoped per
    // event/Tickets (see EftTerminalAccess's own docblock). Only the provider-wide
    // sandbox/live switch (updateMode()) stays System-Admin-only.
    public function test_event_admin_coordinator_can_set_default_and_remove_a_non_default_terminal(): void
    {
        $user = $this->eventAdminCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'protected-terminal']);

        $this->actingAs($user)->post("/admin/eft-terminals/{$terminal->id}/default")->assertRedirect();
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'is_default' => true]);

        $other = EftTerminal::factory()->create(['key' => 'removable-by-coordinator']);
        $this->actingAs($user)->delete("/admin/eft-terminals/{$other->id}")->assertRedirect();
        $this->assertSoftDeleted('eft_terminals', ['id' => $other->id]);
    }

    public function test_entry_level_coordinator_cannot_remove_a_terminal(): void
    {
        $user = $this->entryLevelCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'entry-cannot-remove']);

        $this->actingAs($user)->delete("/admin/eft-terminals/{$terminal->id}")
            ->assertSessionHas('error', 'Unauthorized access.');
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'deleted_at' => null]);
    }

    // The POS terminal picker fires this the moment an operator picks a terminal (see
    // event-pos-donation.blade.php/ticket-pos.blade.php's saveSelectedTerminalId()) — it only
    // ever writes the CALLING user's own row, so it shares canManageRegistry()'s broader
    // access rather than needing setDefault()'s System-Admin-only tier.
    public function test_selecting_a_terminal_remembers_it_as_that_users_own_default(): void
    {
        $user = $this->eventAdminCoordinator();
        $globalDefault = EftTerminal::default();
        $otherTerminal = EftTerminal::factory()->create(['key' => 'second-terminal']);

        $this->actingAs($user)
            ->post("/admin/eft-terminals/{$otherTerminal->id}/select-for-me")
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertSame($otherTerminal->id, $user->preferred_eft_terminal_id);

        // Resolving with no explicit id now gives THIS user the one they picked, not the
        // registry's own global default.
        $this->assertSame($otherTerminal->id, EftTerminal::default($user)->id);

        // A different user who never picked anything still gets the global default.
        $someoneElse = $this->eventAdminCoordinator();
        $this->assertSame($globalDefault->id, EftTerminal::default($someoneElse)->id);
    }

    public function test_entry_level_coordinator_cannot_select_a_personal_default_terminal(): void
    {
        $user = $this->entryLevelCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'no-access-terminal']);

        $this->actingAs($user)
            ->post("/admin/eft-terminals/{$terminal->id}/select-for-me")
            ->assertStatus(403);

        $this->assertNull($user->fresh()->preferred_eft_terminal_id);
    }

    // The picker's own fetch (above) is a fast path, not the only path — resolveOrDefault()
    // is the one place every EFT-starting controller action resolves "which terminal", so an
    // explicit choice made there is remembered too, even if a client ever skipped the picker's
    // own save.
    public function test_starting_a_charge_with_an_explicit_terminal_remembers_it_as_the_default(): void
    {
        $user = $this->eventAdminCoordinator();
        $otherTerminal = EftTerminal::factory()->create(['key' => 'picked-at-charge-time']);

        $resolved = EftTerminal::resolveOrDefault($otherTerminal->id, $user);

        $this->assertSame($otherTerminal->id, $resolved->id);
        $this->assertSame($otherTerminal->id, $user->fresh()->preferred_eft_terminal_id);
    }

    // Renaming a terminal's label was added after mx51's certification review flagged that
    // "mx51 Certification Terminal" had no way to be changed, and leaked into a customer-
    // facing error message that embedded it (see CbaSciController::startPurchase()'s fix).
    // Same permission tier as adding a terminal (canManageRegistry()) — an event-admin
    // coordinator already fully manages their own terminals' pairing, so fixing a typo'd
    // label is no more sensitive than that.
    public function test_event_admin_coordinator_can_rename_a_terminal(): void
    {
        $user = $this->eventAdminCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'renamable-terminal', 'label' => 'Old Label']);

        $this->actingAs($user)->post("/admin/eft-terminals/{$terminal->id}", ['label' => 'New Label'])
            ->assertRedirect();

        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'label' => 'New Label']);
    }

    public function test_entry_level_coordinator_cannot_rename_a_terminal(): void
    {
        $user = $this->entryLevelCoordinator();
        $terminal = EftTerminal::factory()->create(['key' => 'protected-label-terminal', 'label' => 'Original Label']);

        $this->actingAs($user)->post("/admin/eft-terminals/{$terminal->id}", ['label' => 'Hijacked Label']);

        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'label' => 'Original Label']);
    }

    // The "Add Terminal" form is now @include'd verbatim wherever the terminal registry
    // appears (the standalone page, both consoles' own EFT Terminal Settings pane, and Admin
    // Settings) — see admin.partials.eft-terminal-registry — so there's no fixed "home" route
    // to send the browser back to. store() redirects with plain redirect()->back() instead
    // (same as every sibling action here), landing wherever the form was actually submitted
    // from, whichever of those pages that happens to be.
    public function test_add_terminal_redirects_back_to_the_ticket_console_when_submitted_from_there(): void
    {
        $user = $this->ticketAdminController();

        $response = $this->actingAs($user)->from(route('admin.tickets.index'))->post('/admin/eft-terminals', [
            'key' => 'from-ticket-console', 'label' => 'From Ticket Console',
        ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'from-ticket-console']);
    }

    public function test_add_terminal_redirects_back_to_the_event_console_when_submitted_from_there(): void
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Return Context Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->from(route('admin.events.console', $eventId))->post('/admin/eft-terminals', [
            'key' => 'from-event-console', 'label' => 'From Event Console',
        ]);

        $response->assertRedirect(route('admin.events.console', $eventId));
        $this->assertDatabaseHas('eft_terminals', ['key' => 'from-event-console']);
    }

    // A terminal that isn't currently paired can't take a payment, so the settings page
    // groups it apart from usable terminals instead of mixing them into one flat list.
    public function test_settings_page_groups_terminals_by_active_and_inactive_pairing_state(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        // setUp() already paired the default "main" terminal via defaultEftTerminal().
        $unpaired = EftTerminal::factory()->create(['key' => 'unpaired-term', 'label' => 'Unpaired Term']);

        $response = $this->actingAs($admin)->get('/admin/eft-terminals');

        $response->assertOk();
        $response->assertSeeInOrder(['Active', 'Main Terminal', 'Inactive', 'Unpaired Term']);
    }

    // The Back button used to be a bare url()->previous(), which breaks for a role that can't
    // open Admin Settings (role.admin-gated) but can still reach this page directly — it must
    // resolve a real parent route instead, and the right one depends on who's looking.
    public function test_back_link_targets_admin_settings_for_a_system_admin(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->get('/admin/eft-terminals');

        $response->assertOk();
        $response->assertSee('href="' . route('admin.settings') . '"', false);
    }

    public function test_back_link_targets_the_dashboard_for_a_non_system_admin(): void
    {
        $user = $this->ticketAdminController();

        $response = $this->actingAs($user)->get('/admin/eft-terminals');

        $response->assertOk();
        $response->assertSee('href="' . route('admin.dashboard') . '"', false);
    }

    // Each provider's sandbox/live switch is independent — see EftTerminalController::updateMode().
    public function test_admin_can_switch_a_providers_mode(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($admin)->post(route('admin.eft-terminals.updateMode'), [
            'provider' => 'cba_sci', 'mode' => 'live',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('live', Setting::get('cba_sci_mode'));
        $this->assertSame('sandbox', Setting::get('linkly_mode'));
    }

    public function test_non_admin_cannot_switch_a_providers_mode(): void
    {
        $user = $this->ticketAdminController();

        $response = $this->actingAs($user)->post(route('admin.eft-terminals.updateMode'), [
            'provider' => 'linkly', 'mode' => 'live',
        ]);

        $response->assertSessionHas('error', 'Unauthorized access.');
        $this->assertSame('sandbox', Setting::get('linkly_mode'));
    }

    // Linkly has no cheap "is it reachable" metadata call the way mx51's pairing-info is —
    // the only real check is a Logon, which actually reaches the physical terminal, so this
    // is a real LinklyTransaction, same as the event console's own Logon button.
    public function test_checking_connection_records_a_successful_logon(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::default();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response(['response' => ['success' => true, 'responseText' => 'OK']], 200),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.eft-terminals.checkConnection', $terminal));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Logon successful.');
        $this->assertDatabaseHas('linkly_transactions', [
            'eft_terminal_id' => $terminal->id, 'txn_type' => 'logon', 'status' => 'approved',
        ]);
    }

    public function test_checking_connection_records_a_failed_logon(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::default();
        Http::fake([
            '*/tokens/cloudpos' => Http::response(['token' => 'fake-token', 'expirySeconds' => 300], 200),
            '*/sessions/*/transaction*' => Http::response(['response' => ['success' => false, 'responseText' => 'TERMINAL OFFLINE']], 200),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.eft-terminals.checkConnection', $terminal));

        $response->assertSessionHas('error', 'TERMINAL OFFLINE');
        $this->assertDatabaseHas('linkly_transactions', [
            'eft_terminal_id' => $terminal->id, 'txn_type' => 'logon', 'status' => 'failed',
        ]);
        $this->assertSame('offline', $terminal->fresh()->lastKnownStatus()['state']);
    }

    public function test_checking_connection_is_rejected_for_a_non_linkly_terminal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::factory()->create(['provider' => 'cba_sci']);
        Http::fake();

        $response = $this->actingAs($admin)->post(route('admin.eft-terminals.checkConnection', $terminal));

        $response->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_checking_connection_requires_registry_access(): void
    {
        $user = $this->entryLevelCoordinator();
        $terminal = EftTerminal::default();
        Http::fake();

        $response = $this->actingAs($user)->post(route('admin.eft-terminals.checkConnection', $terminal));

        $response->assertSessionHas('error', 'Unauthorized access.');
        Http::assertNothingSent();
    }

    public function test_admin_can_set_default_and_remove_a_non_default_terminal(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::factory()->create(['key' => 'removable-terminal']);

        $this->actingAs($admin)->post("/admin/eft-terminals/{$terminal->id}/default")
            ->assertRedirect();
        $this->assertDatabaseHas('eft_terminals', ['id' => $terminal->id, 'is_default' => true]);

        $other = EftTerminal::factory()->create(['key' => 'other-terminal']);
        $this->actingAs($admin)->delete("/admin/eft-terminals/{$other->id}")->assertRedirect();
        // Soft-deleted (see EftTerminal's SoftDeletes trait) — not literally gone from the
        // table, just excluded from the registry/pickers by Eloquent's own global scope.
        $this->assertSoftDeleted('eft_terminals', ['id' => $other->id]);
    }

    // The original complaint: a terminal that had ever recorded a transaction could never be
    // removed at all. Soft-deleting instead of refusing outright keeps every transaction's
    // eft_terminal_id pointing at an intact row — removed from the active registry, but still
    // fully resolvable for history (see LinklyTransaction::eftTerminal()/SciTransaction::
    // eftTerminal()'s withTrashed()).
    public function test_admin_can_remove_an_inactive_terminal_that_has_recorded_transactions(): void
    {
        $admin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $terminal = EftTerminal::factory()->create(['key' => 'terminal-with-history', 'label' => 'Front Counter']);
        $transaction = SciTransaction::create([
            'client_ref' => 'history-ref-1', 'sci_transaction_id' => 'txn_history', 'sci_version' => 1,
            'eft_terminal_id' => $terminal->id, 'amount' => 50, 'status' => 'FINALISED',
        ]);

        $response = $this->actingAs($admin)->delete("/admin/eft-terminals/{$terminal->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('eft_terminals', ['id' => $terminal->id]);

        // The whole point: the transaction's own terminal reference must still resolve,
        // label and all, even though the terminal no longer appears in the live registry.
        $this->assertNull(EftTerminal::find($terminal->id));
        $this->assertSame($terminal->id, $transaction->fresh()->eftTerminal->id);
        $this->assertSame('Front Counter', $transaction->fresh()->eftTerminal->label);
    }
}
