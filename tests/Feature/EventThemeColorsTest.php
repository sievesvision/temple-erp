<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Per-event public-page colour theme overrides (Event::themeColors()) — null (the default)
 * means inherit the temple's global theme; an event can override just one or two channels
 * and still inherit the rest. See resources/views/frontend/event-donate.blade.php.
 */
class EventThemeColorsTest extends TestCase
{
    private function makeEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'event_name' => 'Theme Test Event',
            'slug' => 'theme-test-event-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(),
            'status' => 'Upcoming',
        ], $overrides));
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_an_event_with_no_overrides_inherits_the_temples_global_theme(): void
    {
        Setting::set('theme_primary_color', '#111111');
        Setting::set('theme_accent_color', '#222222');
        Setting::set('theme_dark_color', '#333333');
        $event = $this->makeEvent();

        $colors = $event->themeColors(Setting::templeBranding());

        $this->assertSame('#111111', $colors['primary']);
        $this->assertSame('#222222', $colors['accent']);
        $this->assertSame('#333333', $colors['dark']);
        $this->assertSame('#fbf8f1', $colors['body']);
    }

    public function test_an_event_can_override_just_one_channel_and_inherit_the_rest(): void
    {
        Setting::set('theme_primary_color', '#111111');
        Setting::set('theme_accent_color', '#222222');
        Setting::set('theme_dark_color', '#333333');
        $event = $this->makeEvent(['theme_enabled' => true, 'theme_accent_color' => '#FFC700']);

        $colors = $event->themeColors(Setting::templeBranding());

        $this->assertSame('#111111', $colors['primary']);
        $this->assertSame('#FFC700', $colors['accent']);
        $this->assertSame('#333333', $colors['dark']);
    }

    // theme_enabled is a switch separate from whether the colour columns hold values —
    // turning it off must always show the temple's default theme even though the event's
    // own colours are still saved underneath, ready to switch back on.
    public function test_theme_enabled_off_shows_the_default_theme_even_with_colours_saved(): void
    {
        Setting::set('theme_primary_color', '#111111');
        Setting::set('theme_accent_color', '#222222');
        Setting::set('theme_dark_color', '#333333');
        $event = $this->makeEvent([
            'theme_enabled' => false,
            'theme_primary_color' => '#A66A00',
            'theme_accent_color' => '#FFC700',
        ]);

        $colors = $event->themeColors(Setting::templeBranding());

        $this->assertSame('#111111', $colors['primary']);
        $this->assertSame('#222222', $colors['accent']);
        $this->assertSame('#A66A00', $event->theme_primary_color, 'the saved colour must survive being switched off');
    }

    public function test_public_event_page_reflects_the_events_own_theme(): void
    {
        $event = $this->makeEvent([
            'theme_enabled' => true,
            'theme_primary_color' => '#A66A00',
            'theme_accent_color' => '#FFC700',
            'theme_dark_color' => '#4A2E0A',
            'theme_body_color' => '#FFDE59',
        ]);

        $response = $this->get(route('events.show', $event->slug));

        $response->assertOk();
        $response->assertSee('--primary: #A66A00', false);
        $response->assertSee('--accent: #FFC700', false);
        $response->assertSee('--dark: #4A2E0A', false);
        $response->assertSee('--cream: #FFDE59', false);
    }

    public function test_console_settings_form_updates_theme_colors(): void
    {
        $admin = $this->adminUser();
        $event = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.events.update', $event->event_id), [
            'event_name' => $event->event_name,
            'event_date' => $event->event_date,
            'start_time' => '09:00', 'end_time' => '17:00', 'location' => 'Temple',
            'status' => 'Upcoming',
            'theme_enabled' => '1',
            'theme_primary_color' => '#A66A00',
            'theme_accent_color' => '#FFC700',
            'theme_dark_color' => '#4A2E0A',
            'theme_body_color' => '#FFDE59',
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertTrue($event->theme_enabled);
        $this->assertSame('#A66A00', $event->theme_primary_color);
        $this->assertSame('#FFC700', $event->theme_accent_color);
    }

    // Submitting the form with the switch unchecked (its hidden companion field sends "0")
    // must turn theme_enabled off — this proves an unchecked checkbox is distinguished from
    // the older modal's form not knowing about theme fields at all (see the guard in
    // EventController::update(), keyed off theme_primary_color's presence).
    public function test_unchecking_the_switch_disables_the_theme_without_losing_colours(): void
    {
        $admin = $this->adminUser();
        $event = $this->makeEvent(['theme_enabled' => true, 'theme_primary_color' => '#A66A00', 'theme_accent_color' => '#FFC700']);

        $response = $this->actingAs($admin)->post(route('admin.events.update', $event->event_id), [
            'event_name' => $event->event_name,
            'event_date' => $event->event_date,
            'start_time' => '09:00', 'end_time' => '17:00', 'location' => 'Temple',
            'status' => 'Upcoming',
            'theme_enabled' => '0', // the hidden companion field's value when the checkbox is unchecked
            'theme_primary_color' => '#A66A00',
            'theme_accent_color' => '#FFC700',
            'theme_dark_color' => '',
            'theme_body_color' => '',
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertFalse($event->theme_enabled);
        $this->assertSame('#A66A00', $event->theme_primary_color, 'colours must survive being switched off, ready to re-enable');
        $this->assertSame(Setting::templeBranding()['primary_color'], $event->themeColors(Setting::templeBranding())['primary']);
    }

    // A save from the older Manage Events modal (which has no theme colour fields at all)
    // must never silently clear an event's already-configured theme — same "absent means
    // leave untouched" guard as the payment-method override.
    public function test_a_save_without_theme_fields_leaves_the_existing_theme_untouched(): void
    {
        $admin = $this->adminUser();
        $event = $this->makeEvent(['theme_primary_color' => '#A66A00', 'theme_accent_color' => '#FFC700']);

        $response = $this->actingAs($admin)->post(route('admin.events.update', $event->event_id), [
            'event_name' => $event->event_name,
            'event_date' => $event->event_date,
            'start_time' => '09:00', 'end_time' => '17:00', 'location' => 'Temple',
            'status' => 'Upcoming',
            // No theme_* fields at all — mirrors the older Manage Events modal's form.
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertSame('#A66A00', $event->theme_primary_color);
        $this->assertSame('#FFC700', $event->theme_accent_color);
    }

    public function test_an_invalid_hex_value_is_rejected(): void
    {
        $admin = $this->adminUser();
        $event = $this->makeEvent();

        $response = $this->actingAs($admin)->post(route('admin.events.update', $event->event_id), [
            'event_name' => $event->event_name,
            'event_date' => $event->event_date,
            'start_time' => '09:00', 'end_time' => '17:00', 'location' => 'Temple',
            'status' => 'Upcoming',
            'theme_primary_color' => 'not-a-colour',
        ]);

        $response->assertSessionHasErrors('theme_primary_color');
    }
}
