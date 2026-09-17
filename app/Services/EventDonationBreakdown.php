<?php

namespace App\Services;

use App\Models\Event;
use App\Models\LinklyTransaction;
use Illuminate\Support\Facades\DB;

/**
 * One event's donations, normalized (devotee + guest merged) and pivoted by donation
 * option: each row gets an option_id => amount map plus an 'other_amount' catch-all for
 * anything not attributable to a specific option. Shared by the Event Donations tab's
 * per-event export (DonationController::exportEventDonations()) and the Event Console
 * (EventConsoleController) so both stay in sync from one implementation.
 */
class EventDonationBreakdown
{
    public static function forEvent(int $eventId): array
    {
        $event = Event::with('donationOptions')->find($eventId);
        if (!$event) {
            return ['event' => null, 'options' => collect(), 'rows' => collect()];
        }

        $devoteeDonations = DB::table('donations')
            ->join('devotees', 'donations.devotee_id', '=', 'devotees.devotee_id')
            ->join('users', 'devotees.user_id', '=', 'users.id')
            ->where('donations.event_id', $eventId)
            ->select('donations.*', 'users.name as devotee_name', 'users.email', 'users.mobile')
            ->get()
            ->map(function ($d) {
                $d->donation_type = 'devotee';
                $d->display_id = 'DN' . str_pad($d->id, 5, '0', STR_PAD_LEFT);
                $d->display_name = $d->devotee_name;
                return $d;
            });

        $guestDonations = DB::table('donations_without_logins')
            ->where('event_id', $eventId)
            ->get()
            ->map(function ($g) {
                $g->donation_type = 'guest';
                $g->display_id = 'GD' . str_pad($g->id, 5, '0', STR_PAD_LEFT);
                $g->display_name = $g->donor_name;
                return $g;
            });

        $donationSelections = DB::table('donation_selections')->get()
            ->groupBy(fn ($s) => $s->donation_type . ':' . $s->donation_id);

        // The Linkly-generated POS transaction reference (e.g. "EFT091716500372") for any
        // EFT Terminal donation — a different identifier from the donation's own
        // transaction_id (which holds Linkly's RRN/auth code, the donor/bank-facing
        // reference), so both need to be visible to actually cross-reference a row here
        // against the EFTPOS pane's transaction ledger. One bulk lookup avoids an N+1 query.
        $linklyRefs = LinklyTransaction::where('event_id', $eventId)
            ->where('txn_type', 'purchase')
            ->whereNotNull('donation_id')
            ->get()
            ->keyBy(fn ($t) => $t->donation_type . ':' . $t->donation_id);

        $options = $event->donationOptions;

        $rows = $devoteeDonations->concat($guestDonations)
            ->sortByDesc(fn ($row) => $row->donation_date . ' ' . $row->created_at)
            ->values()
            ->map(function ($row) use ($options, $donationSelections, $linklyRefs) {
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
                $row->linkly_txn_ref = $linklyRefs[$row->donation_type . ':' . $row->id]->pos_txn_ref ?? null;
                return $row;
            });

        return ['event' => $event, 'options' => $options, 'rows' => $rows];
    }
}
