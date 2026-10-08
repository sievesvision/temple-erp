<?php

namespace Tests\Feature;

use App\Models\KioskDevice;
use App\Models\User;
use App\Services\KioskDeviceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The scoped-ownership behavior specifically requested: an admin-tier Ticket Controller
 * configures ticket-module kiosks, an admin-tier Event Coordinator configures a donation
 * kiosk for their OWN event only — not a flat Admin-only gate, but also never a non-admin-
 * tier "normal" staff user, and never across module/event boundaries. See
 * App\Services\KioskAccess::canConfigure().
 */
class KioskDeviceConfigurationAccessTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function ticketAdminController(): User
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id, 'level' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function ticketViewController(): User
    {
        $user = User::factory()->create(['role' => 'Ticket Controller', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('ticket_controllers')->insert([
            'user_id' => $user->id, 'level' => 'view', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function eventAdminCoordinator(?int $eventId = null): array
    {
        $eventId = $eventId ?? $this->makeEvent('Event A');
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $eventId];
    }

    private function entryLevelEventCoordinator(int $eventId): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => 'entry',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function makeEvent(string $name): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => $name, 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ticketKiosk(): KioskDevice
    {
        return KioskDeviceService::register('Ticket Kiosk', null, 'tickets', null, null, null, $this->admin());
    }

    private function donationKiosk(int $eventId): KioskDevice
    {
        return KioskDeviceService::register('Donation Kiosk', null, 'donations', $eventId, null, null, $this->admin());
    }

    private function unassignedKiosk(): KioskDevice
    {
        return KioskDeviceService::register('Unassigned Kiosk', null, null, null, null, null, $this->admin());
    }

    public function test_admin_tier_ticket_controller_can_configure_a_tickets_kiosk(): void
    {
        $user = $this->ticketAdminController();
        $kiosk = $this->ticketKiosk();

        $this->actingAs($user)
            ->post("/admin/kiosk-devices/{$kiosk->id}/configuration", ['module' => 'tickets'])
            ->assertRedirect()->assertSessionHas('success');
    }

    public function test_admin_tier_ticket_controller_cannot_configure_a_donations_kiosk_even_for_an_event_they_coordinate(): void
    {
        [$coordinator, $eventId] = $this->eventAdminCoordinator();
        $user = $this->ticketAdminController();
        $kiosk = $this->donationKiosk($eventId);

        $this->actingAs($user)
            ->post("/admin/kiosk-devices/{$kiosk->id}/configuration", ['module' => 'donations', 'event_id' => $eventId])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_admin_tier_event_coordinator_can_configure_their_own_events_donation_kiosk(): void
    {
        [$user, $eventId] = $this->eventAdminCoordinator();
        $kiosk = $this->donationKiosk($eventId);

        $this->actingAs($user)
            ->post("/admin/kiosk-devices/{$kiosk->id}/configuration", ['module' => 'donations', 'event_id' => $eventId])
            ->assertRedirect()->assertSessionHas('success');
    }

    // The adapted "cross-branch" scenario — cross-event, since there are no branches.
    public function test_event_admin_coordinator_cannot_configure_a_donation_kiosk_for_an_event_they_dont_coordinate(): void
    {
        [$coordinatorA, $eventA] = $this->eventAdminCoordinator();
        $eventB = $this->makeEvent('Event B');
        $kioskForB = $this->donationKiosk($eventB);

        $this->actingAs($coordinatorA)
            ->post("/admin/kiosk-devices/{$kioskForB->id}/configuration", ['module' => 'donations', 'event_id' => $eventB])
            ->assertRedirect()->assertSessionHas('error');
    }

    // Split into one request per test method, matching this suite's own convention
    // (EftTerminalRegistryAccessTest etc. never mix more than one actingAs() per test) —
    // RoleSwitchMiddleware only initializes session('active_role') when unset, so reusing
    // one test's session across multiple different actingAs() calls lets an earlier
    // request's active_role leak into a later one for a different user entirely.
    public function test_unassigned_kiosk_cannot_be_configured_by_an_admin_tier_ticket_controller(): void
    {
        $kiosk = $this->unassignedKiosk();
        $ticketAdmin = $this->ticketAdminController();

        $this->actingAs($ticketAdmin)
            ->post("/admin/kiosk-devices/{$kiosk->id}/configuration", ['module' => 'tickets'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_unassigned_kiosk_cannot_be_configured_by_an_admin_tier_event_coordinator(): void
    {
        $kiosk = $this->unassignedKiosk();
        [$eventAdmin] = $this->eventAdminCoordinator();

        $this->actingAs($eventAdmin)
            ->post("/admin/kiosk-devices/{$kiosk->id}/configuration", ['module' => 'tickets'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_unassigned_kiosk_can_be_configured_by_true_admin(): void
    {
        $kiosk = $this->unassignedKiosk();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/kiosk-devices/{$kiosk->id}/configuration", ['module' => 'tickets'])
            ->assertRedirect()->assertSessionHas('success');
    }

    public function test_view_level_ticket_controller_never_gets_configuration_access(): void
    {
        $viewController = $this->ticketViewController();
        $ticketKiosk = $this->ticketKiosk();

        $this->actingAs($viewController)
            ->post("/admin/kiosk-devices/{$ticketKiosk->id}/configuration", ['module' => 'tickets'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_entry_level_event_coordinator_never_gets_configuration_access(): void
    {
        $eventId = $this->makeEvent('Event C');
        $entryCoordinator = $this->entryLevelEventCoordinator($eventId);
        $donationKiosk = $this->donationKiosk($eventId);

        $this->actingAs($entryCoordinator)
            ->post("/admin/kiosk-devices/{$donationKiosk->id}/configuration", ['module' => 'donations', 'event_id' => $eventId])
            ->assertRedirect()->assertSessionHas('error');
    }
}
