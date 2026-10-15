<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase E of the Pooja Bookings plan: Archana tickets and pooja bookings each get their
 * own main-menu panel on the Ticket POS (never mixed in one grid), each with its own
 * category tabs grouping its items into labeled sections. Tapping a pooja tile opens a
 * booking-details popup (date/time/priest/devotee/payment); tapping an Archana ticket
 * tile still opens the existing quantity popup. Both post through admin.bookings.store /
 * the ticket order endpoint respectively. See the plan's own Tests section.
 */
class TicketPosBookingCreationTest extends TestCase
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

    private function activePooja(): Pooja
    {
        return Pooja::create([
            'pooja_name' => 'POS Test Archana',
            'pooja_fee' => 45,
            'duration' => '15',
            'status' => 'Active',
            'online_allowed' => false,
        ]);
    }

    private function activePriest(): Priest
    {
        $user = User::factory()->create(['role' => 'Priest']);

        return Priest::create([
            'priest_id' => 'PRIEST' . $user->id,
            'user_id' => $user->id,
            'employment_status' => 'Active',
        ]);
    }

    public function test_archana_and_poojas_have_separate_main_menu_panels_not_a_shared_topbar_button(): void
    {
        $admin = $this->admin();
        $pooja = $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('posBookPoojaBtn', false);
        $response->assertSee('data-view="archana"', false);
        $response->assertSee('data-view="poojas"', false);
        $response->assertSee('data-menu="archana"', false);
        $response->assertSee('data-menu="poojas"', false);
        $response->assertSee('pos-item-tile-pooja', false);
        $response->assertSee($pooja->pooja_name);
    }

    public function test_pooja_tiles_no_longer_show_a_book_badge(): void
    {
        $admin = $this->admin();
        $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('tile-book-badge', false);
    }

    public function test_the_poojas_menu_groups_items_into_labeled_category_sections(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create([
            'pooja_name' => 'POS Grid Festival Pooja',
            'category' => 'Festivals',
            'pooja_fee' => 45,
            'status' => 'Active',
        ]);
        Pooja::create([
            'pooja_name' => 'POS Grid Abishekam Pooja',
            'category' => 'Sponsored Abishekams',
            'pooja_fee' => 60,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('posItemsSearch', false);
        $response->assertSee('pos-category-heading', false);
        $response->assertSee('data-category="Festivals"', false);
        $response->assertSee('data-category="Sponsored Abishekams"', false);
        $response->assertSee('Custom Pooja');
        $response->assertSee($pooja->pooja_name);
    }

    public function test_pooja_name_renders_in_normal_case_not_forced_uppercase(): void
    {
        $admin = $this->admin();
        $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('pos-item-tile-pooja .tile-name', false);
    }

    public function test_pooja_tiles_and_the_custom_pooja_tile_are_hidden_without_bookings_add_permission(): void
    {
        RolePermission::where('role', 'Committee')->where('resource', 'bookings')->update(['can_add' => false]);
        $committee = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->activePooja();

        $response = $this->actingAs($committee)->get(route('admin.tickets.pos'));

        $response->assertOk();
        // Bare substrings like 'pos-item-tile-pooja' or 'Custom Pooja' would also match the
        // page's own static CSS rule / JS comments (always present, regardless of whether
        // any tile uses them) — check for actual rendered tile markup instead.
        $response->assertDontSee('class="pos-item-tile pos-item-tile-pooja', false);
        $response->assertDontSee('<span class="tile-name">Custom Pooja</span>', false);
        // A bare 'data-menu="poojas"' would also match the page's own static CSS (the new
        // grid/table view-toggle rule targets '.pos-menu-panel[data-menu="poojas"]',
        // always present in source regardless of whether the panel itself renders) — check
        // for the actual rendered panel/tab-bar element instead.
        $response->assertDontSee('<div class="pos-menu-panel d-none" data-menu="poojas">', false);
        $response->assertDontSee('<div class="pos-category-tabs d-none" data-menu="poojas">', false);
        $response->assertDontSee('data-view="poojas"', false);
    }

    public function test_the_ticket_pos_booking_form_creates_a_confirmed_booking_via_ajax(): void
    {
        $admin = $this->admin();
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($admin)->postJson(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'POS Devotee',
            'devotee_mobile' => '0488112233',
            'devotee_email' => 'pos.devotee@example.com',
            'payment_method' => 'Cash',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame('Confirmed', $booking->booking_status);
    }

    public function test_the_booking_form_has_a_participant_details_field_and_it_is_saved(): void
    {
        $admin = $this->admin();
        $this->activePriest();
        $pooja = $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));
        $response->assertOk();
        $response->assertSee('name="participant_details"', false);

        $response = $this->actingAs($admin)->postJson(route('admin.bookings.store'), [
            'pooja_id' => $pooja->pooja_id,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_time' => '10:00:00',
            'devotee_name' => 'POS Devotee',
            'devotee_mobile' => '0488112244',
            'devotee_email' => 'pos.devotee2@example.com',
            'payment_method' => 'Cash',
            'participant_details' => "Ravi Kumar — Bharadwaja gothram, Rohini nakshatram\nMeena Kumar — same gothram, Chitra nakshatram",
        ]);

        $response->assertOk();
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertStringContainsString('Bharadwaja gothram', $booking->participant_details);
        $this->assertStringContainsString('Chitra nakshatram', $booking->participant_details);
    }
}
