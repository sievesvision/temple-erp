<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\LinklyTransaction;
use App\Models\SciTransaction;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

/**
 * "Online"/"Offline" isn't a stored flag — Linkly has no standing connection to poll — it's
 * inferred from the most recent transaction (of any kind: Logon, Purchase, or Refund) that
 * actually got a definitive response from the terminal. See EftTerminal::lastKnownStatus().
 */
class EftTerminalOnlineStatusTest extends TestCase
{
    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    public function test_a_never_used_terminal_is_unknown(): void
    {
        $terminal = EftTerminal::factory()->create();

        $status = $terminal->lastKnownStatus();

        $this->assertSame('unknown', $status['state']);
        $this->assertNull($status['at']);
    }

    public function test_an_approved_transaction_marks_the_terminal_online(): void
    {
        $terminal = EftTerminal::factory()->create();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTONLINE1', 'txn_type' => 'purchase', 'eft_terminal_id' => $terminal->id,
            'status' => 'approved', 'amount' => 10,
        ]);

        $status = $terminal->lastKnownStatus();

        $this->assertSame('online', $status['state']);
        $this->assertNotNull($status['at']);
    }

    // A decline still proves the terminal was reached and responded — only a genuine
    // timeout/system-error ('failed') means it wasn't.
    public function test_a_declined_transaction_still_marks_the_terminal_online(): void
    {
        $terminal = EftTerminal::factory()->create();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTDECLINE1', 'txn_type' => 'purchase', 'eft_terminal_id' => $terminal->id,
            'status' => 'declined', 'amount' => 10,
        ]);

        $this->assertSame('online', $terminal->lastKnownStatus()['state']);
    }

    public function test_a_failed_transaction_marks_the_terminal_offline(): void
    {
        $terminal = EftTerminal::factory()->create();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTFAIL1', 'txn_type' => 'logon', 'eft_terminal_id' => $terminal->id,
            'status' => 'failed',
        ]);

        $this->assertSame('offline', $terminal->lastKnownStatus()['state']);
    }

    // Only the MOST RECENT definitive result counts — an old failure shouldn't keep showing
    // "Offline" after a later successful transaction.
    public function test_the_most_recent_definitive_result_wins(): void
    {
        $terminal = EftTerminal::factory()->create();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTOLD1', 'txn_type' => 'logon', 'eft_terminal_id' => $terminal->id, 'status' => 'failed',
        ]);
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTNEW1', 'txn_type' => 'purchase', 'eft_terminal_id' => $terminal->id, 'status' => 'approved', 'amount' => 5,
        ]);

        $this->assertSame('online', $terminal->lastKnownStatus()['state']);
    }

    // In-flight/inconclusive statuses (initiated/in_progress/unknown) prove nothing and must
    // be skipped in favour of the last genuinely definitive result.
    public function test_in_flight_statuses_are_skipped(): void
    {
        $terminal = EftTerminal::factory()->create();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTAPPROVED1', 'txn_type' => 'purchase', 'eft_terminal_id' => $terminal->id, 'status' => 'approved', 'amount' => 5,
        ]);
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTINPROG1', 'txn_type' => 'purchase', 'eft_terminal_id' => $terminal->id, 'status' => 'in_progress', 'amount' => 5,
        ]);

        $this->assertSame('online', $terminal->lastKnownStatus()['state']);
    }

    // mx51 terminals have no logon-style connectivity check of their own — a successful
    // pairing-info check (CbaSciService::testPairing()) is the only proof of life available
    // before any real transaction has happened, so it's folded into the same Connection
    // reading rather than leaving a freshly-paired, never-transacted terminal stuck "Not
    // checked" forever. See EftTerminal::lastKnownSciStatus().
    public function test_a_never_checked_never_transacted_sci_terminal_is_unknown(): void
    {
        $terminal = EftTerminal::factory()->create(['provider' => 'cba_sci']);

        $status = $terminal->lastKnownStatus();

        $this->assertSame('unknown', $status['state']);
        $this->assertNull($status['at']);
    }

    public function test_a_successful_pairing_check_marks_an_sci_terminal_online(): void
    {
        $terminal = EftTerminal::factory()->create(['provider' => 'cba_sci', 'sci_last_checked_at' => now()]);

        $status = $terminal->lastKnownStatus();

        $this->assertSame('online', $status['state']);
        $this->assertNotNull($status['at']);
    }

    public function test_a_newer_sci_transaction_reading_wins_over_an_older_pairing_check(): void
    {
        \Illuminate\Support\Carbon::setTestNow(now()->subHours(2));
        $terminal = EftTerminal::factory()->create(['provider' => 'cba_sci', 'sci_last_checked_at' => now()]);
        \Illuminate\Support\Carbon::setTestNow();
        SciTransaction::create([
            'client_ref' => 'sci-online-newer', 'eft_terminal_id' => $terminal->id, 'amount' => 10,
            'status' => 'FINALISED',
        ]);

        $this->assertSame('online', $terminal->lastKnownStatus()['state']);
    }

    public function test_a_newer_pairing_check_wins_over_an_older_sci_transaction(): void
    {
        \Illuminate\Support\Carbon::setTestNow(now()->subHours(2));
        $terminal = EftTerminal::factory()->create(['provider' => 'cba_sci']);
        SciTransaction::create([
            'client_ref' => 'sci-device-error-older', 'eft_terminal_id' => $terminal->id, 'amount' => 10,
            'status' => 'FINALISED', 'meta' => ['error_code' => 'device_not_connected'],
        ]);
        \Illuminate\Support\Carbon::setTestNow();
        $terminal->update(['sci_last_checked_at' => now()]);

        $this->assertSame('online', $terminal->lastKnownStatus()['state']);
    }

    // A failed pairing-info check means the pairing is the problem, not proof the physical
    // device is offline — CbaSciService::testPairing() never records sci_last_checked_at on
    // that path, so it can't ever surface as a false "offline" reading here.
    public function test_a_device_not_connected_transaction_still_marks_an_sci_terminal_offline(): void
    {
        $terminal = EftTerminal::factory()->create(['provider' => 'cba_sci', 'sci_last_checked_at' => now()->subMinutes(5)]);
        SciTransaction::create([
            'client_ref' => 'sci-offline', 'eft_terminal_id' => $terminal->id, 'amount' => 10,
            'status' => 'FAILED', 'meta' => ['error_code' => 'device_not_connected'], 'updated_at' => now(),
        ]);

        $this->assertSame('offline', $terminal->lastKnownStatus()['state']);
    }

    public function test_console_renders_online_and_offline_badges(): void
    {
        Setting::set('linkly_mode', 'sandbox');
        $online = $this->defaultEftTerminal();
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTONLINEUI', 'txn_type' => 'purchase', 'eft_terminal_id' => $online->id, 'status' => 'approved', 'amount' => 5,
        ]);
        $offline = EftTerminal::factory()->create(['key' => 'offline-terminal', 'label' => 'Offline Terminal']);
        LinklyTransaction::create([
            'pos_txn_ref' => 'EFTOFFLINEUI', 'txn_type' => 'logon', 'eft_terminal_id' => $offline->id, 'status' => 'failed',
        ]);

        $response = $this->actingAs($this->adminUser())->get('/admin/manage-tickets');

        $response->assertOk();
        $response->assertSee('Online');
        $response->assertSee('Offline');
    }
}
