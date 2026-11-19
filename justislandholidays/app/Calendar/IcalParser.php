<?php

namespace App\Calendar;

use Carbon\CarbonImmutable;

/**
 * Reads booked stays from an iCal file (Airbnb, Booking.com, Vrbo, Google Calendar and others).
 * Only the dates are taken: never names, notes or anything else in the events.
 */
final class IcalParser
{
    /** @return list<array{check_in: string, check_out: string}> */
    public static function stays(string $ics, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $today->subDay();
        $until = $today->addYears(3);

        // Unfold lines: a line starting with a space or tab continues the one before.
        $lines = preg_split('/\r\n|\r|\n/', preg_replace('/\r?\n[ \t]/', '', $ics)) ?: [];

        $stays = [];
        $event = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if (strcasecmp($line, 'BEGIN:VEVENT') === 0) {
                $event = [];

                continue;
            }
            if (strcasecmp($line, 'END:VEVENT') === 0) {
                if ($event !== null && ($stay = self::stay($event, $from, $until))) {
                    $stays[] = $stay;
                }
                $event = null;

                continue;
            }
            if ($event === null || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $name = strtoupper(explode(';', $key, 2)[0]);
            if (in_array($name, ['DTSTART', 'DTEND', 'STATUS'], true)) {
                $event[$name] = trim($value);
            }
        }

        return $stays;
    }

    /** @return array{check_in: string, check_out: string}|null */
    private static function stay(array $event, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        if (strtoupper($event['STATUS'] ?? '') === 'CANCELLED') {
            return null;
        }
        $start = self::date($event['DTSTART'] ?? null);
        if (! $start) {
            return null;
        }
        $end = self::date($event['DTEND'] ?? null) ?? $start->addDay();
        if ($end->lte($start)) {
            $end = $start->addDay();   // a same-day event still blocks that night
        }
        if ($end->lte($from) || $start->gt($until)) {
            return null;   // long past, or too far ahead
        }

        return ['check_in' => $start->toDateString(), 'check_out' => $end->min($until)->toDateString()];
    }

    /** 20250601, 20250601T150000 or 20250601T150000Z: the calendar day is enough. */
    private static function date(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]);
    }
}
