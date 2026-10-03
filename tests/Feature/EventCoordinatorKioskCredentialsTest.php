<?php

namespace Tests\Feature;

use App\Models\KioskPin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * An event-admin coordinator (or the system Admin) setting/resetting another coordinator's
 * kiosk username/PIN on their behalf — the self-service screen (KioskPinSettingsTest) needs
 * the ACCOUNT's own password, which an admin assisting someone who's forgotten theirs doesn't
 * have, so this path is gated on the ACTING admin's own password instead. See
 * EventCoordinatorController::overrideKioskCredentials().
 */
class EventCoordinatorKioskCredentialsTest extends TestCase
{
    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Kiosk Cred Event', 'event_date' => now()->addMonth()->toDateString(),
            'slug' => 'kiosk-cred-event-' . uniqid(),
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

    public function test_event_admin_can_set_a_pos_coordinators_username_and_pin(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $pos->id]), [
            'current_password' => 'password',
            'username' => 'counter1',
            'new_pin' => '123456', 'new_pin_confirmation' => '123456',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('counter1', $pos->fresh()->username);
        $pin = KioskPin::where('user_id', $pos->id)->where('destination_type', 'event')->where('destination_id', $eventId)->first();
        $this->assertNotNull($pin);
        $this->assertTrue(Hash::check('123456', $pin->pin));
    }

    public function test_entry_level_coordinator_can_also_receive_a_pin(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $entry = $this->coordinator($eventId, 'entry');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $entry->id]), [
            'current_password' => 'password',
            'new_pin' => '222333', 'new_pin_confirmation' => '222333',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, KioskPin::where('user_id', $entry->id)->count());
    }

    public function test_view_level_coordinator_is_rejected_since_it_has_no_kiosk_destination(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $view = $this->coordinator($eventId, 'view');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $view->id]), [
            'current_password' => 'password',
            'new_pin' => '444555', 'new_pin_confirmation' => '444555',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, KioskPin::where('user_id', $view->id)->count());
        $this->assertNull($view->fresh()->username);
    }

    public function test_wrong_acting_password_is_rejected_and_nothing_is_changed(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $pos->id]), [
            'current_password' => 'not-the-admins-password',
            'new_pin' => '123456', 'new_pin_confirmation' => '123456',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, KioskPin::where('user_id', $pos->id)->count());
    }

    public function test_a_pos_level_coordinator_cannot_use_this_endpoint_themselves(): void
    {
        $eventId = $this->makeEvent();
        $pos = $this->coordinator($eventId, 'pos');
        $otherPos = $this->coordinator($eventId, 'pos');

        $response = $this->actingAs($pos)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $otherPos->id]), [
            'current_password' => 'password',
            'new_pin' => '123456', 'new_pin_confirmation' => '123456',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, KioskPin::where('user_id', $otherPos->id)->count());
    }

    public function test_an_event_admin_cannot_override_another_event_admins_credentials(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $otherAdmin = $this->coordinator($eventId, 'admin');

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $otherAdmin->id]), [
            'current_password' => 'password',
            'new_pin' => '123456', 'new_pin_confirmation' => '123456',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, KioskPin::where('user_id', $otherAdmin->id)->count());
    }

    public function test_the_system_admin_can_override_an_event_admins_credentials(): void
    {
        $eventId = $this->makeEvent();
        $systemAdmin = User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventAdmin = $this->coordinator($eventId, 'admin');

        $response = $this->actingAs($systemAdmin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $eventAdmin->id]), [
            'current_password' => 'password',
            'new_pin' => '123456', 'new_pin_confirmation' => '123456',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, KioskPin::where('user_id', $eventAdmin->id)->count());
    }

    public function test_a_pin_colliding_with_the_targets_other_destination_is_rejected(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->coordinator($eventId, 'admin');
        $pos = $this->coordinator($eventId, 'pos');
        $otherEventId = $this->makeEvent();
        DB::table('event_coordinators')->insert([
            'user_id' => $pos->id, 'event_id' => $otherEventId, 'level' => 'pos',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        KioskPin::create(['user_id' => $pos->id, 'destination_type' => 'event', 'destination_id' => $otherEventId, 'pin' => Hash::make('999888'), 'pin_set_at' => now()]);

        $response = $this->actingAs($admin)->post(route('admin.events.coordinators.overrideKioskCredentials', [$eventId, $pos->id]), [
            'current_password' => 'password',
            'new_pin' => '999888', 'new_pin_confirmation' => '999888',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, KioskPin::where('user_id', $pos->id)->count());
    }
}
