<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use App\Models\RolePermission;

class SalaryController extends Controller
{
    /**
     * Show salary status and payout history.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'salaries', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        // Determine previous month name/value
        $prevMonthVal = date('Y-m', strtotime('first day of last month'));
        $prevMonthName = date('F Y', strtotime('first day of last month'));

        // Fetch Priests with status and wallets
        $priests = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.name', 'priests.monthly_salary as base_salary', 'priests.wallet_balance', 'users.role')
            ->get();

        // Fetch Staff
        $staff = DB::table('staff')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.name', 'staff.salary as base_salary', 'staff.wallet_balance', 'users.role')
            ->get();

        // Fetch Accountants
        $accountants = DB::table('accountants')
            ->join('users', 'accountants.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'users.name', 'accountants.salary as base_salary', DB::raw('0.00 as wallet_balance'), 'users.role')
            ->get();

        $employees = $priests->concat($staff)->concat($accountants);

        // Check who has already been paid for previous month
        $paidUserIds = DB::table('salary_payouts')
            ->where('salary_month', $prevMonthVal)
            ->pluck('user_id')
            ->toArray();

        // Calculate total amount required to pay the previous month's salary (excluding already sanctioned)
        $totalRequiredPrevMonth = 0;
        foreach ($employees as $emp) {
            $isPaid = in_array($emp->user_id, $paidUserIds);
            if (!$isPaid) {
                $totalRequiredPrevMonth += max(0.00, $emp->base_salary + $emp->wallet_balance);
            }
        }

        // Generate list of 4 recent months (e.g. 2 months ago, previous month, current month, next month)
        $monthsList = [];
        for ($i = -2; $i <= 1; $i++) {
            $mTime = strtotime("{$i} month");
            $mVal = date('Y-m', $mTime);
            $mName = date('F Y', $mTime);
            
            // Check if already paid
            $isPaid = DB::table('salary_payouts')->where('salary_month', $mVal)->exists();
            
            if ($isPaid) {
                $amount = DB::table('salary_payouts')->where('salary_month', $mVal)->sum('total_paid');
                $status = 'Sanctioned & Paid';
            } else {
                // If not paid, estimate based on current employee database
                $priestAmt = DB::table('priests')->sum('monthly_salary') + DB::table('priests')->sum('wallet_balance');
                $staffAmt = DB::table('staff')->sum('salary') + DB::table('staff')->sum('wallet_balance');
                $accAmt = DB::table('accountants')->sum('salary');
                $amount = max(0.00, $priestAmt) + max(0.00, $staffAmt) + $accAmt;
                
                if ($mVal >= date('Y-m')) {
                    $status = 'Accruing (Payable on ' . date('M 1, Y', strtotime('+1 month', strtotime($mVal . '-01'))) . ')';
                } else {
                    $status = 'Pending Sanction';
                }
            }

            // Can be sanctioned if it is a past month and not paid
            $canSanction = ($mVal < date('Y-m')) && !$isPaid;

            $monthsList[] = [
                'val' => $mVal,
                'name' => $mName,
                'amount' => $amount,
                'status' => $status,
                'is_paid' => $isPaid,
                'can_sanction' => $canSanction,
                'payable_date' => date('Y-m-d', strtotime('+1 month', strtotime($mVal . '-01')))
            ];
        }

        // Payout history
        $payoutHistory = DB::table('salary_payouts')
            ->join('users', 'salary_payouts.user_id', '=', 'users.id')
            ->select('salary_payouts.*', 'users.name')
            ->orderBy('salary_payouts.created_at', 'desc')
            ->get();

        return view('admin.salaries', compact(
            'employees',
            'prevMonthVal',
            'prevMonthName',
            'paidUserIds',
            'totalRequiredPrevMonth',
            'monthsList',
            'payoutHistory'
        ));
    }

    public function sanction(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'salaries', 'add')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $request->validate([
            'salary_month' => 'required|date_format:Y-m',
        ]);

        $salaryMonth = $request->input('salary_month');
        $currentMonth = date('Y-m');

        if ($salaryMonth >= $currentMonth) {
            $payableDate = date('F 1, Y', strtotime('+1 month', strtotime($salaryMonth . '-01')));
            return redirect()->back()->with('error', "Salary for " . date('F Y', strtotime($salaryMonth . '-01')) . " is not payable yet. It will become payable on " . $payableDate . ".");
        }

        $monthName = date('F Y', strtotime($salaryMonth . '-01'));

        $alreadyPaid = DB::table('salary_payouts')
            ->where('salary_month', $salaryMonth)
            ->exists();

        if ($alreadyPaid) {
            return redirect()->back()->with('error', "Salary payouts for {$monthName} have already been sanctioned.");
        }

        // Fetch Priests, Staff and Accountants
        $priests = DB::table('priests')
            ->join('users', 'priests.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'priests.priest_id', 'priests.monthly_salary as base_salary', 'priests.wallet_balance', 'users.role')
            ->get();

        $staff = DB::table('staff')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'staff.staff_id', 'staff.salary as base_salary', 'staff.wallet_balance', 'users.role')
            ->get();

        $accountants = DB::table('accountants')
            ->join('users', 'accountants.user_id', '=', 'users.id')
            ->select('users.id as user_id', 'accountants.salary as base_salary', DB::raw('0.00 as wallet_balance'), 'users.role')
            ->get();

        DB::beginTransaction();
        try {
            $sanctionedCount = 0;

            // Process Priests
            foreach ($priests as $p) {
                $walletAmount = $p->wallet_balance;
                $totalPaid = max(0.00, $p->base_salary + $walletAmount);

                DB::table('salary_payouts')->insert([
                    'user_id' => $p->user_id,
                    'role' => 'Priest',
                    'salary_month' => $salaryMonth,
                    'base_salary' => $p->base_salary,
                    'wallet_amount' => $walletAmount,
                    'total_paid' => $totalPaid,
                    'payment_date' => date('Y-m-d'),
                    'payment_status' => 'Paid',
                    'remarks' => "Salary sanctioned for {$monthName}. Wallet balance of " . Setting::get('currency_code', 'AUD') . " {$walletAmount} adjusted and cleared.",
                    'created_at' => now()
                ]);

                // Clear Priest Wallet
                DB::table('priests')->where('priest_id', $p->priest_id)->update([
                    'wallet_balance' => 0.00,
                    'updated_at' => now()
                ]);

                // Insert wallet clearance transaction log
                if ($walletAmount != 0) {
                    DB::table('priest_wallet_transactions')->insert([
                        'priest_id' => $p->priest_id,
                        'amount' => abs($walletAmount),
                        'transaction_type' => ($walletAmount > 0) ? 'Debit' : 'Credit',
                        'remarks' => "Wallet balance cleared to 0.00 upon salary sanction for {$monthName}",
                        'created_at' => now()
                    ]);
                }

                $sanctionedCount++;
            }

            // Process Staff
            foreach ($staff as $s) {
                $walletAmount = $s->wallet_balance;
                $totalPaid = max(0.00, $s->base_salary + $walletAmount);

                DB::table('salary_payouts')->insert([
                    'user_id' => $s->user_id,
                    'role' => 'Staff',
                    'salary_month' => $salaryMonth,
                    'base_salary' => $s->base_salary,
                    'wallet_amount' => $walletAmount,
                    'total_paid' => $totalPaid,
                    'payment_date' => date('Y-m-d'),
                    'payment_status' => 'Paid',
                    'remarks' => "Salary sanctioned for {$monthName}. Wallet balance of " . Setting::get('currency_code', 'AUD') . " {$walletAmount} adjusted and cleared.",
                    'created_at' => now()
                ]);

                DB::table('staff')->where('staff_id', $s->staff_id)->update([
                    'wallet_balance' => 0.00,
                    'updated_at' => now()
                ]);

                if ($walletAmount != 0) {
                    DB::table('staff_wallet_transactions')->insert([
                        'staff_id' => $s->staff_id,
                        'amount' => abs($walletAmount),
                        'transaction_type' => ($walletAmount > 0) ? 'Debit' : 'Credit',
                        'remarks' => "Wallet balance cleared to 0.00 upon salary sanction for {$monthName}",
                        'created_at' => now()
                    ]);
                }

                $sanctionedCount++;
            }

            // Process Accountants
            foreach ($accountants as $a) {
                DB::table('salary_payouts')->insert([
                    'user_id' => $a->user_id,
                    'role' => 'Accountant',
                    'salary_month' => $salaryMonth,
                    'base_salary' => $a->base_salary,
                    'wallet_amount' => 0.00,
                    'total_paid' => $a->base_salary,
                    'payment_date' => date('Y-m-d'),
                    'payment_status' => 'Paid',
                    'remarks' => "Salary sanctioned for {$monthName}.",
                    'created_at' => now()
                ]);

                $sanctionedCount++;
            }

            DB::commit();
            return redirect()->back()->with('success', "Successfully sanctioned salary payouts for {$sanctionedCount} employees for {$monthName}.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', "Failed to sanction payouts: " . $e->getMessage());
        }
    }

    /**
     * One combined view of every revenue-bearing table (Donations, Guest Donations,
     * Ticket Sales, Pooja Bookings) for the Reports page's Revenue tab — a KPI summary,
     * a 12-month trend, a breakdown by "avenue" (which of those four feeds the money
     * actually came through), and a few plain-English insights derived straight from
     * those same numbers. Deliberately its own standalone computation rather than reusing
     * OrganisationReportController's (donations/tickets only, no Pooja Bookings, and
     * built around a filtered single-page dashboard) — this is a simpler, unfiltered,
     * all-time overview for a different page.
     */
    private function revenueOverview(): array
    {
        $avenues = [
            'donations' => ['date' => 'donation_date', 'amount' => 'amount', 'label' => 'Donations'],
            'donations_without_logins' => ['date' => 'donation_date', 'amount' => 'amount', 'label' => 'Donations'],
            'ticket_orders' => ['date' => 'order_date', 'amount' => 'total_amount', 'label' => 'Ticket Sales'],
            'pooja_bookings' => ['date' => 'booking_date', 'amount' => 'total_amount', 'label' => 'Pooja Bookings'],
        ];

        $totalPaid = 0.0;
        $paidCount = 0;
        $byAvenue = [];
        foreach ($avenues as $table => $meta) {
            $query = DB::table($table)->where('payment_status', 'Paid');
            $sum = (float) (clone $query)->sum($meta['amount']);
            $count = (clone $query)->count();
            $totalPaid += $sum;
            $paidCount += $count;
            $byAvenue[$meta['label']] = ($byAvenue[$meta['label']] ?? 0) + $sum;
        }
        $avgTxn = $paidCount > 0 ? $totalPaid / $paidCount : 0.0;

        // This month vs last month, summed across every avenue.
        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = (clone $thisMonthStart)->subDay();
        $thisMonthPaid = 0.0;
        $lastMonthPaid = 0.0;
        foreach ($avenues as $table => $meta) {
            $thisMonthPaid += (float) DB::table($table)->where('payment_status', 'Paid')
                ->whereDate($meta['date'], '>=', $thisMonthStart)->sum($meta['amount']);
            $lastMonthPaid += (float) DB::table($table)->where('payment_status', 'Paid')
                ->whereDate($meta['date'], '>=', $lastMonthStart)->whereDate($meta['date'], '<=', $lastMonthEnd)->sum($meta['amount']);
        }
        $changePct = $lastMonthPaid > 0 ? round((($thisMonthPaid - $lastMonthPaid) / $lastMonthPaid) * 100, 1) : null;

        // 12-month trend, combined across every avenue into one line. Grouped in PHP
        // rather than via DATE_FORMAT() (MySQL-only, unlike the portable DATE() the
        // Transactions dashboard uses) — fetching the raw (date, amount) pairs keeps this
        // query portable across any DB driver.
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonthsNoOverflow($i)->format('Y-m'));
        $trend = $months->mapWithKeys(fn ($m) => [$m => 0.0]);
        $trendStart = now()->subMonthsNoOverflow(11)->startOfMonth();
        foreach ($avenues as $table => $meta) {
            $rows = DB::table($table)->where('payment_status', 'Paid')
                ->whereDate($meta['date'], '>=', $trendStart)
                ->select("{$meta['date']} as d", "{$meta['amount']} as amt")
                ->get();
            foreach ($rows as $row) {
                $ym = \Carbon\Carbon::parse($row->d)->format('Y-m');
                if (isset($trend[$ym])) {
                    $trend[$ym] += (float) $row->amt;
                }
            }
        }

