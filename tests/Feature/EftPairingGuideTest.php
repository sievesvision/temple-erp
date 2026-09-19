<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

/**
 * The public Cloud Pairing Guide (accreditation requirement 1.4 — see the Linkly
 * accreditation spreadsheet's row 29) now also documents the multi-terminal registry added
 * later, and every EFTPOS-related settings surface links to it as a "Help" shortcut.
 */
class EftPairingGuideTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_guide_page_is_public_and_documents_multiple_terminals(): void
    {
        $response = $this->get('/eft-pairing-guide');

        $response->assertOk();
        // Original accredited steps 1-5 must stay intact.
        $response->assertSee('Generate a pairing code on the terminal');
        $response->assertSee('Confirm pairing succeeded');
        // New multi-terminal content.
        $response->assertSee('Running More Than One Terminal at Once');
        $response->assertSee('Register a new terminal');
        $response->assertSee('Choose which terminal each computer uses');
    }

    public function test_eft_terminal_settings_page_links_to_the_guide(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/eft-terminals');

        $response->assertOk();
        $response->assertSee(route('eft.pairing-guide'), false);
    }

    public function test_main_settings_page_links_to_the_guide(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/settings');

        $response->assertOk();
        $response->assertSee(route('eft.pairing-guide'), false);
    }

    public function test_ticket_console_links_to_the_guide(): void
    {
        EftTerminal::factory()->create(['key' => 'guide-check-terminal']);

        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee(route('eft.pairing-guide'), false);
    }
}
