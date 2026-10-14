<?php

namespace Tests\Feature;

use App\Models\Pooja;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The Ticket POS's old horizontal topbar (title, Pooja Portal link, EFT Terminal Settings
 * icon, Account dropdown with Settle Till/Manage PIN/Logout) moved to a right-docked,
 * icon-only, expandable side nav — Archana, Poojas and Till are each their own full view
 * swapped by the nav (no in-page toggle, no Settle Till popup), the Account item becomes
 * a flyout (Manage PIN + a confirm-before-navigating Logout), and the Poojas view gained
 * a grid/table display toggle.
 */
class TicketPosSideNavTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_the_side_nav_has_archana_poojas_till_and_account_items(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('id="posSideNav"', false);
        $response->assertSee('data-view="archana"', false);
        $response->assertSee('data-view="poojas"', false);
        $response->assertSee('data-view="till"', false);
        $response->assertSee('id="posEftSettingsTopbarBtn"', false);
        $response->assertSee('id="posNavUserBtn"', false);
        // The old topbar's own markup/trigger is gone.
        $response->assertDontSee('class="pos-topbar"', false);
        $response->assertDontSee('id="settleTillModal"', false);
    }

    public function test_the_user_flyout_holds_a_confirm_gated_logout(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('id="posUserFlyout"', false);
        $response->assertSee('id="posLogoutLink"', false);
        // The logout link is wired with a confirm() prompt in JS, not a bare navigation.
        $response->assertSee("confirm('Log out of the POS?')", false);
    }

    public function test_the_till_view_renders_the_cash_settlement_pane_inline_not_in_a_modal(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('id="posTillPane"', false);
        $response->assertSee('id="posTillPaneBody"', false);
        $response->assertSee('Last settled:');
    }

    public function test_the_poojas_view_has_a_grid_table_toggle_and_renders_both_layouts(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create(['pooja_name' => 'Side Nav Test Pooja', 'category' => 'Festivals', 'pooja_fee' => 55, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('class="pos-view-toggle"', false);
        $response->assertSee('data-view="grid"', false);
        $response->assertSee('data-view="table"', false);
        $response->assertSee('class="pos-category-table"', false);
        $response->assertSee('class="pos-table-row"', false);
        $response->assertSee($pooja->pooja_name);
    }

    public function test_the_grid_table_toggle_is_absent_without_bookings_add_permission(): void
    {
        \App\Models\RolePermission::where('role', 'Committee')->where('resource', 'bookings')->update(['can_add' => false]);
        $committee = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($committee)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('class="pos-view-toggle"', false);
        $response->assertDontSee('data-view="poojas"', false);
    }

    public function test_the_pooja_portal_link_is_removed_from_the_nav(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('>Portal<', false);
        $response->assertDontSee('title="Pooja Portal"', false);
    }

    public function test_eft_terminal_settings_is_its_own_view_not_a_popup(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('data-view="eft-settings"', false);
        $response->assertSee('id="posEftSettingsPane"', false);
        $response->assertSee('id="posEftSettingsFrame"', false);
        // The old popup overlay/iframe partial is gone entirely.
        $response->assertDontSee('id="eftSettingsModalOverlay"', false);
        $response->assertDontSee('id="eftSettingsModalFrame"', false);
    }

    public function test_each_view_shows_its_own_header(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('id="posViewHeaderTitle"', false);
        $response->assertSee('Archana Tickets');
        $response->assertSee('Pooja Bookings');
        $response->assertSee('Transactions');
        $response->assertSee('Terminal Settings');
    }

    public function test_the_side_nav_expand_toggle_is_the_last_item_in_the_rail(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $content = $response->getContent();
        $navStart = strpos($content, 'id="posSideNav"');
        $navEnd = strpos($content, '</nav>', $navStart);
        $toggleFragment = '<button type="button" class="pos-side-nav-toggle" id="posSideNavToggle"';
        $togglePos = strpos($content, $toggleFragment, $navStart);
        $userFlyoutPos = strpos($content, 'id="posUserFlyout"', $navStart);
        $this->assertNotFalse($togglePos);
        $this->assertNotFalse($userFlyoutPos);
        $this->assertLessThan($navEnd, $togglePos);
        $this->assertGreaterThan($userFlyoutPos, $togglePos, 'The expand/collapse toggle should come after the user flyout, i.e. sit at the bottom of the rail.');
    }
}
