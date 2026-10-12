<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\KioskDeviceService;
use App\Services\RegisterService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies App\Services\TransactionChannel actually gets stamped onto the new
 * source_channel column at the point each payment is recorded — portal/pos (told apart by
 * Referer, since both post to the same endpoints), kiosk, register and public/staff-counter
 * each exercised through their own real entry point, not just unit-tested in isolation.
 */
class TransactionChannelTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('linkly_mode', 'sandbox');
        $this->defaultEftTerminal();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => 'Channel Test Event', 'event_date' => now()->toDateString(), 'status' => 'Active',
            'slug' => 'channel-test-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_portal_quick_entry_donation_is_tagged_portal(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)->postJson(route('admin.events.portal.storeGuest', $eventId), [
            'donor_name' => 'Portal Guest', 'event_id' => $eventId, 'amount' => 25,
            'purpose' => 'General Donation', 'payment_method' => 'Cash', 'donation_date' => now()->toDateString(),
        ]);

        $response->assertOk();
        $row = DB::table('donations_without_logins')->where('donor_name', 'Portal Guest')->first();
        $this->assertNotNull($row);
        $this->assertSame('portal', $row->source_channel);
    }

    public function test_the_same_endpoint_submitted_from_the_pos_page_is_tagged_pos(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();

        $response = $this->actingAs($admin)
            ->withHeader('referer', url("/admin/events/{$eventId}/pos"))
            ->postJson(route('admin.events.portal.storeGuest', $eventId), [
                'donor_name' => 'POS Counter Guest', 'event_id' => $eventId, 'amount' => 25,
                'purpose' => 'General Donation', 'payment_method' => 'Cash', 'donation_date' => now()->toDateString(),
            ]);

        $response->assertOk();
        $row = DB::table('donations_without_logins')->where('donor_name', 'POS Counter Guest')->first();
        $this->assertNotNull($row);
        $this->assertSame('pos', $row->source_channel);
    }

    public function test_a_kiosk_donation_is_tagged_kiosk(): void
    {
        $eventId = $this->makeEvent();
        $admin = $this->admin();
        $device = KioskDeviceService::register('Channel Test Kiosk', null, 'donations', $eventId, null, null, $admin);
        $code = KioskDeviceService::generatePairingCode($device, $admin);
        $token = KioskDeviceService::redeemPairingCode($code, '127.0.0.1')['token'];

        $response = $this->postJson('/kiosk/api/donations/guest', [
            'donor_name' => 'Kiosk Guest', 'event_id' => $eventId, 'amount' => 25,
            'purpose' => 'General Donation', 'payment_method' => 'Cash', 'donation_date' => now()->toDateString(),
        ], ['X-Kiosk-Device-Token' => $token]);

        $response->assertOk();
        $row = DB::table('donations_without_logins')->where('donor_name', 'Kiosk Guest')->first();
        $this->assertNotNull($row);
        $this->assertSame('kiosk', $row->source_channel);
    }

    public function test_a_paired_register_donation_is_tagged_register(): void
    {
        $admin = $this->admin();
        $eventId = $this->makeEvent();
        $coordinator = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        DB::table('event_coordinators')->insert(['user_id' => $coordinator->id, 'event_id' => $eventId, 'level' => 'admin', 'created_at' => now(), 'updated_at' => now()]);

        $register = RegisterService::create('Channel Test Register', $admin);
        $code = RegisterService::generatePairingCode($register, $admin);
        $pairResponse = $this->post(route('register.pair.submit'), ['code' => $code]);
        $registerCookie = collect($pairResponse->headers->getCookies())->first(fn ($c) => $c->getName() === RegisterService::COOKIE_NAME);
        // The Set-Cookie value is already encrypted (EncryptCookies on the way out); undo
        // that here so withCookie() below — which encrypts whatever it's given exactly once,
        // matching what a real browser round-trip looks like — doesn't double-encrypt it.
        // Already encrypted once for the Set-Cookie header above — unwrap it back to the raw
        // token so withCookie() below (which encrypts exactly once, like a real cookie round
        // trip) doesn't double-encrypt it.
        $token = \Illuminate\Cookie\CookieValuePrefix::remove(decrypt($registerCookie->getValue(), false));

        $response = $this->actingAs($coordinator)
            ->withCookie(RegisterService::COOKIE_NAME, $token)
            // postJson() only attaches cookies when credentials are explicitly requested —
            // a real browser's same-origin fetch() always sends them, so this matches that.
            ->withCredentials()
            ->postJson(route('admin.events.portal.storeGuest', $eventId), [
                'donor_name' => 'Register Guest', 'event_id' => $eventId, 'amount' => 25,
                'purpose' => 'General Donation', 'payment_method' => 'Cash', 'donation_date' => now()->toDateString(),
            ]);

        $response->assertOk();
        $row = DB::table('donations_without_logins')->where('donor_name', 'Register Guest')->first();
        $this->assertNotNull($row);
        $this->assertSame('register', $row->source_channel);
    }

    public function test_an_anonymous_public_donation_is_tagged_public(): void
    {
        $eventId = $this->makeEvent();

        $response = $this->post(route('donate.without.login'), [
            'donor_name' => 'Website Donor', 'event_id' => $eventId, 'amount' => 25,
            'purpose' => 'General Donation', 'payment_method' => 'Cash',
        ]);

        $response->assertRedirect();
        $row = DB::table('donations_without_logins')->where('donor_name', 'Website Donor')->first();
        $this->assertNotNull($row);
        $this->assertSame('public', $row->source_channel);
    }

    public function test_staff_offline_counter_donation_is_tagged_staff_counter(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($staff)->post(route('staff.counter.record-donation'), [
            'donor_name' => 'Staff Counter Donor', 'mobile' => '0400111222', 'amount' => 25, 'purpose' => 'General',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $row = DB::table('donations_without_logins')->where('donor_name', 'Staff Counter Donor')->first();
        $this->assertNotNull($row);
        $this->assertSame('staff_counter', $row->source_channel);
    }
}
