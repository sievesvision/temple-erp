<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Priest workload/availability rules for pooja bookings — extracted verbatim from
 * BookingController (which still delegates to this class, so every existing caller keeps
 * working unmodified) so BookingService (and any other future caller) doesn't have to
 * instantiate a controller to reuse it, the way StaffController::counterBookPooja() used
 * to (`new BookingController()->autoAssignPriest()`).
 *
 * One real bug fixed during extraction: isPriestAvailable() selected `poojas.duration_
 * minutes`, a column that has never existed (the actual column is `poojas.duration`, a
 * free-text nullable varchar) — every call threw SQLSTATE[42S22] "Unknown column"
 * immediately, meaning priest availability checking (and therefore booking creation) was
 * broken outright. Fixed to read the real `duration` column, parsed as a plain integer-
 * minutes value when numeric and falling back to the same 30-minute default the code
 * already intended otherwise — the Archana/Abhisheka/Homa/Satyanarayana special-cased
 * durations below are unaffected either way.
 */
class PriestAvailabilityService
{
    /**
     * Workload-balanced auto priest assignment — same candidate-then-sort-by-booking-count
     * logic as before. $excludeUserId replaces the original's direct Auth::id() call so
     * this is callable from any context (e.g. a staff-created booking, where the acting
     * user is never the priest), not just a logged-in priest's own self-booking page.
     */
    public static function autoAssignPriest($poojaId, $date, $time, ?int $excludeUserId = null): ?string
    {
        $activePriestsQuery = DB::table('priests')->whereIn('employment_status', ['Active', 'On Leave']);
        if ($excludeUserId) {
            $activePriestsQuery->where('user_id', '!=', $excludeUserId);
        }
        $activePriests = $activePriestsQuery->get();

        $candidatePriests = [];
        foreach ($activePriests as $priest) {
            if (self::isPriestAvailable($priest->priest_id, $poojaId, $date, $time, $excludeUserId)) {
                $bookingsCount = DB::table('pooja_bookings')
                    ->where('priest_id', $priest->priest_id)
                    ->where('booking_date', $date)
                    ->where('booking_status', '!=', 'Cancelled')
                    ->count();
                $candidatePriests[] = ['priest_id' => $priest->priest_id, 'bookings_count' => $bookingsCount];
            }
        }

        if (count($candidatePriests) === 0) {
            return null;
        }

        usort($candidatePriests, fn ($a, $b) => $a['bookings_count'] <=> $b['bookings_count']);

        return $candidatePriests[0]['priest_id'];
    }