        $insights = [];
        if ($changePct !== null) {
            $up = $changePct >= 0;
            $insights[] = [
                'tone' => $up ? 'positive' : 'warning',
                'icon' => $up ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow',
                'title' => ($up ? 'Revenue is up ' : 'Revenue is down ') . abs($changePct) . '% this month',
                'body' => 'Compared to ' . $lastMonthStart->format('F Y') . ' (' . $this->currencyLabel() . number_format($lastMonthPaid, 2) . ').',
            ];
        }
        $avenueTotal = array_sum($byAvenue);
        if ($avenueTotal > 0) {
            $sortedAvenues = $byAvenue;
            arsort($sortedAvenues);
            $topLabel = array_key_first($sortedAvenues);
            $share = round(($sortedAvenues[$topLabel] / $avenueTotal) * 100);
            $insights[] = [
                'tone' => 'info',
                'icon' => 'bi-trophy-fill',
                'title' => $topLabel . ' leads at ' . $share . '% of revenue',
                'body' => 'The dominant revenue avenue across every Paid transaction recorded.',
            ];
        }
        if (empty($insights)) {
            $insights[] = [
                'tone' => 'info',
                'icon' => 'bi-info-circle-fill',
                'title' => 'Not enough data yet for a trend callout',
                'body' => 'Insights appear here once there is enough Paid revenue recorded.',
            ];
        }

