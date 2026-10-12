<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An event-admin coordinator (or the system Admin) setting another coordinator's POS
 * username, and/or triggering a PIN setup/reset link on their behalf. The self-service
 * screen (PosPinSettingsTest) needs the ACCOUNT's own password, which an admin assisting
 * someone who's forgotten theirs doesn't have, so this path is gated on the ACTING admin's
 * own password instead — but unlike the old design, the PIN itself is never submitted here
 * at all: "Never allow Event Admins to view or directly change existing users' PINs" is
 * enforced structurally by routing every change through
 * AccountSetupService::sendPosPinSetupLink(), which only the account holder, opening that
 * one-time link, can act on. See EventCoordinatorController::overridePosCredentials().
 */
class EventCoordinatorPosCredentialsTest extends TestCase
{
    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'POS Cred Event', 'event_date' => now()->addMonth()->toDateString(),
            'slug' => 'pos-cred-event-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function coordinator(int $eventId, string $level): User
    {
        $user = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert([
            'user_id' => $user->id, 'event_id' => $eventId, 'level' => $level,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    public function test_event_admin_can_set_a_pos_coordinators_username_and_trigger_a_pin_link(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $pos->id]), [
            'current_password' => 'password',
            'username' => 'counter1',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('counter1', $pos->fresh()->username);
        $this->assertSame(1, DB::table('pos_pin_setup_tokens')->where('user_id', $pos->id)->count());
        // The PIN itself is never touched directly — still unset until the account holder
        // opens the link.
        $this->assertNull($pos->fresh()->pos_pin);
    }

    /**
     * Security regression: even if a `new_pin` field is included in the request (whether by
     * an old client, a stale bookmark, or a deliberate attempt), it must have zero effect —
     * the controller no longer reads that field at all.
     */
    public function test_a_submitted_new_pin_field_is_silently_ignored(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');

        $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $pos->id]), [
            'current_password' => 'password',
            'new_pin' => '123456', 'new_pin_confirmation' => '123456',
        ]);

        $this->assertNull($pos->fresh()->pos_pin);
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $pos->id)->count());
    }

    public function test_username_can_be_set_without_triggering_a_pin_link(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $pos->id]), [
            'current_password' => 'password',
            'username' => 'counter2',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('counter2', $pos->fresh()->username);
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $pos->id)->count());
    }

    public function test_entry_level_coordinator_can_also_receive_a_pin_link(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $entry = $this->coordinator($eventId, 'entry');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $entry->id]), [
            'current_password' => 'password',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('pos_pin_setup_tokens')->where('user_id', $entry->id)->count());
    }

    public function test_view_level_coordinator_is_rejected_since_it_has_no_pos_destination(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $view = $this->coordinator($eventId, 'view');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $view->id]), [
            'current_password' => 'password',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $view->id)->count());
        $this->assertNull($view->fresh()->username);
    }

    public function test_wrong_acting_password_is_rejected_and_nothing_is_changed(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $pos->id]), [
            'current_password' => 'not-the-admins-password',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $pos->id)->count());
    }

    public function test_a_pos_level_coordinator_cannot_use_this_endpoint_themselves(): void
    {
        $eventId = $this->makeEvent();
        $pos = $this->coordinator($eventId, 'pos');
        $otherPos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($pos)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $otherPos->id]), [
            'current_password' => 'password',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $otherPos->id)->count());
    }

    public function test_an_event_admin_cannot_override_another_event_admins_credentials(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $otherAdmin = $this->coordinator($eventId, 'admin');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $otherAdmin->id]), [
            'current_password' => 'password',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('pos_pin_setup_tokens')->where('user_id', $otherAdmin->id)->count());
    }

    public function test_the_system_admin_can_override_an_event_admins_credentials(): void
    {
        $eventId = $this->makeEvent();
        $systemAdmin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventAdmin = $this->coordinator($eventId, 'admin');

        $response = $this->actingAs($systemAdmin)->post(route('admin.events.coordinators.overridePosCredentials', [$eventId, $eventAdmin->id]), [
            'current_password' => 'password',
            'send_pin_link' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('pos_pin_setup_tokens')->where('user_id', $eventAdmin->id)->count());
    }
}
