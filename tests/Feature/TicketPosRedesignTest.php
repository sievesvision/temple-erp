<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Tests\TestCase;

/**
 * Ticket POS redesign: the cart only renders once an item's been added (no permanently
 * visible empty "Order" panel), Settle Till moved from its own topbar icon into the
 * account dropdown, the PAY button uses the same maroon as event-pos-donation with a
 * dynamic PAY {amount} label instead of a static "Complete Sale & Print", and the item
 * grid now supports category tabs + a visible description per ticket type.
 */
class TicketPosRedesignTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_the_cart_pane_has_no_has_items_class_until_js_adds_it(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        // The CSS that hides it by default must be present...
        $response->assertSee('.pos-cart-pane:not(.has-items)', false);
        // ...and the static markup must not pre-mark it as having items.
        $response->assertDontSee('class="pos-cart-pane has-items"', false);
    }

    public function test_settle_till_is_its_own_side_nav_view_not_a_popup(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('data-view="till"', false);
        $response->assertSee('id="posTillPane"', false);
        // The old Settle Till modal/dropdown-item trigger is gone entirely.
        $response->assertDontSee('id="settleTillModal"', false);
        $response->assertDontSee('data-bs-target="#settleTillModal"', false);
    }

    public function test_the_pay_button_uses_the_maroon_brand_colour_and_a_pay_label(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('background: var(--maroon)', false);
        $response->assertSee('PAY', false);
        $response->assertDontSee('Complete Sale &amp; Print', false);
    }

    public function test_category_tabs_render_when_tickets_have_categories(): void
    {
        $admin = $this->admin();
        Ticket::create(['name' => 'Ganapathy Archana', 'category' => 'Archana', 'price' => 21, 'status' => 'Active']);
        Ticket::create(['name' => 'Abhishekam', 'category' => 'Pooja', 'price' => 51, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('<div class="pos-category-tabs" data-menu="archana">', false);
        $response->assertSee('data-category="Archana"', false);
        $response->assertSee('data-category="Pooja"', false);
        $response->assertSee('>Archana<', false);
    }

    public function test_no_category_tabs_render_when_only_one_ticket_category_exists(): void
    {
        $admin = $this->admin();
        // The standard Archana catalog (seeded by migration, category='Archana') and the
        // 42-pooja rate-card catalog (category='Sponsored Poojas' etc.) both exist in every
        // environment including a fresh test database — null/deactivate both here so this
        // test can exercise the real "only one category" case (tabs are redundant with a
        // single group) in isolation.
        Ticket::query()->update(['category' => null]);
        // poojas.category is NOT NULL (defaults to 'General') — deactivating every pooja
        // keeps them out of $bookingPoojas entirely instead, same end result for this test.
        \App\Models\Pooja::query()->update(['status' => 'Inactive']);
        Ticket::create(['name' => 'Plain Ticket', 'price' => 10, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        // A bare 'pos-category-tabs' would also match the page's own static CSS rule
        // (always present regardless of whether any tab bar renders) — check for the
        // actual rendered element instead.
        $response->assertDontSee('<div class="pos-category-tabs"', false);
    }

    public function test_ticket_description_is_shown_on_its_tile(): void
    {
        $admin = $this->admin();
        Ticket::create(['name' => 'Special Pooja', 'description' => 'A full archana with prasadam included.', 'price' => 101, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('A full archana with prasadam included.');
    }
}
