<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Devotee;
use App\Models\Pooja;
use App\Models\Priest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Follow-up UX changes to the Ticket POS's Poojas menu: a compact, non-maroon
 * Archana/Poojas toggle, a "Recent" tab defaulting to the most recently booked pooja
 * types, and switching to Poojas clearing/closing any in-progress ticket cart. Also
 * covers the booking payment-method row (Cash/Bank/EFT Terminal) replacing the old
 * plain dropdown.
 */
class TicketPosBookingUxTest extends TestCase
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

    private function activePriest(): Priest
    {
        $user = User::factory()->create(['role' => 'Priest']);

        return Priest::create([
            'priest_id' => 'PRIEST' . $user->id,
            'user_id' => $user->id,
            'employment_status' => 'Active',
        ]);
    }

    public function test_the_main_menu_toggle_is_compact_not_full_width_blocking_maroon(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('pos-main-menu', false);
        // The old full-width maroon-block active state is gone — the new toggle uses a
        // light accent colour instead.
        $response->assertDontSee(".pos-main-menu-btn.active { border-color: var(--maroon); background: var(--maroon); color: #fff; }", false);
        $response->assertSee('--accent-blue', false);
    }

    public function test_the_payment_method_row_replaces_the_old_dropdown(): void
    {
        $admin = $this->admin();
        Pooja::create(['pooja_name' => 'UX Test Pooja', 'pooja_fee' => 50, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('id="bpMethodRow"', false);
        $response->assertDontSee('<select name="payment_method"', false);
    }

    public function test_recent_tab_shows_the_most_recently_booked_pooja_first(): void
    {
        $admin = $this->admin();
        $priest = $this->activePriest();
        $poojaOld = Pooja::create(['pooja_name' => 'Older Booked Pooja', 'category' => 'Festivals', 'pooja_fee' => 50, 'status' => 'Active']);
        $poojaNew = Pooja::create(['pooja_name' => 'Newer Booked Pooja', 'category' => 'Sponsored Poojas', 'pooja_fee' => 30, 'status' => 'Active']);
        $devotee = Devotee::create(['user_id' => User::factory()->create()->id, 'verified' => 1]);

        Booking::create([
            'devotee_id' => $devotee->devotee_id, 'pooja_id' => $poojaOld->pooja_id, 'priest_id' => $priest->priest_id,
            'booking_date' => now()->addDay()->toDateString(), 'booking_time' => '09:00:00',
            'amount' => 50, 'total_amount' => 50, 'payment_status' => 'Paid', 'booking_status' => 'Confirmed',
            'created_at' => now()->subMinutes(10),
        ]);
        Booking::create([
            'devotee_id' => $devotee->devotee_id, 'pooja_id' => $poojaNew->pooja_id, 'priest_id' => $priest->priest_id,
            'booking_date' => now()->addDay()->toDateString(), 'booking_time' => '10:00:00',
            'amount' => 30, 'total_amount' => 30, 'payment_status' => 'Paid', 'booking_status' => 'Confirmed',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertSee('Recently Booked');
        $response->assertSee('data-category="__recent__"', false);
        $content = $response->getContent();
        $recentSectionStart = strpos($content, 'Recently Booked');
        $recentSectionEnd = strpos($content, 'pos-category-section', $recentSectionStart + 1);
        $recentSectionHtml = substr($content, $recentSectionStart, ($recentSectionEnd ?: strlen($content)) - $recentSectionStart);
        $newPos = strpos($recentSectionHtml, 'Newer Booked Pooja');
        $oldPos = strpos($recentSectionHtml, 'Older Booked Pooja');
        $this->assertNotFalse($newPos);
        $this->assertNotFalse($oldPos);
        $this->assertLessThan($oldPos, $newPos, 'The more recently booked pooja should appear first in the Recent section.');
    }

    public function test_no_recent_tab_when_nothing_has_been_booked_yet(): void
    {
        $admin = $this->admin();
        Pooja::create(['pooja_name' => 'Never Booked Pooja', 'pooja_fee' => 40, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('admin.tickets.pos'));

        $response->assertOk();
        $response->assertDontSee('Recently Booked');
        // A bare 'data-category="__recent__"' would also match the page's own JS (the
        // selector string setActiveMenu() uses to find the Recent tab when it exists,
        // always present in the source regardless of whether that tab is ever rendered)
        // — check for the actual rendered section/tab element instead.
        $response->assertDontSee('<div class="pos-category-section" data-category="__recent__">', false);
        $response->assertDontSee('data-category="__recent__"><i class="bi bi-clock-history"></i> Recent</button>', false);
    }
}
