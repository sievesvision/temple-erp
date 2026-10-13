<?php

namespace Tests\Feature;

use App\Models\EftTerminal;
use App\Models\LinklyTransaction;
use App\Models\SciTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2C: /admin/transactions — the one gap in the existing reporting surface (donations
 * and ticket orders are already organisation-wide; the EFT/SCI transaction ledger only
 * ever existed per-event or tickets-only). See OrganisationReportController's own docblock.
 */
class OrganisationTransactionsTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function makeEvent(string $name): int
    {
        return DB::table('events')->insertGetId([
            'event_name' => $name, 'event_date' => now()->addMonth()->toDateString(),
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_page_spans_more_than_one_event_in_a_single_list(): void
    {
        $admin = $this->admin();
        $terminal = $this->defaultEftTerminal();
        $eventA = $this->makeEvent('Event A');
        $eventB = $this->makeEvent('Event B');

        LinklyTransaction::create(['pos_txn_ref' => 'a1', 'client_ref' => 'a1', 'linkly_session_id' => 's-a1', 'txn_type' => 'purchase', 'event_id' => $eventA, 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'approved', 'initiated_by' => $admin->id]);
        LinklyTransaction::create(['pos_txn_ref' => 'b1', 'client_ref' => 'b1', 'linkly_session_id' => 's-b1', 'txn_type' => 'purchase', 'event_id' => $eventB, 'eft_terminal_id' => $terminal->id, 'amount' => 20, 'status' => 'approved', 'initiated_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.transactions.index'));

        $response->assertOk();
        $response->assertSee('Event A');
        $response->assertSee('Event B');
    }

    public function test_ticket_sales_transactions_event_id_null_are_shown_as_ticket_sales(): void
    {
        $admin = $this->admin();
        $terminal = $this->defaultEftTerminal();
        LinklyTransaction::create(['pos_txn_ref' => 't1', 'client_ref' => 't1', 'linkly_session_id' => 's-t1', 'txn_type' => 'purchase', 'event_id' => null, 'eft_terminal_id' => $terminal->id, 'amount' => 15, 'status' => 'approved', 'initiated_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.transactions.index'));

        $response->assertOk();
        $response->assertSee('Ticket Sales');
    }

    public function test_filtering_by_event_id_excludes_other_events(): void
    {
        $admin = $this->admin();
        $terminal = $this->defaultEftTerminal();
        $eventA = $this->makeEvent('Filter Event A');
        $eventB = $this->makeEvent('Filter Event B');
        LinklyTransaction::create(['pos_txn_ref' => 'fa', 'client_ref' => 'fa', 'linkly_session_id' => 's-fa', 'txn_type' => 'purchase', 'event_id' => $eventA, 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'approved', 'initiated_by' => $admin->id]);
        LinklyTransaction::create(['pos_txn_ref' => 'fb', 'client_ref' => 'fb', 'linkly_session_id' => 's-fb', 'txn_type' => 'purchase', 'event_id' => $eventB, 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'approved', 'initiated_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.transactions.index', ['event_id' => $eventA]));

        $response->assertOk();
        // Both names also appear once in the (unfiltered) event dropdown, so a bare
        // assertDontSee would fail on the dropdown option alone — compare occurrence
        // counts instead: the filtered-in event appears in its table row too (2+), the
        // filtered-out one only in the dropdown (1).
        $content = $response->getContent();
        $this->assertGreaterThan(1, substr_count($content, 'Filter Event A'));
        $this->assertSame(1, substr_count($content, 'Filter Event B'));
    }

    public function test_filtering_by_provider_only_shows_that_providers_rows(): void
    {
        $admin = $this->admin();
        $terminal = $this->defaultEftTerminal();
        $sciTerminal = EftTerminal::create(['key' => \Illuminate\Support\Str::random(20), 'label' => 'SCI Terminal', 'provider' => 'cba_sci', 'pos_id' => EftTerminal::generatePosId(), 'sci_pairing_id' => 'pid_' . \Illuminate\Support\Str::uuid()]);
        LinklyTransaction::create(['pos_txn_ref' => 'lk', 'client_ref' => 'lk', 'linkly_session_id' => 's-lk', 'txn_type' => 'purchase', 'event_id' => null, 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'approved', 'initiated_by' => $admin->id]);
        SciTransaction::create(['client_ref' => 'sci1', 'sci_transaction_id' => 'sci-txn-1', 'event_id' => null, 'eft_terminal_id' => $sciTerminal->id, 'txn_type' => 'PURCHASE', 'amount' => 10, 'status' => 'FINALISED', 'initiated_by' => $admin->id]);

        // "Linkly Cloud"/"CBA Smart Terminal" each also appear once in the (unfiltered)
        // provider dropdown — compare occurrence counts the same way the event filter test
        // above does, rather than a bare assertDontSee.
        $linklyOnly = $this->actingAs($admin)->get(route('admin.transactions.index', ['provider' => 'linkly']))->getContent();
        $this->assertGreaterThan(1, substr_count($linklyOnly, 'Linkly Cloud'));
        $this->assertSame(1, substr_count($linklyOnly, 'CBA Smart Terminal'));

        $sciOnly = $this->actingAs($admin)->get(route('admin.transactions.index', ['provider' => 'cba_sci']))->getContent();
        $this->assertGreaterThan(1, substr_count($sciOnly, 'CBA Smart Terminal'));
        $this->assertSame(1, substr_count($sciOnly, 'Linkly Cloud'));
    }

    public function test_filtering_by_operator_only_shows_their_transactions(): void
    {
        $admin = $this->admin();
        $terminal = $this->defaultEftTerminal();
        // Explicit, plain names rather than Faker's default — fake()->name() occasionally
        // generates a name with an apostrophe (e.g. "O'Connell"), which Blade's {{ }}
        // HTML-escapes to "O&#039;Connell" in the rendered page; a raw substr_count() against
        // the un-escaped name then intermittently finds zero matches and fails, even though
        // the filtering itself is correct. This was flaky in CI for exactly that reason.
        $operatorA = User::factory()->create(['name' => 'Operator Alpha Test', 'mobile' => fake()->unique()->numerify('04########')]);
        $operatorB = User::factory()->create(['name' => 'Operator Beta Test', 'mobile' => fake()->unique()->numerify('04########')]);
        LinklyTransaction::create(['pos_txn_ref' => 'oa', 'client_ref' => 'oa', 'linkly_session_id' => 's-oa', 'txn_type' => 'purchase', 'event_id' => null, 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'approved', 'initiated_by' => $operatorA->id]);
        LinklyTransaction::create(['pos_txn_ref' => 'ob', 'client_ref' => 'ob', 'linkly_session_id' => 's-ob', 'txn_type' => 'purchase', 'event_id' => null, 'eft_terminal_id' => $terminal->id, 'amount' => 10, 'status' => 'approved', 'initiated_by' => $operatorB->id]);

        $response = $this->actingAs($admin)->get(route('admin.transactions.index', ['operator_id' => $operatorA->id]));
        $response->assertOk();

        // Both names also appear once in the (unfiltered) operator dropdown — same
        // occurrence-count comparison as the event/provider filter tests above.
        $content = $response->getContent();
        $this->assertGreaterThan(1, substr_count($content, $operatorA->name));
        $this->assertSame(1, substr_count($content, $operatorB->name));
    }

    public function test_a_non_admin_cannot_view_organisation_transactions(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'Committee', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($nonAdmin)->get(route('admin.transactions.index'))->assertForbidden();
    }

    public function test_the_hub_links_to_the_existing_donations_tickets_reports_and_logs_pages(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.transactions.index'));

        $response->assertOk();
        $response->assertSee(route('admin.donations.index'), false);
        $response->assertSee(route('admin.tickets.index'), false);
        $response->assertSee(route('admin.reports.index'), false);
        $response->assertSee(route('admin.logs.index'), false);
    }
}