        return [
            'revenueStats' => [
                'total_paid' => $totalPaid,
                'paid_count' => $paidCount,
                'average_txn' => $avgTxn,
                'change_pct' => $changePct,
                'this_month' => $thisMonthPaid,
                'last_month' => $lastMonthPaid,
            ],
            'revenueTrendLabels' => $months->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y'))->values(),
            'revenueTrendValues' => $trend->values(),
            'revenueByAvenue' => collect($byAvenue)->map(fn ($v, $k) => ['label' => $k, 'total' => $v])->sortByDesc('total')->values(),
            'revenueInsights' => $insights,
        ];
    }

    private function currencyLabel(): string
    {
        return Setting::get('currency_code', 'AUD') . ' ';
    }

    /**
     * Display reporting dashboards.
     */
    public function reports(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'reports', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        // 1. Attendance report
        $priestAttendance = DB::table('attendances')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->select('attendances.date as attendance_date', DB::raw("COUNT(DISTINCT attendances.user_id) as present_count"), DB::raw("SUM(attendances.worked_minutes) / 60 as total_hours"))
            ->where('users.role', 'Priest')
            ->groupBy('attendances.date')
            ->orderBy('attendances.date', 'desc')
            ->limit(30)
            ->get();

        $staffAttendance = DB::table('attendances')
            ->join('users', 'attendances.user_id', '=', 'users.id')
            ->select('attendances.date as attendance_date', DB::raw("COUNT(DISTINCT attendances.user_id) as present_count"), DB::raw("SUM(attendances.worked_minutes) / 60 as total_hours"))
            ->where('users.role', 'Staff')
            ->groupBy('attendances.date')
            ->orderBy('attendances.date', 'desc')
            ->limit(30)
            ->get();

        // 2. Salary payout report
        // salary_payouts has no wallet_amount column of its own (total_paid is already
        // base_salary + that period's wallet contribution combined, per sanctionPayouts()
        // above) — the wallet share shown here is reconstructed as the difference.
        $salaryPayoutsSummary = DB::table('salary_payouts')
            ->select('salary_month', DB::raw("SUM(base_salary) as total_base"), DB::raw("SUM(total_paid - base_salary) as total_wallet"), DB::raw("SUM(total_paid) as total_paid"))
            ->groupBy('salary_month')
            ->orderBy('salary_month', 'desc')
            ->get();

        // 3. Wallet transactions report
        $priestWalletTx = DB::table('priest_wallet_transactions')
            ->select(DB::raw("DATE(created_at) as date"), DB::raw("SUM(CASE WHEN transaction_type='Credit' THEN amount ELSE 0 END) as credits"), DB::raw("SUM(CASE WHEN transaction_type='Debit' THEN amount ELSE 0 END) as debits"))
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        $staffWalletTx = DB::table('staff_wallet_transactions')
            ->select(DB::raw("DATE(created_at) as date"), DB::raw("SUM(CASE WHEN transaction_type='Credit' THEN amount ELSE 0 END) as credits"), DB::raw("SUM(CASE WHEN transaction_type='Debit' THEN amount ELSE 0 END) as debits"))
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        // 4. Pooja Completion report
        $poojaCompletionSummary = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->select('poojas.pooja_name', DB::raw("COUNT(*) as completed_count"), DB::raw("SUM(pooja_bookings.total_amount) as total_amount"))
            ->where('pooja_bookings.booking_status', 'Completed')
            ->groupBy('poojas.pooja_name')
            ->orderBy('completed_count', 'desc')
            ->get();

        // 5. Monthly Earnings report (from Bookings and Donations)
        $bookingsEarnings = DB::table('pooja_bookings')
            ->select(DB::raw("DATE_FORMAT(booking_date, '%Y-%m') as month"), DB::raw("SUM(total_amount) as total_earnings"))
            ->where('payment_status', 'Paid')
            ->groupBy('month')
            ->get();

        // Check dynamically if donations table exists
        $donationsEarnings = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('donations')) {
            $donationsEarnings = DB::table('donations')
                ->where('payment_status', 'Paid')
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw("SUM(amount) as total_earnings"))
                ->groupBy('month')
                ->get();
        }

        // 6. Events Report data
        $eventsCountSummary = DB::table('events')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        $recentEventsList = DB::table('events')
            ->orderBy('event_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit(20)
            ->get();

        // 7. Inventory Report data
        $inventoryLowStockList = [];
        $inventoryTransactionsList = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('inventories')) {
            $inventoryLowStockList = DB::table('inventories')
                ->whereRaw('quantity <= minimum_threshold')
                ->orderBy('quantity', 'asc')
                ->get();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('inventory_transactions')) {
            $inventoryTransactionsList = DB::table('inventory_transactions')
                ->join('inventories', 'inventory_transactions.item_id', '=', 'inventories.item_id')
                ->select('inventory_transactions.*', 'inventories.item_name', 'inventories.unit')
                ->orderBy('transaction_date', 'desc')
                ->orderBy('inventory_transactions.created_at', 'desc')
                ->limit(30)
                ->get();
        }

        return view('admin.reports', array_merge($this->revenueOverview(), compact(
            'priestAttendance',
            'staffAttendance',
            'salaryPayoutsSummary',
            'priestWalletTx',
            'staffWalletTx',
            'poojaCompletionSummary',
            'bookingsEarnings',
            'donationsEarnings',
            'eventsCountSummary',
            'recentEventsList',
            'inventoryLowStockList',
            'inventoryTransactionsList'
        )));
    }
}