    /**
     * Same overlap/capacity rules as before (duration-based collision, Archana hourly cap
     * of 4, Homa/Satyanarayana half-day exclusive slot with a max of 2/day each, leave-
     * request conflicts, self-booking prevention) — see the class docblock for the one
     * fix (the duration column reference).
     */
    public static function isPriestAvailable($priestId, $poojaId, $date, $time, ?int $excludeUserId = null): bool
    {
        $pooja = DB::table('poojas')->where('pooja_id', $poojaId)->first();
        if (!$pooja) {
            return false;
        }

        $priest = DB::table('priests')->where('priest_id', $priestId)->first();
        if (!$priest || !in_array($priest->employment_status, ['Active', 'On Leave'], true)) {
            return false;
        }

        $onLeave = DB::table('leave_requests')
            ->where('priest_id', $priestId)
            ->where('status', 'Approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
        if ($onLeave) {
            return false;
        }

        if ($excludeUserId && (string) $priest->user_id === (string) $excludeUserId) {
            return false;
        }

        $startTime = strtotime("$date $time");
        $poojaName = strtoupper($pooja->pooja_name);

        $durationMinutes = self::parseDurationMinutes($pooja->duration ?? null);
        if (str_contains($poojaName, 'ARCHANA')) {
            $durationMinutes = 15;
        } elseif (str_contains($poojaName, 'ABHISHEKA')) {
            $durationMinutes = 60;
        }
        $endTime = $startTime + ($durationMinutes * 60);

        $existingBookings = DB::table('pooja_bookings')
            ->join('poojas', 'pooja_bookings.pooja_id', '=', 'poojas.pooja_id')
            ->where('pooja_bookings.priest_id', $priestId)
            ->where('pooja_bookings.booking_date', $date)
            ->where('pooja_bookings.booking_status', '!=', 'Cancelled')
            ->select('pooja_bookings.*', 'poojas.pooja_name', 'poojas.duration')
            ->get();

        $isHoma = str_contains($poojaName, 'HOMA');
        $isSatyanarayana = str_contains($poojaName, 'SATYANARAYANA');

        if ($isHoma || $isSatyanarayana) {
            $reqHour = (int) date('H', $startTime);
            $reqSlot = $reqHour < 14 ? 'Morning' : 'Afternoon';

            if ($reqSlot === 'Morning' && ($reqHour < 6 || $reqHour >= 14)) {
                return false;
            }
            if ($reqSlot === 'Afternoon' && ($reqHour < 14 || $reqHour >= 20)) {
                return false;
            }

            $homaCount = 0;
            $satyanarayanaCount = 0;

            foreach ($existingBookings as $b) {
                $bName = strtoupper($b->pooja_name);
                $bTime = strtotime("$date $b->booking_time");
                $bHour = (int) date('H', $bTime);
                $bSlot = $bHour < 14 ? 'Morning' : 'Afternoon';

                if ($bSlot === $reqSlot) {
                    return false;
                }
                if (str_contains($bName, 'HOMA')) {
                    $homaCount++;
                }
                if (str_contains($bName, 'SATYANARAYANA')) {
                    $satyanarayanaCount++;
                }
            }

            if ($isHoma && $homaCount >= 2) {
                return false;
            }
            if ($isSatyanarayana && $satyanarayanaCount >= 2) {
                return false;
            }

            return true;
        }

        $hourStartStr = date('H:00:00', $startTime);
        $hourEndStr = date('H:59:59', $startTime);
        $archanaCount = 0;

        foreach ($existingBookings as $b) {
            $bName = strtoupper($b->pooja_name);
            $bStartTime = strtotime("$date $b->booking_time");

            if (str_contains($bName, 'HOMA') || str_contains($bName, 'SATYANARAYANA')) {
                $bHour = (int) date('H', $bStartTime);
                $bSlot = $bHour < 14 ? 'Morning' : 'Afternoon';
                $reqHour = (int) date('H', $startTime);
                $reqSlot = $reqHour < 14 ? 'Morning' : 'Afternoon';
                if ($bSlot === $reqSlot) {
                    return false;
                }
            }

            $bDuration = self::parseDurationMinutes($b->duration ?? null);
            if (str_contains($bName, 'ARCHANA')) {
                $bDuration = 15;
            } elseif (str_contains($bName, 'ABHISHEKA')) {
                $bDuration = 60;
            }
            $bEndTime = $bStartTime + ($bDuration * 60);

            if (($startTime >= $bStartTime && $startTime < $bEndTime)
                || ($endTime > $bStartTime && $endTime <= $bEndTime)
                || ($bStartTime >= $startTime && $bStartTime < $endTime)) {
                return false;
            }

            if (str_contains($poojaName, 'ARCHANA') && str_contains($bName, 'ARCHANA')) {
                if ($b->booking_time >= $hourStartStr && $b->booking_time <= $hourEndStr) {
                    $archanaCount++;
                }
            }
        }

        if (str_contains($poojaName, 'ARCHANA') && $archanaCount >= 4) {
            return false;
        }

        return true;
    }

    /**
     * poojas.duration is free-text (e.g. an admin might type "30" or "30 mins" or leave it
     * blank) — use it when it's a clean number, otherwise fall back to the same 30-minute
     * default the original (broken) code already intended.
     */
    private static function parseDurationMinutes(?string $duration): int
    {
        if ($duration !== null && is_numeric(trim($duration))) {
            return (int) trim($duration);
        }

        return 30;
    }
}
