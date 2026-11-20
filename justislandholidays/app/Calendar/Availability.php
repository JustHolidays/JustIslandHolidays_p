<?php

namespace App\Calendar;

use App\Models\CalendarBooking;
use App\Models\Listing;
use Carbon\CarbonImmutable;

/**
 * Booked nights and the calendar that shows them (19 Nov 2026).
 *
 * A stay books the nights from check-in up to the night before check-out. A day is split in
 * two halves: the morning belongs to the night before (a guest leaving), the afternoon to the
 * night that starts that day (a guest arriving). So a check-in day shows its afternoon booked,
 * a check-out day shows its morning booked (half days), and a day between two back-to-back
 * stays shows both halves booked.
 */
final class Availability
{
    /** Visitors can page through this many months, this month included (18, decided 20 Nov 2026). */
    public const PUBLIC_MONTHS = 18;

    /** The furthest "months" offset that still fits $shown months inside PUBLIC_MONTHS. */
    public static function maxPublicOffset(int $shown): int
    {
        return max(0, self::PUBLIC_MONTHS - $shown);
    }

    /** @return array<string, true> booked nights as 'Y-m-d' => true, for nights from $from up to (not including) $to */
    public static function bookedNights(Listing $listing, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $nights = [];
        $bookings = CalendarBooking::where('listing_id', $listing->id)
            ->where('check_in', '<', $to->toDateString())
            ->where('check_out', '>', $from->toDateString())
            ->get(['check_in', 'check_out']);

        foreach ($bookings as $b) {
            $night = CarbonImmutable::parse($b->check_in)->max($from);
            $end = CarbonImmutable::parse($b->check_out)->min($to);
            for (; $night->lt($end); $night = $night->addDay()) {
                $nights[$night->toDateString()] = true;
            }
        }

        return $nights;
    }

    /**
     * Months for display, starting with the month of $start.
     *
     * @return list<array{title: string, weeks: list<list<array{date: CarbonImmutable, inMonth: bool, past: bool, am: bool, pm: bool}>>}>
     */
    public static function months(Listing $listing, CarbonImmutable $start, int $count): array
    {
        $first = $start->startOfMonth();
        $gridFrom = $first->startOfWeek(CarbonImmutable::MONDAY)->subDay();   // the night before the first cell
        $gridTo = $first->addMonths($count)->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addDay();
        $booked = self::bookedNights($listing, $gridFrom, $gridTo);
        $today = CarbonImmutable::today();

        $months = [];
        for ($m = 0; $m < $count; $m++) {
            $month = $first->addMonths($m);
            $day = $month->startOfWeek(CarbonImmutable::MONDAY);
            $last = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
            $weeks = [];
            $week = [];
            for (; $day->lte($last); $day = $day->addDay()) {
                $week[] = [
                    'date' => $day,
                    'inMonth' => $day->month === $month->month,
                    'past' => $day->lt($today),
                    'am' => isset($booked[$day->subDay()->toDateString()]),
                    'pm' => isset($booked[$day->toDateString()]),
                ];
                if (count($week) === 7) {
                    $weeks[] = $week;
                    $week = [];
                }
            }
            $months[] = ['title' => $month->format('F Y'), 'weeks' => $weeks];
        }

        return $months;
    }

    /** Plain words for one day, for screen readers and the tooltip. */
    public static function describe(array $day): string
    {
        $date = $day['date']->format('j F');

        return match (true) {
            $day['am'] && $day['pm'] => "{$date}: booked",
            $day['am'] => "{$date}: booked in the morning (guests leave), free from the afternoon",
            $day['pm'] => "{$date}: free in the morning, booked from the afternoon (guests arrive)",
            default => "{$date}: available",
        };
    }

    // --- Owner's own marks -------------------------------------------------------------------

    /** Marks one night (arriving on $date, leaving the next morning) as booked by the owner. */
    public static function bookNight(Listing $listing, CarbonImmutable $date): void
    {
        self::bookRange($listing, $date, $date->addDay());
    }

    /** Marks the nights from $checkIn up to $checkOut as booked by the owner. */
    public static function bookRange(Listing $listing, CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        $booking = new CalendarBooking(['check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString()]);
        $booking->listing_id = $listing->id;
        $booking->save();
    }

    /**
     * Frees one night the owner marked. A longer stay they marked is split around it.
     * Nights that came from a feed cannot be freed here (they come back at the next sync).
     */
    public static function freeNight(Listing $listing, CarbonImmutable $date): bool
    {
        $booking = CalendarBooking::where('listing_id', $listing->id)->whereNull('feed_id')
            ->where('check_in', '<=', $date->toDateString())
            ->where('check_out', '>', $date->toDateString())
            ->first();
        if (! $booking) {
            return false;
        }

        $in = CarbonImmutable::parse($booking->check_in);
        $out = CarbonImmutable::parse($booking->check_out);
        $booking->delete();
        if ($in->lt($date)) {
            self::bookRange($listing, $in, $date);
        }
        if ($date->addDay()->lt($out)) {
            self::bookRange($listing, $date->addDay(), $out);
        }

        return true;
    }

    /** True if the night of $date is booked by a feed (so the owner cannot free it here). */
    public static function nightFromFeed(Listing $listing, CarbonImmutable $date): bool
    {
        return CalendarBooking::where('listing_id', $listing->id)->whereNotNull('feed_id')
            ->where('check_in', '<=', $date->toDateString())
            ->where('check_out', '>', $date->toDateString())
            ->exists();
    }
}
