<?php

namespace App\Services;

use App\Models\Event;
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

        $options = $event->donationOptions;

        $rows = $devoteeDonations->concat($guestDonations)
            ->sortByDesc(fn ($row) => $row->donation_date . ' ' . $row->created_at)
            ->values()
            ->map(function ($row) use ($options, $donationSelections) {
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

        return ['event' => $event, 'options' => $options, 'rows' => $rows];
    }
}
