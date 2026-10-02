<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The POS terminal picker's own live pairing check — mx51's certification checklist calls
 * for a GET /pairing-info both when the pairing screen is viewed and just before a
 * transaction starts (the latter already covered by CbaSciTransactionTest's startPurchase
 * coverage). The picker previously never checked at all: its list was built once at page
 * load from the cached sci_pairing_id flag, which only changes when someone explicitly
 * presses Unpair, so a pairing silently revoked on mx51's own side kept showing "Paired"
 * indefinitely. See CbaSciController::refreshPickerStatus().
 */
class CbaSciTerminalPickerTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function pairedSciTerminal(string $label = 'mx51 Terminal'): EftTerminal
    {
        return EftTerminal::create([
            'key' => 'sci-picker-' . uniqid(), 'label' => $label, 'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(), 'sci_pairing_id' => 'pid_123', 'sci_key_id' => 'kid_123',
            'sci_signing_secret_part_b' => 'secret-b', 'sci_api_base_url' => 'https://sci-api.tenant.example',
            'sci_paired_at' => now()->subMinutes(2),
        ]);
    }

    public function test_refresh_returns_every_terminal_including_unpaired_ones(): void
    {
        $admin = $this->adminUser();
        $paired = $this->pairedSciTerminal();
        $paired->update(['sci_tid' => '300999001']);
        $neverPaired = EftTerminal::create([
            'key' => 'sci-unpaired-' . uniqid(), 'label' => 'Unpaired mx51 Terminal', 'provider' => 'cba_sci',
            'pos_id' => (string) Str::uuid(),
        ]);
        Http::fake(['sci-api.tenant.example/*' => Http::response(['data' => []], 200)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.terminal-picker.refresh'));

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $terminals = collect($response->json('terminals'))->keyBy('id');
        $this->assertTrue($terminals[$paired->id]['paired']);
        $this->assertFalse($terminals[$neverPaired->id]['paired']);

        // The picker's own "mandatory fields" (Pairing ID, TID, key) — only meaningful for
        // mx51 terminals, present regardless of whether this exact call re-confirmed the
        // pairing is still active.
        $this->assertSame($paired->key, $terminals[$paired->id]['key']);
        $this->assertSame('pid_123', $terminals[$paired->id]['sci_pairing_id']);
        $this->assertSame('300999001', $terminals[$paired->id]['sci_tid']);
        $this->assertNull($terminals[$neverPaired->id]['sci_pairing_id']);

        // A terminal with no pairing ID at all has nothing to check — refreshPairingStatus()
        // short-circuits before ever touching the network for it (see its own "is a noop for
        // an already unpaired terminal" test in CbaSciPairingTest), only the genuinely paired
        // one should have triggered a real request.
        Http::assertSentCount(1);
    }

    public function test_a_pairing_revoked_on_mx51s_side_is_self_healed_and_reported_unpaired(): void
    {
        $admin = $this->adminUser();
        $terminal = $this->pairedSciTerminal();
        Http::fake(['sci-api.tenant.example/*' => Http::response(['error' => ['code' => 'no_active_pairings_found']], 401)]);

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.terminal-picker.refresh'));

        $response->assertOk();
        $terminals = collect($response->json('terminals'))->keyBy('id');
        $this->assertFalse($terminals[$terminal->id]['paired']);
        // Not just reported unpaired in this one response — actually cleared locally, exactly
        // like EftTerminalController::index()'s own call to the same service method, so every
        // other screen (settings, future picker loads) agrees instead of only this response.
        $this->assertFalse($terminal->fresh()->isSciPaired());
    }

    public function test_a_linkly_terminals_paired_flag_is_included_without_any_mx51_call(): void
    {
        $admin = $this->adminUser();
        $linkly = $this->defaultEftTerminal();

        $response = $this->actingAs($admin)->postJson(route('admin.cba-sci.terminal-picker.refresh'));

        $response->assertOk();
        $terminals = collect($response->json('terminals'))->keyBy('id');
        $this->assertArrayHasKey($linkly->id, $terminals->toArray());
        $this->assertNull($terminals[$linkly->id]['sci_pairing_id']);
        $this->assertNull($terminals[$linkly->id]['sci_tid']);
        Http::assertNothingSent();
    }

    public function test_refresh_requires_eft_terminal_permission(): void
    {
        // The route group's own role:... middleware gate (not this controller) is what
        // rejects a role like Devotee — it redirects rather than returning JSON, same as
        // every other endpoint in this group (see CbaSciTransactionTest's own cancel/refund
        // permission tests).
        $devotee = User::factory()->create(['role' => 'Devotee', 'mobile' => fake()->unique()->numerify('04########')]);
        $this->pairedSciTerminal();

        $response = $this->actingAs($devotee)->postJson(route('admin.cba-sci.terminal-picker.refresh'));

        $response->assertRedirect();
    }
}
