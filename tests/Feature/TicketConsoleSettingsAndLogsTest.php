<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use Tests\TestCase;

/**
 * The Ticket Console's Settings (kiosk payment-method override) and Logs panes — added for
 * parity with the Event Console, which already has both. See TicketController::
 * updateSettings()/ticketPaymentMethodsOverride() and manageTickets()'s $ticketLogs query.
 */
class TicketConsoleSettingsAndLogsTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_ticket_console_shows_settings_and_logs_panes(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('pane-settings', false);
        $response->assertSee('pane-logs', false);
        $response->assertSee('Payment Methods for Ticket Kiosk');
    }

    public function test_saving_a_custom_payment_method_override_changes_the_kiosk(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post('/admin/tickets/settings', [
            'payment_methods' => ['Cash'],
        ])->assertRedirect();

        $this->assertSame('["Cash"]', Setting::get('ticket_payment_methods_override'));

        $kiosk = $this->actingAs($admin)->get('/admin/tickets/pos');
        $kiosk->assertOk();
        $kiosk->assertSee('"Cash"', false);
        $kiosk->assertDontSee('"UPI"', false);
        $kiosk->assertDontSee('"Bank Transfer"', false);
    }

    public function test_reverting_to_global_payment_methods_clears_the_override(): void
    {
        $admin = $this->adminUser();
        Setting::set('ticket_payment_methods_override', json_encode(['Cash']));

        $this->actingAs($admin)->post('/admin/tickets/settings', [
            'use_global_payment_methods' => '1',
        ])->assertRedirect();

        $this->assertSame('', Setting::get('ticket_payment_methods_override'));
    }

    public function test_logs_pane_shows_ticket_related_audit_entries(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson('/admin/ticket/store', [
            'name' => 'Log Test Ticket', 'price' => '5.00', 'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee("Created ticket type &#039;Log Test Ticket&#039;", false);
    }

    public function test_non_console_manager_cannot_update_settings(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($user)->post('/admin/tickets/settings', [
            'payment_methods' => ['Cash'],
        ]);

        $response->assertStatus(403);
    }
}
