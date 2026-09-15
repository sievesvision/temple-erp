<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use App\Models\RolePermission;
use App\Models\Event;
use App\Services\DonationReceiptService;
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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'view')) {
            abort(403, 'Unauthorized access.');
        }

        if ($request->filled('event_id')) {
            return $this->exportEventDonations((int) $request->query('event_id'));
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
        $sheet = $spreadsheet->getActiveSheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
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

        $filename = 'event-donations-' . \Illuminate\Support\Str::slug($event->event_name) . '-' . now()->format('Y-m-d') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Whether the given user/active-role may record a new donation, optionally against a
     * specific event. The normal RolePermission grid covers Admin/Committee/Accountant as
     * today; an Event Coordinator has no grid entries at all (see RolePermission::roles())
     * and is instead authorized per-event via the event_coordinators pivot — only for the
     * specific event they're recording against, never a blank/general-fund donation.
     */
    private function canRecordDonation($user, ?string $activeRole, $eventId): bool
    {
        if (RolePermission::can($activeRole, 'donations', 'add')) {
            return true;
        }

        if ($activeRole === 'Event Coordinator' && $eventId) {
            return DB::table('event_coordinators')->where('user_id', $user->id)->where('event_id', $eventId)->exists();
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
            'payment_mode' => 'required|string|in:Cash,UPI,Bank Transfer,Cheque',
            'transaction_id' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
            'donation_date' => 'required|date',
            'selections_json' => 'nullable|string',
        ]);

        try {
            $donationId = DB::table('donations')->insertGetId([
                'devotee_id' => $validated['devotee_id'],
                'event_id' => $validated['event_id'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_mode'],
                'payment_status' => 'Paid',
                'transaction_id' => $validated['transaction_id'] ?? 'OFFLINE-' . strtoupper(uniqid()),
                // 'purpose' carries the selected event donation option(s) when the admin
                // picked from an event's tiers; 'remarks' stays the free-text note either way.
                'purpose' => $validated['purpose'] ?? null,
                'remarks' => $validated['remarks'] ?? 'Manually recorded donation',
                'donation_date' => $validated['donation_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->saveDonationSelections('devotee', $donationId, $validated['selections_json'] ?? null);

            $devoteeUser = DB::table('devotees')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('devotees.devotee_id', $validated['devotee_id'])
                ->select('users.name', 'users.email')
                ->first();

            DonationReceiptService::send([
                'donor_name' => $devoteeUser->name ?? 'Devotee',
                'donor_email' => $devoteeUser->email ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_mode'],
                'purpose' => $validated['remarks'] ?? 'General Temple Fund',
                'event_id' => $validated['event_id'] ?? null,
                'donation_date' => $validated['donation_date'],
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);

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

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100',
            'purpose_details' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:Cash,UPI,Bank',
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
                'transaction_id' => $validated['transaction_id'] ?? 'GUEST-' . strtoupper(uniqid()),
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_no' => $validated['bank_account_no'] ?? null,
                'bank_ifsc' => $validated['bank_ifsc'] ?? null,
                'bank_branch' => $validated['bank_branch'] ?? null,
                'donation_date' => $validated['donation_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->saveDonationSelections('guest', $donationId, $validated['selections_json'] ?? null);

            DonationReceiptService::send([
                'donor_name' => $validated['donor_name'],
                'donor_email' => $validated['email'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'purpose' => $validated['purpose_details'] ?? $validated['purpose'],
                'event_id' => $validated['event_id'] ?? null,
                'donation_date' => $validated['donation_date'],
                'transaction_id' => $validated['transaction_id'] ?? null,
            ]);

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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,event_id',
            'amount' => 'required|numeric|min:1',
            'payment_mode' => 'required|string|in:Cash,UPI,Bank Transfer,Cheque,Stripe',
            'payment_status' => 'required|string|in:Paid,Pending,Cancelled,Failed',
            'transaction_id' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
            'donation_date' => 'required|date',
        ]);

        $donation = DB::table('donations')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:100',
            'purpose_details' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:Cash,UPI,Bank,Stripe',
            'payment_status' => 'required|string|in:Paid,Pending,Cancelled,Failed',
            'transaction_id' => 'nullable|string|max:100',
            'donation_date' => 'required|date',
        ]);

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
        }

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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'view')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($type === 'devotee') {
            $donation = DB::table('donations')
                ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
                ->join('users', 'devotees.user_id', '=', 'users.id')
                ->where('donations.id', $id)
                ->select('donations.*', 'users.name as donor_name', 'users.email')
                ->first();

            if (!$donation) {
                return redirect()->back()->with('error', 'Donation not found.');
            }

            if (!$donation->email) {
                return redirect()->back()->with('error', 'This devotee has no email address on file — cannot resend a receipt.');
            }

            DonationReceiptService::send([
                'donor_name' => $donation->donor_name,
                'donor_email' => $donation->email,
                'amount' => $donation->amount,
                'payment_method' => $donation->payment_method,
                'purpose' => $donation->remarks ?? 'General Temple Fund',
                'event_id' => $donation->event_id,
                'donation_date' => $donation->donation_date,
                'transaction_id' => $donation->transaction_id,
            ]);
        } elseif ($type === 'guest') {
            $donation = DB::table('donations_without_logins')->where('id', $id)->first();

            if (!$donation) {
                return redirect()->back()->with('error', 'Donation not found.');
            }

            if (!$donation->email) {
                return redirect()->back()->with('error', 'This donor has no email address on file — cannot resend a receipt.');
            }

            DonationReceiptService::send([
                'donor_name' => $donation->donor_name,
                'donor_email' => $donation->email,
                'amount' => $donation->amount,
                'payment_method' => $donation->payment_method,
                'purpose' => $donation->purpose_details ?? $donation->purpose,
                'event_id' => $donation->event_id,
                'donation_date' => $donation->donation_date,
                'transaction_id' => $donation->transaction_id,
            ]);
        } else {
            return redirect()->back()->with('error', 'Invalid donation type.');
        }

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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $table = $type === 'devotee' ? 'donations' : 'donations_without_logins';
        $row = DB::table($table)->where('id', $id)->first();

        if (!$row) {
            return redirect()->back()->with('error', 'Donation not found.');
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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations_without_logins')->where('id', $id)->first();
        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
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
            'amount' => $donation->amount,
            'payment_method' => $donation->payment_method,
            'purpose' => $donation->purpose_details ?? $donation->purpose,
            'event_id' => $donation->event_id,
            'donation_date' => $donation->donation_date,
            'transaction_id' => $donation->transaction_id,
        ]);

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
        if (!$user || !RolePermission::can(session('active_role', $user->role), 'donations', 'edit')) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $donation = DB::table('donations')
            ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->where('donations.id', $id)
            ->select('donations.*', 'users.name as donor_name', 'users.email')
            ->first();

        if (!$donation) {
            return redirect()->back()->with('error', 'Donation not found.');
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
            'amount' => $donation->amount,
            'payment_method' => $donation->payment_method,
            'purpose' => $donation->remarks ?: $donation->purpose,
            'event_id' => $donation->event_id,
            'donation_date' => $donation->donation_date,
            'transaction_id' => $donation->transaction_id,
        ]);

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
            'purpose_details' => 'nullable|string|max:255',
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
        // require contact details — the event itself decides via require_donor_contact_details.
        $requireContact = $request->filled('event_id')
            && Event::where('event_id', $request->input('event_id'))->value('require_donor_contact_details');

        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'event_id' => 'nullable|exists:events,event_id',
            'email' => ($requireContact ? 'required' : 'nullable') . '|email|max:255',
            'mobile' => ($requireContact ? 'required' : 'nullable') . '|string|max:20',
            'amount' => 'required|numeric|min:1',
            'purpose' => 'required|string|max:255',
            'purpose_details' => 'nullable|string|max:255',
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
