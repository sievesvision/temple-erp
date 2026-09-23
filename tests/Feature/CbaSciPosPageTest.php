<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EftTerminal;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3 smoke test — the donation POS and ticket kiosk pages must still render (no Blade
 * errors from the new Action Framework markup/JS wiring) and must report a CBA Smart
 * Terminal as paired using its own SCI pairing state, not Linkly's secret_live/secret_sandbox
 * columns (see EftTerminal::isPairedFor()).
 */
class CbaSciPosPageTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedSciTerminal(): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-pos-' . uniqid(), 'label' => 'SCI POS Terminal', 'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(), 'sci_pairing_id' => 'pid_pos', 'sci_key_id' => 'kid_pos',
            'sci_signing_secret_part_b' => 'secret', 'sci_api_base_url' => 'https://sci-api.tenant.example',
        ]);
    }

    private function unpairedLinklyTerminal(): EftTerminal
    {
        return EftTerminal::create(['key' => 'linkly-pos-' . uniqid(), 'label' => 'Linkly POS Terminal', 'pos_id' => (string) Str::uuid()]);
    }

    public function test_donation_pos_page_renders_and_lists_a_paired_cba_terminal(): void
    {
        $admin = $this->adminUser();
        $event = Event::create([
            'event_name' => 'POS Smoke Event', 'slug' => 'pos-smoke-event-' . uniqid(),
            'event_date' => now()->addMonth()->toDateString(), 'status' => 'Upcoming',
        ]);
        $sci = $this->pairedSciTerminal();
        $this->unpairedLinklyTerminal();

        $response = $this->actingAs($admin)->get(route('admin.events.pos', $event->event_id));

        $response->assertOk();
        $response->assertSee('sci-action-framework.js', false);
        $response->assertSee('eftModalActionFramework', false);
        $response->assertSee('eftModalOverride', false);
        $response->assertSee('"provider":"cba_sci"', false);
        $response->assertSee('"id":' . $sci->id . ',"label":"SCI POS Terminal","provider":"cba_sci","is_default":false,"paired":true', false);
    }

    public function test_ticket_pos_page_renders_and_lists_a_paired_cba_terminal(): void
    {
        $admin = $this->adminUser();
        $sci = $this->pairedSciTerminal();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('sci-action-framework.js', false);
        $response->assertSee('"provider":"cba_sci"', false);
        $response->assertSee('"id":' . $sci->id . ',"label":"SCI POS Terminal","provider":"cba_sci","is_default":false,"paired":true', false);
    }
}
