<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\RolePermission;
use App\Models\Event;
use App\Services\AuditLogService;
use App\Services\DonationReceiptService;
use App\Services\EventCoordinatorLevel;
use App\Services\LinklyEftService;
use App\Models\LinklyTransaction;
use App\Services\StripeConfigService;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class DonationController extends Controller
{
    /**
     * Display the donations management panel.
     */
    public function manageDonations(Request $request)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        $canAddDonation = RolePermission::can(session('active_role', $user->role), 'donations', 'add');
        $canEditDonation = RolePermission::can(session('active_role', $user->role), 'donations', 'edit');
        $canDeleteDonation = RolePermission::can(session('active_role', $user->role), 'donations', 'delete');

        // Fetch Devotee Donations
        $devoteeDonations = DB::table('donations')
            ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->leftJoin('events', 'donations.event_id', '=', 'events.event_id')
            ->select('donations.*', 'users.name as devotee_name', 'users.email', 'users.mobile', 'events.event_name')
            ->orderBy('donation_date', 'desc')
            ->orderBy('donations.created_at', 'desc')
            ->get();

        // Fetch Guest Donations
        $guestDonations = DB::table('donations_without_logins')
            ->leftJoin('events', 'donations_without_logins.event_id', '=', 'events.event_id')
            ->select('donations_without_logins.*', 'events.event_name')
            ->orderBy('donation_date', 'desc')
            ->orderBy('donations_without_logins.created_at', 'desc')
            ->get();

        // Calculate Totals — both only count settled donations. Bank/Cash sit as 'Pending'
        // until an admin approves them (see approveGuestDonation()/approveDevoteeDonation()),
        // and Stripe attempts that are still Pending or ended up Cancelled/Failed don't count
        // as money received either.
        $devoteeTotal = $devoteeDonations->where('payment_status', 'Paid')->sum('amount');
        $guestTotal = $guestDonations->where('payment_status', 'Paid')->sum('amount');
        $ehundiTotal = DB::table('ehundis')->sum('amount') ?: 0;
        $grandTotal = $devoteeTotal + $guestTotal + $ehundiTotal;

        // Normalize both donation sources into one shape so the admin panel can list them
        // in a single table/filter set, while $devoteeDonations/$guestDonations above are
        // still passed through as-is for their type-specific edit modals.
        $devoteeDonations->each(function ($d) {
            $d->donation_type = 'devotee';
            $d->display_id = 'DN' . str_pad($d->id, 5, '0', STR_PAD_LEFT);
            $d->display_name = $d->devotee_name;
            // For event-linked donations, $d->purpose holds the donor's selected donation
            // option(s) (e.g. "Annadanam Sponsorship, Pooja Sponsorship (x2)") — append it
            // instead of showing only the event name, so the chosen option is visible here.
            // display_option keeps just that raw value (blank for the generic placeholder)
            // as its own field, for the event-wise summary and the Excel export.
            $d->display_option = ($d->purpose && $d->purpose !== 'Event Donation') ? $d->purpose : '';
            $d->display_purpose = $d->event_name
                ? $d->event_name . ($d->display_option ? ' — ' . $d->display_option : '')
                : ($d->remarks ?: 'General Temple Fund');
        });

        $guestDonations->each(function ($g) {
            $g->donation_type = 'guest';
            $g->display_id = 'GD' . str_pad($g->id, 5, '0', STR_PAD_LEFT);
            $g->display_name = $g->donor_name;
            $g->display_option = ($g->purpose && $g->purpose !== 'Event Donation') ? $g->purpose : '';
            $g->display_purpose = $g->event_name
                ? $g->event_name . ($g->display_option ? ' — ' . $g->display_option : '')
                : ($g->purpose_details ?: $g->purpose);
        });

        $allDonations = $devoteeDonations->concat($guestDonations)
            ->sortByDesc(fn ($row) => $row->donation_date . ' ' . $row->created_at)
            ->values();

        // Event-wise donation tracking — totals per event, Paid-only (matches the totals
        // above), plus a Pending figure so admins can see what's still awaiting approval.
        $eventSummary = $allDonations
            ->filter(fn ($row) => !empty($row->event_id))
            ->groupBy('event_id')
            ->map(function ($rows) {
                $paid = $rows->where('payment_status', 'Paid');
                $pending = $rows->where('payment_status', 'Pending');
                return (object) [
                    'event_id' => $rows->first()->event_id,
                    'event_name' => $rows->first()->event_name,
                    'donation_count' => $rows->count(),
                    'paid_total' => $paid->sum('amount'),
                    'paid_count' => $paid->count(),
                    'pending_total' => $pending->sum('amount'),
                    'pending_count' => $pending->count(),
                ];
            })
            ->sortByDesc('paid_total')
            ->values();

        // Fetch e-Hundi Donations
        $ehundiDonations = DB::table('ehundis')
            ->leftJoin('devotees', 'ehundis.devotee_id', '=', 'devotees.devotee_id')
            ->leftJoin('users', 'devotees.user_id', '=', 'users.id')
            ->select('ehundis.*', 'users.name as devotee_name', 'users.email', 'users.mobile')
            ->orderBy('created_at', 'desc')
            ->get();

        // Fetch Devotees list for the dropdown
        $devotees = DB::table('devotees')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->select('devotees.devotee_id', 'users.name', 'users.email')
            ->orderBy('users.name', 'asc')
            ->get();

        // Fetch Events list for the donation-linking dropdown — eager-loaded with their
        // configured donation options (tiers) so the Add/Edit donation forms can offer the
        // same option checkboxes a donor would see on the event's own donation page.
        $events = Event::with('donationOptions')
            ->orderBy('event_date', 'desc')
            ->get();
        $eventOptionsByEventId = $events->keyBy('event_id')->map(fn ($e) => $e->donationOptions);

        // Which payment methods show up in the "Log Devotee/Guest Donation" forms — set in
        // System Settings > Donations & Payments. UPI is excluded by default.
        $enabledPaymentMethods = json_decode(Setting::get('enabled_payment_methods', '["Cash","Bank Transfer","Cheque"]'), true) ?: [];

        // Structured per-option breakdown for every donation, keyed "type:id" — lets the
        // per-event view show one column per configured donation option with the actual
        // amount the donor put toward it, instead of a single flattened purpose string.
        $donationSelections = DB::table('donation_selections')->get()
            ->groupBy(fn ($s) => $s->donation_type . ':' . $s->donation_id);

        // Pivoted rows per event: each donation row plus an option_id => amount map (only
        // populated for donations made with the new structured tier picker) and an
        // 'other_amount' catch-all for anything not attributable to a specific option
        // (older free-text donations, or a plain "Event Donation" with no tier chosen).
        $eventDonationRows = $eventOptionsByEventId->map(function ($options, $eventId) use ($allDonations, $donationSelections) {
            return $allDonations->where('event_id', $eventId)->values()->map(function ($row) use ($options, $donationSelections) {
                $selections = $donationSelections[$row->donation_type . ':' . $row->id] ?? collect();
                $optionAmounts = [];
                $matchedTotal = 0;
                foreach ($options as $opt) {
                    $amt = (float) $selections->where('event_donation_option_id', $opt->id)->sum('amount');
                    $optionAmounts[$opt->id] = $amt;
                    $matchedTotal += $amt;
                }
                $row->option_amounts = $optionAmounts;
                $row->other_amount = round($row->amount - $matchedTotal, 2);
                if ($row->other_amount < 0.01) {
                    $row->other_amount = 0;
                }
                return $row;
            });
        });

        // Pre-shaped for the Add Donation modals' JS tier picker — built here rather than
        // inline in the Blade @json() directive, since a nested multi-line closure inside
        // @json() confuses Blade's own paren/bracket matching at compile time.
        $eventDonationOptionsForJs = $events->mapWithKeys(function ($e) {
            return [$e->event_id => $e->donationOptions->map(function ($o) {
                return [
                    'id' => $o->id,
                    'label' => $o->label,
                    'amount' => $o->amount === null ? null : (float) $o->amount,
                    'allow_quantity' => (bool) $o->allow_quantity,
                ];
            })->values()];
        });

        return view('admin.manage-donations', compact(
            'devoteeDonations',
            'guestDonations',
            'allDonations',
            'eventSummary',
            'ehundiDonations',
            'devoteeTotal',
            'guestTotal',
            'ehundiTotal',
            'grandTotal',
            'devotees',
            'events',
            'eventOptionsByEventId',
            'eventDonationOptionsForJs',
            'donationSelections',
            'eventDonationRows',
            'enabledPaymentMethods',
            'canAddDonation',
            'canEditDonation',
            'canDeleteDonation'
        ));
    }

    /**
     * Export all devotee + guest donations as CSV (opens directly in Excel) — includes
     * the event name and the donor's selected donation option as separate columns.
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        $eventId = $request->filled('event_id') ? (int) $request->query('event_id') : null;

        $isCoordinatorForEvent = $user && $activeRole === 'Event Coordinator' && $eventId
            && DB::table('event_coordinators')->where('user_id', $user->id)->where('event_id', $eventId)->exists();

        if (!$user || !(RolePermission::can($activeRole, 'donations', 'view') || $isCoordinatorForEvent)) {
            abort(403, 'Unauthorized access.');
        }

        if ($eventId) {
            return $this->exportEventDonations($eventId);
        }

        $devoteeDonations = DB::table('donations')
            ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->leftJoin('events', 'donations.event_id', '=', 'events.event_id')
            ->select('donations.*', 'users.name as devotee_name', 'users.email', 'users.mobile', 'events.event_name')
            ->orderBy('donation_date', 'desc')
            ->get()
            ->map(function ($d) {
                $d->donation_type = 'Devotee';
                $d->display_id = 'DN' . str_pad($d->id, 5, '0', STR_PAD_LEFT);
                $d->display_name = $d->devotee_name;
                $d->display_option = ($d->purpose && $d->purpose !== 'Event Donation') ? $d->purpose : '';
                return $d;
            });

        $guestDonations = DB::table('donations_without_logins')
            ->leftJoin('events', 'donations_without_logins.event_id', '=', 'events.event_id')
            ->select('donations_without_logins.*', 'events.event_name')
            ->orderBy('donation_date', 'desc')
            ->get()
            ->map(function ($g) {
                $g->donation_type = 'Guest';
                $g->display_id = 'GD' . str_pad($g->id, 5, '0', STR_PAD_LEFT);
                $g->display_name = $g->donor_name;
                $g->display_option = ($g->purpose && $g->purpose !== 'Event Donation') ? $g->purpose : '';
                return $g;
            });

        $rows = $devoteeDonations->concat($guestDonations)
            ->sortByDesc(fn ($row) => $row->donation_date . ' ' . $row->created_at)
            ->values();

        $filename = 'donations-export-' . now()->format('Y-m-d') . '.csv';

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM so Excel detects UTF-8 correctly instead of mangling non-ASCII names.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Donation ID', 'Type', 'Name', 'Email', 'Mobile', 'Amount', 'Payment Method', 'Status', 'Event', 'Donation Option', 'Dedication / Remarks', 'Transaction ID', 'Donation Date']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->display_id,
                    $row->donation_type,
                    $row->display_name,
                    $row->email ?? '',
                    $row->mobile ?? '',
                    $row->amount,
                    $row->payment_method,
                    $row->payment_status,
                    $row->event_name ?? '',
                    $row->display_option,
                    $row->donation_type === 'Guest' ? ($row->purpose_details ?? '') : ($row->remarks ?? ''),
                    $row->transaction_id,
                    $row->donation_date,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export one event's donations as CSV, with a column per that event's configured
     * donation option (matching the Event Donations tab's pivoted table) instead of the
     * flat "Donation Option" text column the general export uses.
     */
    private function exportEventDonations(int $eventId)
    {
        $breakdown = \App\Services\EventDonationBreakdown::forEvent($eventId);
        if (!$breakdown['event']) {
            abort(404, 'Event not found.');
        }

        return $this->renderEventDonationsWorkbook($breakdown['event'], $breakdown['options'], $breakdown['rows']);
    }

    /**
     * Builds the actual .xlsx workbook for exportEventDonations() — a temple-name banner,
     * report title/generated-at line, a styled header row, the donation rows with one
     * column per configured option, and a bold totals row with real SUM() formulas.
     */
    private function renderEventDonationsWorkbook(Event $event, $options, $rows)
    {
        $templeName = Setting::get('temple_name', 'Temple Donation Report');
        $currency = Setting::get('currency_code', 'AUD');

        $headers = array_merge(
            ['Donation ID', 'Type', 'Name', 'Email', 'Mobile'],
            $options->pluck('label')->all(),
            [
                'Other', 'Total Amount', 'Payment Method', 'Payment Status',
                'Transaction ID', 'Bank Name', 'Bank Account No', 'Bank IFSC', 'Bank Branch',
                'Dedication / Remarks', 'Donation Date', 'Recorded At',
            ]
        );
        // Fixed, deliberately narrow widths (in Excel "characters") instead of auto-size —
        // auto-size lets one long email or dedication note balloon the whole sheet. Text
        // columns stay just wide enough to identify at a glance (full value is always one
        // click away in the formula bar); amount columns only ever hold a short number.
        $widths = array_merge(
            [12, 9, 18, 20, 13],
            array_fill(0, $options->count(), 13),
            [10, 13, 13, 11, 16, 13, 13, 10, 12, 16, 12, 15]
        );
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // Amount columns are: the option columns, then Other, then Total Amount.
        $amountStartCol = 6;
        $amountEndCol = $amountStartCol + $options->count() + 1;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $this->buildEventDonationsSummarySheet($summarySheet, $event, $options, $rows, $templeName, $currency);

        $sheet = $spreadsheet->createSheet();
        $safeTitle = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $event->event_name);
        $sheet->setTitle(\Illuminate\Support\Str::limit($safeTitle, 28, ''));

        $sheet->setCellValue('A1', $templeName);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setName('Calibri');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue('A2', 'Event Donations Report — ' . $event->event_name);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('B8863A');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Generated on ' . now()->format('d M Y, h:i A') . ' · ' . $rows->count() . ' donation(s)');
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('999999');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $headerRow = 5;
        foreach ($headers as $i => $label) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('B8863A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        $rowIndex = $headerRow + 1;
        $firstDataRow = $rowIndex;
        foreach ($rows as $row) {
            $isGuest = $row->donation_type === 'guest';
            $data = [
                $row->display_id,
                $isGuest ? 'Guest' : 'Devotee',
                $row->display_name,
                $row->email ?? '',
                $row->mobile ?? '',
            ];
            foreach ($options as $opt) {
                $data[] = $row->option_amounts[$opt->id] > 0 ? (float) $row->option_amounts[$opt->id] : null;
            }
            $data[] = $row->other_amount > 0 ? (float) $row->other_amount : null;
            $data[] = (float) $row->amount;
            $data[] = $row->payment_method;
            $data[] = $row->payment_status;
            $data[] = $row->transaction_id;
            $data[] = $isGuest ? ($row->bank_name ?? '') : '';
            $data[] = $isGuest ? ($row->bank_account_no ?? '') : '';
            $data[] = $isGuest ? ($row->bank_ifsc ?? '') : '';
            $data[] = $isGuest ? ($row->bank_branch ?? '') : '';
            $data[] = $isGuest ? ($row->purpose_details ?? '') : ($row->remarks ?? '');
            $data[] = $row->donation_date;
            $data[] = $row->created_at;

            foreach ($data as $i => $value) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue("{$col}{$rowIndex}", $value);
            }

            // Zebra striping so a long row of numbers stays easy to track across the sheet.
            if (($rowIndex - $firstDataRow) % 2 === 1) {
                $sheet->getStyle("A{$rowIndex}:{$lastCol}{$rowIndex}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FBF8F3');
            }
            $rowIndex++;
        }
        $lastDataRow = max($rowIndex - 1, $firstDataRow);

        // Totals row — real SUM() formulas over the amount columns, not just a static number.
        $sheet->setCellValue("A{$rowIndex}", 'TOTAL');
        $sheet->mergeCells("A{$rowIndex}:E{$rowIndex}");
        for ($c = $amountStartCol; $c <= $amountEndCol; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->setCellValue("{$colLetter}{$rowIndex}", "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow})");
        }
        $totalsRange = "A{$rowIndex}:{$lastCol}{$rowIndex}";
        $sheet->getStyle($totalsRange)->getFont()->setBold(true);
        $sheet->getStyle($totalsRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FDF6EA');
        $sheet->getStyle($totalsRange)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        // Currency formatting (with the temple's currency code) and right-alignment on
        // every amount column, header through totals row.
        for ($c = $amountStartCol; $c <= $amountEndCol; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $amountRange = "{$colLetter}{$firstDataRow}:{$colLetter}{$rowIndex}";
            $sheet->getStyle($amountRange)->getNumberFormat()->setFormatCode('"' . $currency . '" #,##0.00');
            $sheet->getStyle($amountRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }

        // Wrap text in every data cell so anything longer than its column (a long email,
        // dedication note, etc.) wraps onto extra lines within the cell instead of visually
        // spilling into the next column — Excel only clips that overflow when the neighbour
        // isn't empty, and several columns here legitimately are, so wrapping is the
        // reliable fix rather than relying on that.
        $sheet->getStyle("A{$firstDataRow}:{$lastCol}{$lastDataRow}")->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        // Type / Payment Status / Donation Date read better centered than left-aligned.
        // Their position shifts with how many option columns an event has, so look them up
        // by header label rather than a fixed letter.
        foreach (['Type', 'Payment Status', 'Donation Date'] as $label) {
            $idx = array_search($label, $headers, true);
            if ($idx === false) {
                continue;
            }
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // Light borders around the whole table and fixed column widths (see $widths above).
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$rowIndex}")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('E0D8C8');
        foreach ($widths as $i => $width) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($colLetter)->setWidth($width);
        }

        // Freeze both the header rows AND the first three identity columns (ID/Type/Name),
        // so scrolling right to check an amount never loses track of who the row belongs to.
        $sheet->freezePane('D' . $firstDataRow);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'event-donations-' . \Illuminate\Support\Str::slug($event->event_name) . '-' . now()->format('Y-m-d') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Builds the "Summary" sheet (opened first) for the event donations workbook: an
     * at-a-glance overview (paid/pending totals and counts), a payment-method breakdown, and
     * a per-donation-option category breakdown — both based on Paid donations only, since
     * Pending amounts aren't real money received yet. Pending is called out separately in red
     * and deliberately excluded from every total, per how the temple actually reconciles cash.
     */
    private function buildEventDonationsSummarySheet($sheet, Event $event, $options, $rows, string $templeName, string $currency): void
    {
        $amountFormat = '"' . $currency . '" #,##0.00';
        $gold = 'B8863A';
        $teal = '0F9D6A';
        $red = 'C0392B';
        $lastCol = 'E';

        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(3);
        $sheet->getColumnDimension('D')->setWidth(26);
        $sheet->getColumnDimension('E')->setWidth(18);

        $sheet->setCellValue('A1', $templeName);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);

        $sheet->setCellValue('A2', 'Donation Balance Summary — ' . $event->event_name);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($gold);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Generated on ' . now()->format('d M Y, h:i A'));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('999999');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $paidRows = $rows->where('payment_status', 'Paid');
        $pendingRows = $rows->where('payment_status', 'Pending');
        $excludedRows = $rows->whereIn('payment_status', ['Cancelled', 'Failed']);
        $paidTotal = $paidRows->sum('amount');
        $pendingTotal = $pendingRows->sum('amount');
        $excludedTotal = $excludedRows->sum('amount');

        // --- Overview stat cards (row 5 = labels, row 6 = values) ---
        $cards = [
            ['col' => 'A', 'label' => 'PAID TOTAL', 'value' => $paidTotal, 'isAmount' => true, 'color' => $teal],
            ['col' => 'B', 'label' => 'PENDING TOTAL', 'value' => $pendingTotal, 'isAmount' => true, 'color' => $red],
            ['col' => 'D', 'label' => 'PAID DONATIONS', 'value' => $paidRows->count(), 'isAmount' => false, 'color' => '333333'],
            ['col' => 'E', 'label' => 'TOTAL DONATIONS', 'value' => $rows->count(), 'isAmount' => false, 'color' => '333333'],
        ];
        foreach ($cards as $card) {
            $sheet->setCellValue("{$card['col']}5", $card['label']);
            $sheet->setCellValue("{$card['col']}6", $card['value']);
            $sheet->getStyle("{$card['col']}5")->getFont()->setBold(true)->setSize(8)->getColor()->setRGB('857A6B');
            $sheet->getStyle("{$card['col']}6")->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($card['color']);
            if ($card['isAmount']) {
                $sheet->getStyle("{$card['col']}6")->getNumberFormat()->setFormatCode($amountFormat);
            }
            $sheet->getStyle("{$card['col']}5:{$card['col']}6")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FBF8F3');
            $sheet->getStyle("{$card['col']}5:{$card['col']}6")->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('E0D8C8');
        }
        $sheet->getRowDimension(6)->setRowHeight(24);

        // --- Payment method breakdown (Paid only), left column ---
        $normalizeMethod = function (?string $method) {
            $method = trim((string) $method);
            if (in_array($method, ['Bank', 'Bank Transfer'], true)) {
                return 'Bank Transfer';
            }
            return $method !== '' ? $method : 'Unspecified';
        };
        $preferredOrder = ['Cash', 'Bank Transfer', 'Stripe', 'UPI', 'Cheque'];
        $methodTotals = $paidRows->groupBy(fn ($r) => $normalizeMethod($r->payment_method))->map->sum('amount');
        $methodTotals = $methodTotals->sortBy(function ($total, $method) use ($preferredOrder) {
            $pos = array_search($method, $preferredOrder, true);
            return $pos === false ? 99 : $pos;
        });

        $sectionRow = 8;
        $sheet->setCellValue("A{$sectionRow}", 'PAYMENT METHOD BREAKDOWN (PAID)');
        $sheet->mergeCells("A{$sectionRow}:B{$sectionRow}");
        $sheet->getStyle("A{$sectionRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$sectionRow}:B{$sectionRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($gold);
        $sheet->getStyle("A{$sectionRow}:B{$sectionRow}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($sectionRow)->setRowHeight(20);

        $r = $sectionRow + 1;
        foreach ($methodTotals as $method => $total) {
            $sheet->setCellValue("A{$r}", $method);
            $sheet->setCellValue("B{$r}", (float) $total);
            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode($amountFormat);
            $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $r++;
        }
        $sheet->setCellValue("A{$r}", 'Total Received (Paid)');
        $sheet->setCellValue("B{$r}", (float) $paidTotal);
        $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
        $sheet->getStyle("A{$r}:B{$r}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode($amountFormat);
        $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $r += 2;

        $sheet->setCellValue("A{$r}", 'Pending (awaiting payment)');
        $sheet->setCellValue("B{$r}", (float) $pendingTotal);
        $sheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true)->getColor()->setRGB($red);
        $sheet->getStyle("B{$r}")->getFont()->setBold(true)->getColor()->setRGB($red);
        $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode($amountFormat);
        $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $r++;
        $sheet->setCellValue("A{$r}", 'Not included in totals above');
        $sheet->getStyle("A{$r}")->getFont()->setItalic(true)->setSize(8)->getColor()->setRGB($red);
        $r++;

        if ($excludedTotal > 0) {
            $sheet->setCellValue("A{$r}", 'Cancelled / Failed (excluded)');
            $sheet->setCellValue("B{$r}", (float) $excludedTotal);
            $sheet->getStyle("A{$r}:B{$r}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('999999');
            $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode($amountFormat);
            $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        }
        $paymentSectionEnd = $r;

        // --- Category breakdown (Paid only, one row per donation option + Other), right column ---
        $categoryTotals = [];
        foreach ($options as $opt) {
            $categoryTotals[$opt->label] = $paidRows->sum(fn ($row) => $row->option_amounts[$opt->id] ?? 0);
        }
        $otherTotal = $paidRows->sum('other_amount');
        if ($otherTotal > 0 || empty($categoryTotals)) {
            $categoryTotals['Other'] = $otherTotal;
        }

        $sheet->setCellValue("D{$sectionRow}", 'DONATION CATEGORY BREAKDOWN (PAID)');
        $sheet->mergeCells("D{$sectionRow}:E{$sectionRow}");
        $sheet->getStyle("D{$sectionRow}")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("D{$sectionRow}:E{$sectionRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB($teal);
        $sheet->getStyle("D{$sectionRow}:E{$sectionRow}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $r = $sectionRow + 1;
        foreach ($categoryTotals as $label => $total) {
            $sheet->setCellValue("D{$r}", $label);
            $sheet->setCellValue("E{$r}", (float) $total);
            $sheet->getStyle("D{$r}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($amountFormat);
            $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $r++;
        }
        $sheet->setCellValue("D{$r}", 'Total (Paid)');
        $sheet->setCellValue("E{$r}", (float) $paidTotal);
        $sheet->getStyle("D{$r}:E{$r}")->getFont()->setBold(true);
        $sheet->getStyle("D{$r}:E{$r}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($amountFormat);
        $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $categorySectionEnd = $r;

        // --- Grand total, spanning both columns below whichever section is taller ---
        $grandTotalRow = max($paymentSectionEnd, $categorySectionEnd) + 2;
        $sheet->setCellValue("A{$grandTotalRow}", 'GRAND TOTAL RAISED (PAID)');
        $sheet->mergeCells("A{$grandTotalRow}:D{$grandTotalRow}");
        $sheet->setCellValue("E{$grandTotalRow}", (float) $paidTotal);
        $sheet->getStyle("A{$grandTotalRow}:E{$grandTotalRow}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$grandTotalRow}:E{$grandTotalRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FDF6EA');
        $sheet->getStyle("A{$grandTotalRow}:E{$grandTotalRow}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK)->getColor()->setRGB($gold);
        $sheet->getStyle("A{$grandTotalRow}:E{$grandTotalRow}")->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK)->getColor()->setRGB($gold);
        $sheet->getStyle("E{$grandTotalRow}")->getNumberFormat()->setFormatCode($amountFormat);
        $sheet->getStyle("E{$grandTotalRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($grandTotalRow)->setRowHeight(24);

        $sheet->getStyle("A{$sectionRow}:E{$grandTotalRow}")->getFont()->setName('Calibri')->setSize(10);
        $sheet->setSelectedCell('A1');
    }

    /**
     * Whether the given user/active-role may record a new donation, optionally against a
     * specific event. The normal RolePermission grid covers Admin/Committee/Accountant as
     * today; an Event Coordinator has no grid entries at all (see RolePermission::roles())
     * and is instead authorized per-event via the event_coordinators pivot — only for the
     * specific event they're recording against (at least 'entry' level), never a blank/
     * general-fund donation.
     */
    private function canRecordDonation($user, ?string $activeRole, $eventId): bool
    {
        if (RolePermission::can($activeRole, 'donations', 'add')) {
            return true;
        }

        if ($activeRole === 'Event Coordinator' && $eventId) {
            return EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of((int) $eventId, $user->id), 'entry');
        }

        return false;
    }

    /**
     * Whether the given user/active-role may manage (edit/approve/resend/check-status) a
     * donation belonging to $eventId — same per-event coordinator carve-out as
     * canRecordDonation(), reusable for actions on an existing donation. $minLevel lets a
     * caller require 'admin' for something more sensitive than the 'entry' default, though
     * nothing currently needs that.
     */
    private function canManageDonationForEvent($user, ?string $activeRole, $eventId, string $minLevel = 'entry'): bool
    {
        if (RolePermission::can($activeRole, 'donations', 'edit')) {
            return true;
        }

        if ($activeRole === 'Event Coordinator' && $eventId) {
            return EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of((int) $eventId, $user->id), $minLevel);
        }

        return false;
    }

    /**
     * Persist the per-option breakdown of a tiered donation (from the "selections_json"
     * hidden field built by the public donate-form and the admin Add Donation modals), so
     * the per-event donations view can show one column per configured option instead of
     * just a flattened purpose string. Silently no-ops for non-tiered donations.
     */
    private function saveDonationSelections(string $donationType, int $donationId, ?string $selectionsJson): void
    {
        if (!$selectionsJson) {
            return;
        }

        $selections = json_decode($selectionsJson, true);
        if (!is_array($selections) || empty($selections)) {
            return;
        }

        foreach ($selections as $selection) {
            if (empty($selection['label']) || !isset($selection['amount']) || (float) $selection['amount'] <= 0) {
                continue;
            }

            DB::table('donation_selections')->insert([
                'donation_type' => $donationType,
                'donation_id' => $donationId,
                'event_donation_option_id' => $selection['option_id'] ?? null,
                'option_label' => $selection['label'],
                'quantity' => $selection['quantity'] ?? null,
                'amount' => $selection['amount'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Store a manually recorded Devotee donation.
     */
    public function storeDevoteeDonation(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !$this->canRecordDonation($user, $activeRole, $request->input('event_id'))) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'devotee_id' => 'required|exists:devotees,devotee_id',
            'event_id' => 'nullable|exists:events,event_id',
            'amount' => 'required|numeric|min:1',
            'payment_mode' => 'required|string|in:Cash,UPI,Bank Transfer,Cheque,EFT Terminal',
            'transaction_id' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:2000',
            'donation_date' => 'required|date',
            'selections_json' => 'nullable|string',
            'payment_status' => 'nullable|string|in:Paid,Pending',
            // Set only when payment_mode is EFT Terminal — links this donation back to its
            // Linkly accreditation ledger row (see linkLedgerToDonation()).
            'linkly_session_id' => 'nullable|string|max:64',
        ]);

        try {
            $donationId = DB::table('donations')->insertGetId([
                'devotee_id' => $validated['devotee_id'],
                'event_id' => $validated['event_id'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_mode'],
                'payment_status' => $validated['payment_status'] ?? 'Paid',
                'transaction_id' => ($validated['transaction_id'] ?? '') !== '' ? $validated['transaction_id'] : 'OFFLINE-' . strtoupper(uniqid()),
                // 'purpose' carries the selected event donation option(s) when the admin
                // picked from an event's tiers; 'remarks' stays the free-text note either way.
                'purpose' => $validated['purpose'] ?? null,
                'remarks' => $validated['remarks'] ?? 'Manually recorded donation',
                'donation_date' => $validated['donation_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->saveDonationSelections('devotee', $donationId, $validated['selections_json'] ?? null);
            $this->linkLedgerToDonation($validated['linkly_session_id'] ?? null, 'devotee', $donationId);

            $devoteeUser = DB::table('devotees')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('devotees.devotee_id', $validated['devotee_id'])
                ->select('users.name', 'users.email', 'users.mobile')
                ->first();

            $receiptPayload = [
                'donor_name' => $devoteeUser->name ?? 'Devotee',
                'donor_email' => $devoteeUser->email ?? null,
                'donor_mobile' => $devoteeUser->mobile ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_mode'],
                'purpose' => $validated['remarks'] ?? 'General Temple Fund',
                'event_id' => $validated['event_id'] ?? null,
                'donation_date' => $validated['donation_date'],
                'transaction_id' => $validated['transaction_id'] ?? null,
                'receipt_number' => DonationReceiptService::receiptNumber('D', $donationId),
            ];
            if (($validated['payment_status'] ?? 'Paid') === 'Pending') {
                DonationReceiptService::sendPendingNotice($receiptPayload);
            } else {
                DonationReceiptService::send($receiptPayload);
            }

            AuditLogService::log(
                "Recorded devotee donation of {$validated['amount']} ({$validated['payment_mode']})",
                null,
                $validated['event_id'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Devotee donation recorded successfully.']);
            }
            return redirect()->back()->with('success', 'Devotee donation recorded successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to record donation: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to record donation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Drives a purchase transaction on the paired EFT terminal — called by the POS page and
     * the console's Quick Entry *before* storeGuestDonation(), so a donation is only ever
     * recorded once the terminal actually confirms the card was charged. A declined/failed
     * transaction never reaches storeGuestDonation() at all.
     */
    /**
     * The URI Linkly posts async notifications back to for a session — {{type}} is reliably
     * substituted by Linkly, but {{sessionId}} is not (confirmed against real webhook
     * traffic — it can arrive as the literal, unsubstituted string), so the URL never carries
     * it at all; linklyWebhook() resolves the session id from the postback body instead. Uses
     * url() rather than route() because route() URL-encodes parameters, which would mangle
     * the "{{"/"}}" braces into something Linkly's template substitution wouldn't recognise.
     */
    private function eftNotificationUri(): string
    {
        return url('/admin/eft/webhook/{{type}}');
    }

    public function startEftCharge(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !$this->canRecordDonation($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            // A per-attempt idempotency key the browser generates once and reuses for every
            // retry of the *same* checkout attempt (double-clicking Pay, or resuming after a
            // refresh/network drop) — see the lookup below. A brand new donation attempt
            // always gets a brand new one from the browser.
            'client_ref' => 'required|string|max:64',
            'event_id' => 'nullable|exists:events,event_id',
        ]);

        // Resume-in-place: if this exact attempt already has a non-final Linkly session
        // (started moments ago, then the browser refreshed or the operator clicked Pay
        // again before the first click's request even finished), hand back that same
        // session instead of starting a second one on the terminal. This is the mechanism
        // behind both "protect against double-clicking Pay" and safe recovery after a
        // browser/network interruption (Linkly Core Payments requirement) — never blindly
        // start a new payment for a checkout attempt that might already be in flight.
        $existing = LinklyTransaction::where('client_ref', $validated['client_ref'])
            ->where('txn_type', 'purchase')
            ->whereNotIn('status', LinklyTransaction::TERMINAL_STATUSES)
            ->latest('id')
            ->first();
        if ($existing && $existing->linkly_session_id) {
            return response()->json([
                'success' => true,
                'message' => 'Resuming existing transaction.',
                'session_id' => $existing->linkly_session_id,
                'resumed' => true,
            ]);
        }

        $txnRef = 'EFT' . now()->format('mdHis') . rand(10, 99);

        $result = LinklyEftService::startPurchase(
            (float) $validated['amount'],
            $txnRef,
            Setting::get('currency_code', 'AUD'),
            $this->eftNotificationUri(),
            $user->id,
            $user->name
        );

        if ($result['success']) {
            LinklyTransaction::create([
                'pos_txn_ref' => $txnRef,
                'client_ref' => $validated['client_ref'],
                'linkly_session_id' => $result['session_id'],
                'txn_type' => 'purchase',
                'event_id' => $validated['event_id'] ?? null,
                'amount' => $validated['amount'],
                'currency_code' => Setting::get('currency_code', 'AUD'),
                'status' => 'initiated',
                'initiated_by' => $user->id,
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function pollEftCharge(Request $request, string $sessionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !$this->canRecordDonation($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        $result = LinklyEftService::pollTransaction($sessionId);
        $this->syncLedgerFromPoll($sessionId, $result);
        $this->markDonationRefundedIfJustApproved($sessionId, $result);

        // A transaction that's been neither approved/declined/cancelled nor failed for far
        // longer than Linkly's own ~3-minute transaction window is genuinely UNKNOWN, not
        // safely assumable as declined — Linkly may have charged the card without us ever
        // hearing back. Reporting it as 'unknown' (rather than endlessly 'in_progress' or,
        // worse, guessing 'declined') stops the browser's auto-poll loop while making clear
        // a fresh Purchase must not be started for the same order without checking first.
        if (!$result['done']) {
            $txn = LinklyTransaction::where('linkly_session_id', $sessionId)->first();
            if ($txn && !$txn->isTerminal() && $txn->created_at->diffInSeconds(now()) > 200) {
                $txn->update(['status' => 'unknown']);
                $result['payment_status'] = 'unknown';
                $result['done'] = true;
                $result['success'] = null;
                $result['message'] = 'No final result was received from the terminal in time. Check the terminal and the customer\'s bank statement before attempting another charge.';
            }
        }

        return response()->json($result);
    }

    /**
     * Keeps the accreditation ledger (linkly_transactions) in step with what pollTransaction()
     * just found out — called from every poll rather than only once, since a poll can be the
     * first time a terminal outcome is seen. Never overwrites a row that's already reached a
     * terminal status (approved/declined/cancelled/failed): once Linkly has given its final
     * word, a later stray/duplicate poll response must not un-finalise it.
     */
    private function syncLedgerFromPoll(string $sessionId, array $result): void
    {
        $txn = LinklyTransaction::where('linkly_session_id', $sessionId)->first();
        if (!$txn || $txn->isTerminal()) {
            return;
        }

        if (!$result['done']) {
            if ($txn->status !== 'in_progress') {
                $txn->update(['status' => 'in_progress']);
            }
            return;
        }

        $txn->update([
            'status' => $result['payment_status'],
            'response_code' => $result['response_code'] ?? null,
            'response_text' => $result['message'] ?? null,
            'auth_code' => $result['auth_code'] ?? null,
            'rrn' => $result['rrn'] ?? null,
        ]);
    }

    /**
     * Sends a CANCEL key-press to the terminal for an in-progress async transaction — wired
     * to the POS modal's Cancel button so it actually reaches the terminal (e.g. while it's
     * waiting for a card tap or PIN) instead of only dismissing the modal locally. Same
     * authorization as starting/polling the charge; the eventual approved/declined/cancelled
     * outcome still only ever comes from pollEftCharge()'s authoritative GET.
     */
    public function cancelEftCharge(Request $request, string $sessionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !$this->canRecordDonation($user, $activeRole, $request->input('event_id'))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        return response()->json(LinklyEftService::cancel($sessionId));
    }

    /**
     * Links a just-created donation record back to the Linkly ledger row its EFT Terminal
     * payment started — a no-op for every other payment method (no session id is ever sent).
     * This is what makes a refund possible later (payment_method + ledger status are checked
     * from the donation side) and what the accreditation transaction view can use to show
     * "which donation did this Linkly transaction end up recording".
     */
    private function linkLedgerToDonation(?string $sessionId, string $donationType, int $donationId): void
    {
        if (!$sessionId) {
            return;
        }

        LinklyTransaction::where('linkly_session_id', $sessionId)->update([
            'donation_type' => $donationType,
            'donation_id' => $donationId,
        ]);
    }

    /**
     * "event-coordinator-admin" level for a specific event — Admin/Committee (via the
     * existing "events" edit grant) or an Event Coordinator at 'admin' level for this event.
     * Gates every EFTPOS management action (pairing, Logon, Refund, Reprint) the same way
     * EventConsoleController::show() gates the console's own Settings/Coordinators/Logs
     * panes, since these are exactly that same admin tier of the event console — never a
     * pos-entry/general-entry level user, per Linkly's Core Payments refund-authorisation
     * requirement.
     */
    private function canManageEftForEvent($user, ?string $activeRole, $eventId): bool
    {
        if ($activeRole === 'Admin' || RolePermission::can($activeRole, 'events', 'edit')) {
            return true;
        }

        if ($activeRole === 'Event Coordinator' && $eventId) {
            return EventCoordinatorLevel::atLeast(EventCoordinatorLevel::of((int) $eventId, $user->id), 'admin');
        }

        return false;
    }

    /**
     * Starts a refund for a completed EFT Terminal purchase. Gated to event-admin level,
     * never pos-entry/general-entry — Core Payments requires refunds to be protected from
     * unauthorised use, enforced here server-side regardless of what any UI shows. Runs
     * through the same async start+poll+modal flow as a purchase (see startEftCharge()/
     * pollEftCharge()) since a refund is a real terminal transaction, not a database edit.
     */
    public function refundEftCharge(Request $request, $eventId, int $transactionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $original = LinklyTransaction::find($transactionId);
        if (!$original || $original->txn_type !== 'purchase') {
            return response()->json(['success' => false, 'message' => 'Original transaction not found.'], 404);
        }

        if (!$this->canManageEftForEvent($user, $activeRole, $original->event_id)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
        }

        if ($original->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Only an approved purchase can be refunded.'], 422);
        }

        // Duplicate-refund protection: block a second attempt while one is already in
        // flight or has already succeeded for this same purchase.
        $alreadyRefunded = LinklyTransaction::where('original_transaction_id', $original->id)
            ->whereIn('status', ['initiated', 'in_progress', 'approved'])
            ->exists();
        if ($alreadyRefunded) {
            return response()->json(['success' => false, 'message' => 'This transaction has already been refunded, or a refund is already in progress.'], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . (float) $original->amount,
            'client_ref' => 'required|string|max:64',
        ]);

        $refundTxnRef = 'RFD' . now()->format('mdHis') . rand(10, 99);

        $result = LinklyEftService::startRefund(
            (float) $validated['amount'],
            $refundTxnRef,
            $original->pos_txn_ref,
            $original->currency_code ?? Setting::get('currency_code', 'AUD'),
            $this->eftNotificationUri(),
            $user->id,
            $user->name
        );

        if ($result['success']) {
            LinklyTransaction::create([
                'pos_txn_ref' => $refundTxnRef,
                'client_ref' => $validated['client_ref'],
                'linkly_session_id' => $result['session_id'],
                'txn_type' => 'refund',
                'event_id' => $original->event_id,
                'donation_type' => $original->donation_type,
                'donation_id' => $original->donation_id,
                'amount' => $validated['amount'],
                'currency_code' => $original->currency_code,
                'status' => 'initiated',
                'original_transaction_id' => $original->id,
                'initiated_by' => $user->id,
                'authorised_by' => $user->id,
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Marks the underlying donation row 'Refunded' once a refund transaction is confirmed
     * approved — called from pollEftCharge() right after syncLedgerFromPoll(), so the
     * temple's own totals (which only ever sum payment_status = 'Paid') stop counting it
     * without needing a separate admin step. Only ever runs on the authoritative polled
     * result, same "never decide from DisplayText" rule as every other status change here.
     */
    private function markDonationRefundedIfJustApproved(string $sessionId, array $result): void
    {
        if (!($result['done'] && $result['success'])) {
            return;
        }

        $txn = LinklyTransaction::where('linkly_session_id', $sessionId)->where('txn_type', 'refund')->first();
        if (!$txn || !$txn->donation_type || !$txn->donation_id) {
            return;
        }

        $table = $txn->donation_type === 'devotee' ? 'donations' : 'donations_without_logins';
        DB::table($table)->where('id', $txn->donation_id)->update(['payment_status' => 'Refunded', 'updated_at' => now()]);
    }

    /**
     * Logs on to the paired terminal — confirms it's reachable/configured without moving any
     * money. Same event-admin authorization as Refund/pairing. Plain POST-redirect (not the
     * async modal flow) since a Logon resolves immediately, matching the console's existing
     * Settings/Coordinators forms.
     */
    public function logonLinkly(Request $request, int $eventId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$this->canManageEftForEvent($user, $activeRole, $eventId)) {
            abort(403, 'Unauthorized access.');
        }

        $result = LinklyEftService::logon();

        LinklyTransaction::create([
            'pos_txn_ref' => 'LGN' . now()->format('mdHis') . rand(10, 99),
            'txn_type' => 'logon',
            'event_id' => $eventId,
            'status' => $result['success'] ? 'approved' : 'failed',
            'response_text' => $result['message'],
            'initiated_by' => $user->id,
            'authorised_by' => $user->id,
        ]);

        AuditLogService::log('Linkly terminal Logon: ' . $result['message'], null, $eventId);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Reprints the acquirer/EFTPOS receipt for a completed session. Same event-admin
     * authorization as Refund/Logon/pairing.
     */
    public function reprintEftReceipt(Request $request, int $eventId, string $sessionId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$this->canManageEftForEvent($user, $activeRole, $eventId)) {
            abort(403, 'Unauthorized access.');
        }

        $result = LinklyEftService::reprintReceipt($sessionId);
        AuditLogService::log('Linkly receipt reprint requested for session ' . $sessionId . ': ' . $result['message'], null, $eventId);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Repairs the (single, shared) EFT terminal from inside an event-admin's own console —
     * same underlying pairing as LinklyController::pair() (the Settings-page, Admin-only
     * entry point, left unchanged), just reachable by an event-admin coordinator too and
     * returning back to the console instead of Settings. Pairing is a whole-terminal action
     * (there is one physical/virtual PIN pad, not one per event), so this intentionally
     * doesn't scope anything to $eventId beyond deciding who's allowed to trigger it.
     */
    public function pairEftFromConsole(Request $request, int $eventId)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$this->canManageEftForEvent($user, $activeRole, $eventId)) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'pair_code' => 'required|string|max:10',
        ]);

        $result = LinklyEftService::pair($validated['pair_code']);
        AuditLogService::log('EFT terminal pairing ' . ($result['success'] ? 'succeeded' : 'failed') . ' (from event console)', null, $eventId);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Receives Linkly's postback notifications for an in-progress async transaction — the
     * PIN pad's live display prompts ("ENTER PIN", etc.) — so pollEftCharge() has something
     * to hand the browser. Not behind the usual admin auth (Linkly itself calls this, not a
     * logged-in browser) — authenticated instead by the bearer token startEftCharge()
     * generated for this specific session, and exempted from CSRF in bootstrap/app.php the
     * same way the Stripe webhook is.
     *
     * Two confirmed-against-real-traffic quirks this works around:
     * - Linkly's own {{sessionId}} URI template placeholder does not reliably get
     *   substituted (it can arrive as the literal string "{{sessionId}}"), unlike {{type}}
     *   which does — so the session id is always read from the postback body's own
     *   "SessionId" field instead of trusted from the URL.
     * - The postback body uses PascalCase field names ("Response", "DisplayText",
     *   "CancelKeyFlag"), unlike the lowercase camelCase used by the direct GET/POST API
     *   responses handled in LinklyEftService::pollTransaction() — these are two genuinely
     *   different casings from the same provider, not a typo.
     */
    public function linklyWebhook(Request $request)
    {
        $sessionId = $request->input('SessionId') ?? $request->input('sessionId');
        $responseType = $request->input('ResponseType') ?? $request->input('responseType');

        if (!$sessionId || !$responseType) {
            return response()->json(['message' => 'Malformed notification.'], 400);
        }

        if (!LinklyEftService::verifyWebhookToken($sessionId, $request->bearerToken())) {
            return response()->json(['message' => 'Invalid or expired session.'], 401);
        }

        if ($responseType === 'display') {
            $displayResponse = $request->input('Response') ?? $request->input('response') ?? [];
            $lines = $displayResponse['DisplayText'] ?? $displayResponse['displayText'] ?? [];
            $flags = [
                'cancel' => (bool) ($displayResponse['CancelKeyFlag'] ?? $displayResponse['cancelKeyFlag'] ?? false),
                'ok' => (bool) ($displayResponse['OKKeyFlag'] ?? $displayResponse['okKeyFlag'] ?? false),
                'yes' => (bool) ($displayResponse['AcceptYesKeyFlag'] ?? $displayResponse['acceptYesKeyFlag'] ?? false),
                'no' => (bool) ($displayResponse['DeclineNoKeyFlag'] ?? $displayResponse['declineNoKeyFlag'] ?? false),
                'authorise' => (bool) ($displayResponse['AuthoriseKeyFlag'] ?? $displayResponse['authoriseKeyFlag'] ?? false),
            ];
            $cleanedLines = LinklyEftService::cleanDisplayLines(is_array($lines) ? $lines : []);
            LinklyEftService::recordDisplay($sessionId, is_array($lines) ? $lines : [], $flags);

            // Deliberately logs only the cleaned display text, never the raw postback body —
            // a "transaction"/"receipt" postback (unlike "display") can carry card data
            // (PAN, track2), so nothing from this webhook is ever logged wholesale.
            Log::info('Linkly display notification', ['session_id' => $sessionId, 'display' => $cleanedLines]);
        } elseif ($responseType === 'receipt') {
            // The merchant/customer receipt text for a completed transaction — kept only for
            // the accreditation pane's Reprint/receipt evidence, never used to decide
            // payment_status. recordReceipt() masks any run of digits long enough to be a PAN
            // before anything is cached, and nothing here is ever logged wholesale (same
            // "never log raw postback body" rule as above).
            $receiptResponse = $request->input('Response') ?? $request->input('response') ?? [];
            $lines = $receiptResponse['ReceiptText'] ?? $receiptResponse['receiptText'] ?? [];
            LinklyEftService::recordReceipt($sessionId, is_array($lines) ? $lines : []);
            Log::info('Linkly receipt notification received', ['session_id' => $sessionId]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Store a manually recorded Guest donation.
     */
    public function storeGuestDonation(Request $request)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);
        if (!$user || !$this->canRecordDonation($user, $activeRole, $request->input('event_id'))) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // Same per-event email/mobile requirement as the public donation form
        // (storePublic()) — an event admin can require either independently for every guest
        // donation recorded here, whether from the full console's Quick Entry or the POS page.
        $lockedEvent = $request->filled('event_id') ? Event::find($request->input('event_id')) : null;
        $requireEmail = $lockedEvent && $lockedEvent->require_donor_email;
        $requireMobile = $lockedEvent && $lockedEvent->require_donor_mobile;

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => ($requireEmail ? 'required' : 'nullable') . '|email|max:255',
            'mobile' => ($requireMobile ? 'required' : 'nullable') . '|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100',
            'purpose_details' => 'nullable|string|max:2000',
            'payment_method' => 'required|string|in:Cash,UPI,Bank,EFT Terminal',
            'transaction_id' => 'nullable|string|max:100',
            // Bank/cheque details are a convenience field, not a requirement — an admin
            // recording a donation from a bank statement or receipt may not have every
            // field to hand, so none of these are mandatory even when payment_method=Bank.
            'bank_name' => 'nullable|string|max:100',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'bank_branch' => 'nullable|string|max:100',
            'donation_date' => 'required|date',
            'selections_json' => 'nullable|string',
            'payment_status' => 'nullable|string|in:Paid,Pending',
            // Set only when payment_method is EFT Terminal — links this donation back to its
            // Linkly accreditation ledger row (see linkLedgerToDonation()).
            'linkly_session_id' => 'nullable|string|max:64',
        ]);

        try {
            $donationId = DB::table('donations_without_logins')->insertGetId([
                'donor_name' => $validated['donor_name'],
                'event_id' => $validated['event_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'],
                'purpose_details' => $validated['purpose_details'] ?? null,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_status'] ?? 'Paid',
                'transaction_id' => ($validated['transaction_id'] ?? '') !== '' ? $validated['transaction_id'] : 'GUEST-' . strtoupper(uniqid()),
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_no' => $validated['bank_account_no'] ?? null,
                'bank_ifsc' => $validated['bank_ifsc'] ?? null,
                'bank_branch' => $validated['bank_branch'] ?? null,
                'donation_date' => $validated['donation_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->saveDonationSelections('guest', $donationId, $validated['selections_json'] ?? null);
            $this->linkLedgerToDonation($validated['linkly_session_id'] ?? null, 'guest', $donationId);

            $receiptPayload = [
                'donor_name' => $validated['donor_name'],
                'donor_email' => $validated['email'] ?? null,
                'donor_mobile' => $validated['mobile'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'purpose' => $validated['purpose_details'] ?? $validated['purpose'],
                'event_id' => $validated['event_id'] ?? null,
                'donation_date' => $validated['donation_date'],
                'transaction_id' => $validated['transaction_id'] ?? null,
                'receipt_number' => DonationReceiptService::receiptNumber('G', $donationId),
            ];
            if (($validated['payment_status'] ?? 'Paid') === 'Pending') {
                DonationReceiptService::sendPendingNotice($receiptPayload);
            } else {
                DonationReceiptService::send($receiptPayload);
            }

            AuditLogService::log(
                "Recorded guest donation of {$validated['amount']} from {$validated['donor_name']} ({$validated['payment_method']})",
                null,
                $validated['event_id'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Guest donation recorded successfully.']);
            }
            return redirect()->back()->with('success', 'Guest donation recorded successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to record guest donation: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to record guest donation: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update a devotee-linked donation record.
     */
    public function updateDevoteeDonation(Request $request, $id)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $donation = DB::table('donations')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if (!$user || !$this->canManageDonationForEvent($user, $activeRole, $donation->event_id)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,event_id',
            'amount' => 'required|numeric|min:1',
            'payment_mode' => 'required|string|in:Cash,UPI,Bank Transfer,Cheque,EFT Terminal,Stripe',
            'payment_status' => 'required|string|in:Paid,Pending,Cancelled,Failed',
            'transaction_id' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:2000',
            'donation_date' => 'required|date',
        ]);

        DB::table('donations')->where('id', $id)->update([
            'event_id' => $validated['event_id'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_mode'],
            'payment_status' => $validated['payment_status'],
            'transaction_id' => $validated['transaction_id'] ?? $donation->transaction_id,
            'purpose' => $validated['purpose'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'donation_date' => $validated['donation_date'],
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Devotee donation updated successfully.');
    }

    /**
     * Delete a devotee-linked donation record.
     */
    public function deleteDevoteeDonation($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        DB::table('donations')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Devotee donation deleted successfully.');
    }

    /**
     * Update a guest donation record.
     */
    public function updateGuestDonation(Request $request, $id)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if (!$user || !$this->canManageDonationForEvent($user, $activeRole, $donation->event_id)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100',
            'purpose_details' => 'nullable|string|max:2000',
            'payment_method' => 'required|string|in:Cash,UPI,Bank,EFT Terminal,Stripe',
            'payment_status' => 'required|string|in:Paid,Pending,Cancelled,Failed',
            'transaction_id' => 'nullable|string|max:100',
            'donation_date' => 'required|date',
        ]);

        DB::table('donations_without_logins')->where('id', $id)->update([
            'donor_name' => $validated['donor_name'],
            'event_id' => $validated['event_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'purpose_details' => $validated['purpose_details'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => $validated['payment_status'],
            'transaction_id' => $validated['transaction_id'] ?? $donation->transaction_id,
            'donation_date' => $validated['donation_date'],
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Guest donation updated successfully.');
    }

    /**
     * Delete a guest donation record.
     */
    public function deleteGuestDonation($id)
    {
        $user = Auth::user();
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'delete')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        DB::table('donations_without_logins')->where('id', $id)->delete();

        return redirect()->back()->with('success', 'Guest donation deleted successfully.');
    }

    /**
     * Manually resend the donation receipt email for an existing donation record
     * (devotee-linked or guest) — e.g. if the original send failed or the donor
     * asks for another copy.
     */
    public function resendReceipt(Request $request, $type, $id)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        if ($type === 'devotee') {
            $donation = DB::table('donations')
                ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('donations.id', $id)
                ->select('donations.*', 'users.name as donor_name', 'users.email', 'users.mobile')
                ->first();
        } elseif ($type === 'guest') {
            $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        } else {
            return redirect()->back()->with('error', 'Invalid donation type.');
        }

        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if (!$user || !$this->canManageDonationForEvent($user, $activeRole, $donation->event_id)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if (!$donation->email) {
            $label = $type === 'devotee' ? 'This devotee' : 'This donor';
            return redirect()->back()->with('error', "{$label} has no email address on file — cannot resend a receipt.");
        }

        $purpose = $type === 'devotee' ? ($donation->remarks ?? 'General Temple Fund') : ($donation->purpose_details ?? $donation->purpose);

        // Resending a receipt should only ever reach the donor themselves, never CC the
        // event/coordinator list again — that already happened on the original send.
        DonationReceiptService::send([
            'donor_name' => $donation->donor_name,
            'donor_email' => $donation->email,
            'donor_mobile' => $donation->mobile,
            'amount' => $donation->amount,
            'payment_method' => $donation->payment_method,
            'purpose' => $purpose,
            'event_id' => $donation->event_id,
            'donation_date' => $donation->donation_date,
            'transaction_id' => $donation->transaction_id,
            'receipt_number' => DonationReceiptService::receiptNumber($type === 'devotee' ? 'D' : 'G', $donation->id),
        ], false);

        AuditLogService::log("Resent {$type} donation receipt to {$donation->donor_name}", null, $donation->event_id);

        return redirect()->back()->with('success', 'Receipt email resent successfully.');
    }

    /**
     * Manually re-check a Stripe donation's real status against Stripe — for a donation
     * stuck as 'Pending' (donor abandoned the checkout tab without completing or clicking
     * back) or 'Cancelled', this asks Stripe directly rather than waiting on a webhook that
     * will never come. See StripeReconciliationService for how the result is resolved.
     */
    public function checkStripeStatus($type, $id)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $table = $type === 'devotee' ? 'donations' : 'donations_without_logins';
        $row = DB::table($table)->where('id', $id)->first();

        if (!$row) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if (!$user || !$this->canManageDonationForEvent($user, $activeRole, $row->event_id)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($row->payment_method !== 'Stripe' || !in_array($row->payment_status, ['Pending', 'Cancelled'])) {
            return redirect()->back()->with('error', 'This action only applies to Stripe donations that are Pending or Cancelled.');
        }

        $result = \App\Services\StripeReconciliationService::reconcile($table, $row);

        return redirect()->back()->with($result['outcome'] === 'error' ? 'error' : 'success', $result['message']);
    }

    /**
     * Approve/verify a self-service guest donation that was submitted as Bank Transfer or
     * Cash at Temple — these sit as 'Pending' until an admin confirms the money was actually
     * received, unlike Online Payment (Stripe) which is auto-verified via the payment gateway.
     * Approving flips it to 'Paid' (so it counts in the totals) and sends the donation
     * receipt for the first time, since it wasn't sent while still unconfirmed.
     */
    public function approveGuestDonation($id)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if (!$user || !$this->canManageDonationForEvent($user, $activeRole, $donation->event_id)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($donation->payment_status !== 'Pending') {
            return redirect()->back()->with('error', 'Only pending donations can be approved.');
        }

        DB::table('donations_without_logins')->where('id', $id)->update([
            'payment_status' => 'Paid',
            'updated_at' => now(),
        ]);

        DonationReceiptService::send([
            'donor_name' => $donation->donor_name,
            'donor_email' => $donation->email,
            'donor_mobile' => $donation->mobile,
            'amount' => $donation->amount,
            'payment_method' => $donation->payment_method,
            'purpose' => $donation->purpose_details ?? $donation->purpose,
            'event_id' => $donation->event_id,
            'donation_date' => $donation->donation_date,
            'transaction_id' => $donation->transaction_id,
            'receipt_number' => DonationReceiptService::receiptNumber('G', $donation->id),
        ]);

        AuditLogService::log(
            "Approved guest donation of {$donation->amount} from {$donation->donor_name}",
            null,
            $donation->event_id
        );

        return redirect()->back()->with('success', 'Donation approved and marked as received.');
    }

    /**
     * Approve/verify a devotee's own Bank Transfer or Cash at Temple donation — the same
     * confirm-before-it-counts rule as approveGuestDonation(), just for the devotee-linked
     * 'donations' table instead of the guest table.
     */
    public function approveDevoteeDonation($id)
    {
        $user = Auth::user();
        $activeRole = session('active_role', $user->role ?? null);

        $donation = DB::table('donations')
            ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->where('donations.id', $id)
            ->select('donations.*', 'users.name as donor_name', 'users.email', 'users.mobile')
            ->first();

        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

        if (!$user || !$this->canManageDonationForEvent($user, $activeRole, $donation->event_id)) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($donation->payment_status !== 'Pending') {
            return redirect()->back()->with('error', 'Only pending donations can be approved.');
        }

        DB::table('donations')->where('id', $id)->update([
            'payment_status' => 'Paid',
            'updated_at' => now(),
        ]);

        DonationReceiptService::send([
            'donor_name' => $donation->donor_name,
            'donor_email' => $donation->email,
            'donor_mobile' => $donation->mobile,
            'amount' => $donation->amount,
            'payment_method' => $donation->payment_method,
            'purpose' => $donation->remarks ?: $donation->purpose,
            'event_id' => $donation->event_id,
            'donation_date' => $donation->donation_date,
            'transaction_id' => $donation->transaction_id,
            'receipt_number' => DonationReceiptService::receiptNumber('D', $donation->id),
        ]);

        AuditLogService::log(
            "Approved devotee donation of {$donation->amount} from {$donation->donor_name}",
            null,
            $donation->event_id
        );

        return redirect()->back()->with('success', 'Donation approved and marked as received.');
    }

    /**
     * The logged-in devotee's own donation page — same Bank/Cash/Online tabs used on the
     * homepage and event pages, so the experience matches everywhere donations are made.
     */
    public function showDevoteeDonatePage(Request $request)
    {
        $user = Auth::user();
        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        if (!$devotee) {
            return redirect()->route('devotee.dashboard')->with('error', 'Devotee profile not found.');
        }

        $temple = Setting::templeBranding();
        $stripeEnabled = (bool) Setting::get('stripe_enabled', true);
        $events = DB::table('events')->where('status', 'Upcoming')->orderBy('event_date', 'asc')->get();

        return view('devotee.donate', compact('temple', 'stripeEnabled', 'events', 'user'));
    }

    /**
     * Store a donation made by a logged-in devotee — same Bank/Cash/Online options as the
     * public donation form, but linked to the devotee's own account (devotee_id) rather than
     * recorded as a guest. Bank/Cash sit as 'Pending' until an admin approves them, same as
     * the guest flow; Online Payment goes through the same Stripe Checkout as everywhere else.
     */
    public function storeDevoteeSelfDonation(Request $request)
    {
        $user = Auth::user();
        $devotee = DB::table('devotees')->where('user_id', $user->id)->first();
        if (!$devotee) {
            return redirect()->route('devotee.dashboard')->with('error', 'Devotee profile not found.');
        }

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,event_id',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:255',
            'purpose_details' => 'nullable|string|max:2000',
            'payment_method' => 'required|in:Bank,Cash,Stripe',
            'selections_json' => 'nullable|string',
        ]);

        if ($validated['payment_method'] === 'Stripe') {
            $validated['email'] = $user->email;
            return $this->startStripeCheckout($validated, $devotee->devotee_id);
        }

        // Same 'Pending until approved' rule as the guest donation flow — see storePublic().
        $transactionId = strtoupper($validated['payment_method']) . '-' . strtoupper(uniqid());

        $donationId = DB::table('donations')->insertGetId([
            'devotee_id' => $devotee->devotee_id,
            'event_id' => $validated['event_id'] ?? null,
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'Pending',
            'remarks' => $validated['purpose_details'] ?? null,
            'transaction_id' => $transactionId,
            'donation_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->saveDonationSelections('devotee', $donationId, $validated['selections_json'] ?? null);

        DonationReceiptService::sendPendingNotice([
            'donor_name' => $user->name,
            'donor_email' => $user->email,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'purpose' => $validated['purpose_details'] ?? $validated['purpose'],
            'event_id' => $validated['event_id'] ?? null,
            'donation_date' => now()->toDateString(),
            'transaction_id' => $transactionId,
        ]);

        $currency = Setting::get('currency_code', 'AUD');
        $message = 'Thank you! Your donation of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' has been recorded and is pending confirmation.';

        if ($validated['payment_method'] === 'Bank') {
            $message = 'Thank you! Please complete your bank transfer using the details shown. Your donation will be confirmed once we verify the transfer, and a receipt will be emailed to you then.';
        } elseif ($validated['payment_method'] === 'Cash') {
            $message = 'Thank you! Your pledge has been recorded. Please hand your offering to the temple counter — a receipt will be emailed to you once it is confirmed.';
        }

        return redirect()->route('devotee.dashboard')->with('success', $message);
    }

    /**
     * Store a public donation (no login required) — general fund or tied to a specific event.
     * Covers all three donor-facing options: Bank Transfer, Cash at Temple, and Online Payment.
     */
    public function storePublic(Request $request)
    {
        // Some events (e.g. those needing to trace every donor for a receipt/audit trail)
        // require an email and/or mobile — each independently, via the event's own
        // require_donor_email / require_donor_mobile settings.
        $lockedEvent = $request->filled('event_id') ? Event::find($request->input('event_id')) : null;
        $requireEmail = $lockedEvent && $lockedEvent->require_donor_email;
        $requireMobile = $lockedEvent && $lockedEvent->require_donor_mobile;

        // Defense in depth: the public page already hides the form for a closed event, but a
        // direct POST (stale tab, replay) must not be allowed to record a donation either.
        if ($lockedEvent && $lockedEvent->isClosedForDonations()) {
            return redirect()->back()->with('error', 'This event is now closed and is no longer accepting donations.');
        }

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => ($requireEmail ? 'required' : 'nullable') . '|email|max:255',
            'mobile' => ($requireMobile ? 'required' : 'nullable') . '|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:255',
            'purpose_details' => 'nullable|string|max:2000',
            'payment_method' => 'required|in:Bank,Cash,Stripe',
            'transaction_id' => 'nullable|string|max:100',
            'selections_json' => 'nullable|string',
        ]);

        if ($validated['payment_method'] === 'Stripe') {
            return $this->startStripeCheckout($validated);
        }

        // Bank Transfer and Cash at Temple are self-reported by the donor — nobody has
        // actually confirmed the money changed hands yet, so these sit as 'Pending' until
        // an admin approves them (see approveGuestDonation()). Online Payment (Stripe) is
        // the only method that's auto-verified, since Stripe's own API confirms the charge.
        $transactionId = $validated['transaction_id'] ?? strtoupper($validated['payment_method']) . '-' . strtoupper(uniqid());

        $donationId = DB::table('donations_without_logins')->insertGetId([
            'donor_name' => $validated['donor_name'],
            'event_id' => $validated['event_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'amount' => $validated['amount'],
            'purpose' => $validated['purpose'],
            'purpose_details' => $validated['purpose_details'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'Pending',
            'transaction_id' => $transactionId,
            'bank_name' => null,
            'bank_account_no' => null,
            'bank_ifsc' => null,
            'bank_branch' => null,
            'donation_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->saveDonationSelections('guest', $donationId, $validated['selections_json'] ?? null);

        DonationReceiptService::sendPendingNotice([
            'donor_name' => $validated['donor_name'],
            'donor_email' => $validated['email'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'purpose' => $validated['purpose_details'] ?? $validated['purpose'],
            'event_id' => $validated['event_id'] ?? null,
            'donation_date' => now()->toDateString(),
            'transaction_id' => $transactionId,
        ]);

        $currency = Setting::get('currency_code', 'AUD');
        $message = 'Thank you! Your donation of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' has been recorded and is pending confirmation.';

        if ($validated['payment_method'] === 'Bank') {
            $message = 'Thank you! Please complete your bank transfer using the details shown. Your donation of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' will be confirmed once we verify the transfer, and a receipt will be emailed to you then.';
        } elseif ($validated['payment_method'] === 'Cash') {
            $message = 'Thank you! Your pledge of ' . $currency . ' ' . number_format($validated['amount'], 2) . ' has been recorded. Please hand your offering to the temple counter — a receipt will be emailed to you once it is confirmed.';
        }

        // The official receipt is not sent here — Bank/Cash donations are still unverified
        // at this point. It goes out from approveGuestDonation() once an admin confirms it;
        // sendPendingNotice() above only sent the "here's what to do next" reminder.

        return redirect()->back()->with('success_donation', $message);
    }

    /**
     * Create a Stripe Checkout Session for an online donation and redirect the donor to it.
     * A 'Pending' row is inserted first (keyed by the Checkout Session id) so the success
     * callback and the webhook both have something to flip to 'Paid' — neither one inserts.
     *
     * Pass $devoteeId when this is a logged-in devotee's own donation — the pending row goes
     * into the devotee-linked 'donations' table instead of the guest 'donations_without_logins'
     * table, so it's correctly attributed to their account from the start.
     */
    private function startStripeCheckout(array $validated, ?int $devoteeId = null)
    {
        if (!Setting::get('stripe_enabled', true) || !StripeConfigService::secret()) {
            return redirect()->back()->with('error', 'Online payment is currently unavailable. Please choose Bank Transfer or Cash at Temple instead.')->withInput();
        }

        $table = $devoteeId ? 'donations' : 'donations_without_logins';

        if ($devoteeId) {
            $donationId = DB::table('donations')->insertGetId([
                'devotee_id' => $devoteeId,
                'event_id' => $validated['event_id'] ?? null,
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'],
                'payment_method' => 'Stripe',
                'payment_status' => 'Pending',
                'remarks' => $validated['purpose_details'] ?? null,
                'transaction_id' => null,
                'donation_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->saveDonationSelections('devotee', $donationId, $validated['selections_json'] ?? null);
        } else {
            $donationId = DB::table('donations_without_logins')->insertGetId([
                'donor_name' => $validated['donor_name'],
                'event_id' => $validated['event_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'],
                'purpose_details' => $validated['purpose_details'] ?? null,
                'payment_method' => 'Stripe',
                'payment_status' => 'Pending',
                'transaction_id' => null,
                'bank_name' => null,
                'bank_account_no' => null,
                'bank_ifsc' => null,
                'bank_branch' => null,
                'donation_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->saveDonationSelections('guest', $donationId, $validated['selections_json'] ?? null);
        }

        $currency = strtolower(Setting::get('currency_code', 'AUD'));
        $templeName = Setting::get('temple_name', 'Temple Donation');
        $heroImageUrl = url(Setting::get('temple_hero_image', '/images/temple_landing.jpg'));

        $eventName = null;
        if (!empty($validated['event_id'])) {
            $eventName = Event::find($validated['event_id'])?->event_name;
        }

        $descriptionParts = array_filter([$eventName, $validated['purpose_details'] ?? null]);
        $description = $descriptionParts
            ? implode(' — ', $descriptionParts)
            : 'Thank you for supporting ' . $templeName . '.';

        try {
            $stripe = new StripeClient(StripeConfigService::secret());
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'customer_email' => $validated['email'] ?? null,
                'submit_type' => 'donate',
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => (int) round($validated['amount'] * 100),
                        'product_data' => [
                            'name' => $validated['purpose'] . ' — ' . $templeName,
                            'description' => $description,
                            'images' => [$heroImageUrl],
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'custom_text' => [
                    'submit' => [
                        'message' => 'Your generosity supports ' . $templeName . ' and the community it serves. A receipt will be emailed to you once confirmed.',
                    ],
                ],
                'success_url' => route('donate.stripe.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('donate.stripe.cancel', ['donation' => $donationId, 'table' => $table]),
                'metadata' => ['donation_id' => (string) $donationId, 'table' => $table],
            ]);
        } catch (\Exception $e) {
            DB::table($table)->where('id', $donationId)->update(['payment_status' => 'Failed', 'updated_at' => now()]);
            Log::error('Stripe checkout session creation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to start the online payment right now. Please try Bank Transfer or Cash at Temple instead.')->withInput();
        }

        DB::table($table)->where('id', $donationId)->update([
            'transaction_id' => $session->id,
            'updated_at' => now(),
        ]);

        return redirect($session->url);
    }

    /**
     * Find a Stripe-initiated donation by its Checkout Session id across both donation
     * tables (guest and devotee-linked) — returns [tableName, row] or [null, null].
     */
    private function findStripeDonationByTransactionId(string $sessionId): array
    {
        $donation = DB::table('donations_without_logins')->where('transaction_id', $sessionId)->first();
        if ($donation) {
            return ['donations_without_logins', $donation];
        }

        $donation = DB::table('donations')->where('transaction_id', $sessionId)->first();
        if ($donation) {
            return ['donations', $donation];
        }

        return [null, null];
    }

    /**
     * Stripe redirects the donor here after a successful Checkout Session.
     */
    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return redirect()->route('home')->with('error', 'Missing payment session.');
        }

        [$table, $donation] = $this->findStripeDonationByTransactionId($sessionId);
        if (!$donation) {
            return redirect()->route('home')->with('error', 'We could not find that donation.');
        }

        // Devotee-linked donations land back on their own dashboard; guest donations
        // land back on the homepage, matching where each donation started.
        $redirectRoute = $table === 'donations' ? 'devotee.dashboard' : 'home';
        $flashKey = $table === 'donations' ? 'success' : 'success_donation';

        // The webhook may have already confirmed it — treat that as equally valid.
        if ($donation->payment_status !== 'Paid') {
            try {
                $stripe = new StripeClient(StripeConfigService::secret());
                $session = $stripe->checkout->sessions->retrieve($sessionId);
            } catch (\Exception $e) {
                Log::error('Stripe session verification failed: ' . $e->getMessage());
                return redirect()->route($redirectRoute)->with('error', 'Could not verify your payment. Please contact the temple office if you were charged.');
            }

            if ($session->payment_status === 'paid') {
                // Guarded by payment_status != 'Paid' so that if the webhook already flipped
                // this donation to Paid a moment earlier, this update (and therefore the
                // receipt email below) is skipped — avoids sending the receipt twice.
                $justConfirmed = DB::table($table)
                    ->where('id', $donation->id)
                    ->where('payment_status', '!=', 'Paid')
                    ->update(['payment_status' => 'Paid', 'updated_at' => now()]);

                if ($justConfirmed) {
                    DonationReceiptService::send(DonationReceiptService::payloadForRow($table, $donation));
                }
            } else {
                return redirect()->route($redirectRoute)->with('error', 'Payment was not completed.');
            }
        }

        $currency = Setting::get('currency_code', 'AUD');
        return redirect()->route($redirectRoute)->with($flashKey, 'Thank you! Your donation of ' . $currency . ' ' . number_format($donation->amount, 2) . ' was received successfully via Stripe.');
    }

    /**
     * Stripe redirects the donor here if they abandon Checkout without paying.
     */
    public function stripeCancel(Request $request)
    {
        $donationId = $request->query('donation');
        $table = $request->query('table') === 'donations' ? 'donations' : 'donations_without_logins';

        if ($donationId) {
            DB::table($table)
                ->where('id', $donationId)
                ->where('payment_status', 'Pending')
                ->update(['payment_status' => 'Cancelled', 'updated_at' => now()]);
        }

        $redirectRoute = $table === 'donations' ? 'devotee.dashboard' : 'home';

        return redirect()->route($redirectRoute)->with('error', 'Your donation was cancelled — no payment was taken.');
    }

    /**
     * Authoritative confirmation from Stripe, independent of whether the donor's browser
     * ever made it back to the success page. Excluded from CSRF verification (see
     * bootstrap/app.php) since Stripe posts here directly, not via a browser form.
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = StripeConfigService::webhookSecret();

        try {
            if ($webhookSecret) {
                $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } else {
                $event = json_decode($payload);
                Log::warning('Stripe webhook received without STRIPE_WEBHOOK_SECRET configured — signature was not verified.');
            }
        } catch (SignatureVerificationException | \UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        $sessionId = $event->data->object->id ?? null;
        if (($event->type ?? null) === 'checkout.session.completed' && $sessionId) {
            [$table, $donation] = $this->findStripeDonationByTransactionId($sessionId);

            if ($table && $donation) {
                // Guarded by payment_status != 'Paid' so that if the donor's own browser
                // already confirmed via stripeSuccess(), this update (and the receipt email)
                // is skipped.
                $justConfirmed = DB::table($table)
                    ->where('id', $donation->id)
                    ->where('payment_status', '!=', 'Paid')
                    ->update(['payment_status' => 'Paid', 'updated_at' => now()]);

                if ($justConfirmed) {
                    DonationReceiptService::send(DonationReceiptService::payloadForRow($table, $donation));
                }
            }
        }

        return response('OK', 200);
    }
}
