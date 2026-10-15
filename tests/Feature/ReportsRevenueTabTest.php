<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Http\Controllers\SalaryController;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The Reports page's Revenue tab: a KPI summary, a 12-month trend, and a breakdown by
 * "avenue" (Donations / Ticket Sales / Pooja Bookings) — the one revenue dimension the
 * Reports page never had before (see SalaryController::revenueOverview()).
 *
 * Split in two: revenueOverview() itself is plain, portable query-builder code (runs fine
 * here, under SQLite) and is tested directly via reflection below — no HTTP, no touching
 * the page's other (pre-existing, untouched) tabs. The full-page HTTP tests are tagged
 * requires-mysql because SalaryController::reports() ALSO runs its pre-existing Monthly
 * Bookings/Donations Revenue queries, which use DATE_FORMAT() (MySQL-only) — same reason
 * as OfflineCounterTest. Those are verified locally against the real MySQL dev DB instead.
 */
class ReportsRevenueTabTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => 'Admin', 'mobile' => fake()->unique()->numerify('04########')]);
    }

    private function revenueOverview(): array
    {
        $controller = new SalaryController();
        $method = new \ReflectionMethod($controller, 'revenueOverview');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    public function test_revenue_overview_combines_devotee_and_guest_donations_into_one_avenue(): void
    {
        DB::table('donations')->insert([
            'devotee_id' => null, 'amount' => 100, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('donations_without_logins')->insert([
            'donor_name' => 'Revenue Tab Guest', 'amount' => 50, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ticket_orders')->insert([
            'customer_name' => 'Revenue Tab Ticket Buyer', 'total_amount' => 30, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'order_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $data = $this->revenueOverview();

        $byAvenue = collect($data['revenueByAvenue'])->keyBy('label');
        $this->assertSame(150.0, $byAvenue['Donations']['total']);
        $this->assertSame(30.0, $byAvenue['Ticket Sales']['total']);
        $this->assertSame(0.0, $byAvenue['Pooja Bookings']['total']);
        $this->assertSame(180.0, $data['revenueStats']['total_paid']);
        $this->assertSame(3, $data['revenueStats']['paid_count']);
    }

    public function test_revenue_overview_computes_month_over_month_change(): void
    {
        DB::table('donations')->insert([
            'devotee_id' => null, 'amount' => 200, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'donation_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('donations')->insert([
            'devotee_id' => null, 'amount' => 300, 'payment_method' => 'Cash', 'payment_status' => 'Paid',
            'donation_date' => now()->startOfMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $data = $this->revenueOverview();

        $this->assertSame(200.0, $data['revenueStats']['last_month']);
        $this->assertSame(300.0, $data['revenueStats']['this_month']);
        $this->assertSame(50.0, $data['revenueStats']['change_pct']);
    }

    public function test_revenue_overview_ignores_pending_and_cancelled_amounts(): void
    {
        DB::table('donations')->insert([
            'devotee_id' => null, 'amount' => 999, 'payment_method' => 'Cash', 'payment_status' => 'Pending',
            'donation_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $data = $this->revenueOverview();

        $this->assertSame(0.0, $data['revenueStats']['total_paid']);
        $this->assertSame(0, $data['revenueStats']['paid_count']);
    }

    public function test_revenue_overview_trend_has_twelve_months_most_recent_last(): void
    {
        $data = $this->revenueOverview();

        $this->assertCount(12, $data['revenueTrendLabels']);
        $this->assertCount(12, $data['revenueTrendValues']);
        $this->assertSame(now()->format('M Y'), $data['revenueTrendLabels']->last());
    }

    /**
     * requires-mysql: SalaryController::reports() also runs its pre-existing (untouched)
     * Monthly Bookings/Donations Revenue queries, which use DATE_FORMAT() — SQLite (the
     * default test connection, see phpunit.xml) doesn't support it. Verified locally
     * against the real MySQL dev DB.
     */
    #[Group('requires-mysql')]
    public function test_an_admin_can_view_the_reports_page(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
    }

    /**
     * The route itself is gated by role:Admin,Accountant middleware (routes/web.php) —
     * any other role never reaches the controller at all, redirected straight to their
     * own dashboard instead. No requires-mysql tag: this never reaches the DATE_FORMAT
     * queries, so it's fully portable.
     */
    public function test_a_role_outside_admin_or_accountant_is_redirected_to_its_own_dashboard(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'mobile' => fake()->unique()->numerify('04########')]);

        $response = $this->actingAs($staff)->get(route('admin.reports.index'));

        $response->assertRedirect(route('staff.dashboard'));
    }

    /**
     * Accountant passes the route-level role gate, so this exercises the controller's own
     * finer-grained RolePermission check instead — also portable, since reports() aborts
     * before reaching the DATE_FORMAT queries.
     */
    public function test_an_accountant_without_the_reports_grant_is_forbidden(): void
    {
        DB::table('role_permissions')->updateOrInsert(
            ['role' => 'Accountant', 'resource' => 'reports'],
            ['can_view' => false, 'created_at' => now(), 'updated_at' => now()]
        );
        $accountant = User::factory()->create(['role' => 'Accountant', 'mobile' => fake()->unique()->numerify('04########')]);

        $this->actingAs($accountant)->get(route('admin.reports.index'))->assertForbidden();
    }

    #[Group('requires-mysql')]
    public function test_the_revenue_tab_shows_kpis_insights_and_charts(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Total Revenue (Paid)');
        $response->assertSee('Paid Transactions');
        $response->assertSee('Revenue Trend (12 months)');
        $response->assertSee('Revenue by Avenue');
        $response->assertSee('id="revenueTrendChart"', false);
        $response->assertSee('id="revenueAvenueChart"', false);
    }

    /**
     * salary_payouts has no wallet_amount column (confirmed via Schema::getColumnListing)
     * — the Payroll tab's own summary query used to reference it directly and 500 the
     * whole page on every load, regardless of this new Revenue tab. Covers that it no
     * longer does, with a real payout row present so the query actually executes its
     * GROUP BY rather than trivially returning empty.
     */
    #[Group('requires-mysql')]
    public function test_the_page_loads_even_with_salary_payout_rows_present(): void
    {
        $admin = $this->admin();
        DB::table('salary_payouts')->insert([
            'user_id' => $admin->id, 'salary_month' => now()->format('Y-m'),
            'base_salary' => 1000, 'total_paid' => 1200, 'payment_status' => 'Paid',
            'payment_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
        // The reconstructed wallet share (total_paid - base_salary = 200) should render
        // as a positive figure, proving the SUM(total_paid - base_salary) fix is correct.
        $currency = Setting::get('currency_code', 'AUD');
        $response->assertSee('+' . $currency . ' 200.00', false);
    }
}
