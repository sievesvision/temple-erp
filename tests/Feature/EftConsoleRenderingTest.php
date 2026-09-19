<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A plain "does it render at all" smoke test for the pages touched by the multi-terminal
 * refactor (Settings, Event Console, Event POS, Ticket Console, Ticket Kiosk) — each of
 * these now lists every registered EftTerminal instead of a single global pairing, so this
 * exercises that with more than one terminal actually present, including one that's never
 * been paired at all.
 */
class EftConsoleRenderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
        EftTerminal::factory()->create(['key' => 'unpaired-terminal', 'label' => 'Unpaired Terminal']);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_settings_page_renders_with_multiple_terminals(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('EFT Terminals');
        $response->assertSee('Unpaired Terminal');
    }

    public function test_event_console_renders_with_multiple_terminals(): void
    {
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Render Check Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/console");

        $response->assertOk();
        $response->assertSee('Unpaired Terminal');
    }

    public function test_event_pos_renders_with_multiple_terminals(): void
    {
        $eventId = DB::table('events')->insertGetId([
            'event_name' => 'Render Check Event', 'event_date' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser())->get("/admin/events/{$eventId}/pos");

        $response->assertOk();
    }

    public function test_ticket_console_renders_with_multiple_terminals(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('Unpaired Terminal');
    }

    public function test_ticket_kiosk_renders_with_multiple_terminals(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/tickets/pos');

        $response->assertOk();
    }
}
