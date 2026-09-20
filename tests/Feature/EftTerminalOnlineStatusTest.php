<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\LinklyTransaction;
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
