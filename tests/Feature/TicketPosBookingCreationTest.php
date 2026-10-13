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
 * Phase E of the Pooja Bookings plan: pooja booking tiles live in the Ticket POS's main
 * item grid, alongside the Archana tickets — same category tabs, same search bar, no
 * separate "Book Pooja" topbar button. Tapping a pooja tile opens a booking-details popup
 * (date/time/priest/devotee/payment); tapping an Archana ticket tile still opens the
 * existing quantity popup. Both post through admin.bookings.store / the ticket order
 * endpoint respectively. See the plan's own Tests section.
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

    public function test_pooja_tiles_appear_in_the_main_item_grid_with_a_book_badge_not_a_topbar_button(): void
    {
        $admin = $this->admin();
        $pooja = $this->activePooja();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('posBookPoojaBtn', false);
        $response->assertSee('pos-item-tile-pooja', false);
        $response->assertSee('tile-book-badge', false);
        $response->assertSee($pooja->pooja_name);
    }

    public function test_the_main_grid_has_a_search_bar_and_category_tabs_spanning_tickets_and_poojas(): void
    {
        $admin = $this->admin();
        $pooja = Pooja::create([
            'pooja_name' => 'POS Grid Festival Pooja',
            'category' => 'Festivals',
            'pooja_fee' => 45,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('posItemsSearch', false);
        $response->assertSee('posCategoryTabs', false);
        $response->assertSee('data-category="Festivals"', false);
        $response->assertSee('Custom Pooja');
        $response->assertSee($pooja->pooja_name);
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
}
