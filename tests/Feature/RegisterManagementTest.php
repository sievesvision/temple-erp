<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\Register;
use App\Models\User;
use App\Services\RegisterService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2B: registers — a physical counter/station paired to its own browser via a short
 * code (mirrors Kiosk Device pairing), with its own per-event/ticket-sales default EFT
 * terminal. See RegisterService, IdentifyRegister, and
 * EftTerminal::defaultForContext()'s own docblock for the resolution priority this feeds.
 */
class RegisterManagementTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedTerminal(string $label, bool $isDefault = false): EftTerminal
    {
        return EftTerminal::create([
            'key' => \Illuminate\Support\Str::random(20), 'label' => $label, 'provider' => 'cba_sci',
            'pos_id' => EftTerminal::generatePosId(),
            'is_default' => $isDefault, 'sci_pairing_id' => 'pid_' . \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function test_admin_can_create_a_register(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.registers.store'), ['name' => 'Front Counter']);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Register::where('name', 'Front Counter')->count());
    }

    public function test_a_non_admin_cannot_create_a_register(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($nonAdmin)->post(route('admin.registers.store'), ['name' => 'Sneaky Counter']);

        $this->assertSame(0, Register::where('name', 'Sneaky Counter')->count());
    }

    public function test_a_valid_pairing_code_issues_a_working_cookie(): void
    {
        $admin = $this->admin();
        $register = RegisterService::create('Front Counter', $admin);
        $code = RegisterService::generatePairingCode($register, $admin);

        $response = $this->post(route('register.pair.submit'), ['code' => $code]);

        $response->assertSessionHasNoErrors();
        $register->refresh();
        $this->assertNotNull($register->paired_at);
        $response->assertCookie(RegisterService::COOKIE_NAME);
    }

    public function test_an_invalid_code_is_rejected(): void
    {
        $response = $this->post(route('register.pair.submit'), ['code' => 'NOTAREAL']);

        $response->assertSessionHasErrors('code');
    }

    public function test_a_code_cannot_be_redeemed_twice(): void
    {
        $admin = $this->admin();
        $register = RegisterService::create('Front Counter', $admin);
        $code = RegisterService::generatePairingCode($register, $admin);

        $this->post(route('register.pair.submit'), ['code' => $code]);
        $second = $this->post(route('register.pair.submit'), ['code' => $code]);

        $second->assertSessionHasErrors('code');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $admin = $this->admin();
        $register = RegisterService::create('Front Counter', $admin);
        $code = RegisterService::generatePairingCode($register, $admin);
        DB::table('register_pairing_codes')->where('register_id', $register->id)->update(['expires_at' => now()->subMinute()]);

        $response = $this->post(route('register.pair.submit'), ['code' => $code]);

        $response->assertSessionHasErrors('code');
    }

    public function test_the_register_cookie_is_identified_on_subsequent_requests(): void
    {
        $admin = $this->admin();
        $register = RegisterService::create('Front Counter', $admin);
        $code = RegisterService::generatePairingCode($register, $admin);
        $pairResponse = $this->post(route('register.pair.submit'), ['code' => $code]);
        $token = $pairResponse->headers->getCookies()[0]->getValue();

        $coordinator = User::factory()->create(['role' => 'Event Coordinator', 'mobile' => fake()->unique()->numerify('04########')]);
        $eventId = DB::table('events')->insertGetId(['event_name' => 'Reg Test Event', 'event_date' => now()->addMonth()->toDateString(), 'slug' => 'reg-test-' . uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('event_coordinators')->insert(['user_id' => $coordinator->id, 'event_id' => $eventId, 'level' => 'pos', 'created_at' => now(), 'updated_at' => now()]);

        $terminal = $this->pairedTerminal('Register Terminal');
        RegisterService::setDefaultTerminal($register, 'event', $eventId, $terminal->id, $admin);

        $response = $this->actingAs($coordinator)
            ->withCookie(RegisterService::COOKIE_NAME, $token)
            ->get(route('admin.events.pos', $eventId));

        $response->assertOk();
        $response->assertSee('Register Terminal');
        $register->refresh();
        $this->assertNotNull($register->last_seen_at);
    }

    public function test_default_for_context_prefers_the_registers_configured_terminal_over_the_global_default(): void
    {
        $admin = $this->admin();
        $this->defaultEftTerminal(); // the seeded is_default row — see TestCase's own docblock
        $registerTerminal = $this->pairedTerminal('Register Specific');
        $register = RegisterService::create('Front Counter', $admin);
        RegisterService::setDefaultTerminal($register, 'event', 5, $registerTerminal->id, $admin);

        $resolved = EftTerminal::defaultForContext($register, 'event', 5);

        $this->assertSame($registerTerminal->id, $resolved->id);
    }

    public function test_default_for_context_falls_back_to_the_global_default_when_unconfigured(): void
    {
        $admin = $this->admin();
        // The migration seeds exactly one is_default row already — reuse it (via the base
        // TestCase's own helper) rather than creating a second is_default=true row, which
        // would leave two "default" terminals and make the resolution ambiguous.
        $globalDefault = $this->defaultEftTerminal();
        $register = RegisterService::create('Front Counter', $admin);
        // No per-context default set for event 6.

        $resolved = EftTerminal::defaultForContext($register, 'event', 6);

        $this->assertSame($globalDefault->id, $resolved->id);
    }

    public function test_the_same_register_can_have_different_defaults_for_different_events(): void
    {
        $admin = $this->admin();
        $terminalA = $this->pairedTerminal('Terminal A');
        $terminalB = $this->pairedTerminal('Terminal B');
        $register = RegisterService::create('Front Counter', $admin);
        RegisterService::setDefaultTerminal($register, 'event', 10, $terminalA->id, $admin);
        RegisterService::setDefaultTerminal($register, 'event', 20, $terminalB->id, $admin);
        RegisterService::setDefaultTerminal($register, 'tickets', null, $terminalB->id, $admin);

        $this->assertSame($terminalA->id, EftTerminal::defaultForContext($register, 'event', 10)->id);
        $this->assertSame($terminalB->id, EftTerminal::defaultForContext($register, 'event', 20)->id);
        $this->assertSame($terminalB->id, EftTerminal::defaultForContext($register, 'tickets', null)->id);
    }

    public function test_unpairing_clears_the_token_so_the_old_cookie_no_longer_resolves(): void
    {
        $admin = $this->admin();
        $register = RegisterService::create('Front Counter', $admin);
        $code = RegisterService::generatePairingCode($register, $admin);
        $this->post(route('register.pair.submit'), ['code' => $code]);
        $register->refresh();
        $this->assertTrue($register->isPaired());

        $this->actingAs($admin)->post(route('admin.registers.unpair', $register->id));

        $this->assertFalse($register->fresh()->isPaired());
    }

    public function test_a_non_admin_cannot_set_terminal_defaults(): void
    {
        $admin = $this->admin();
        $nonAdmin = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);
        $register = RegisterService::create('Front Counter', $admin);
        $terminal = $this->pairedTerminal('Some Terminal');

        $this->actingAs($nonAdmin)->post(route('admin.registers.setTerminal', $register->id), [
            'context_type' => 'tickets', 'eft_terminal_id' => $terminal->id,
        ]);

        $this->assertSame(0, DB::table('register_eft_terminal_defaults')->where('register_id', $register->id)->count());
    }
}
