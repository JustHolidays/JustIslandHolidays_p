<?php

namespace App\Calendar;

use App\Models\CalendarBooking;
use App\Models\CalendarFeed;
use App\Support\Fetch\FetchException;
use App\Support\Fetch\SafeFetcher;
use Illuminate\Support\Facades\DB;

/**
 * Imports one iCal feed: fetched safely (https only, no private addresses, size and time
 * limits), then the feed's stays replace the ones from its last import. If the fetch fails,
 * the stays from the last good import are kept.
 */
final class FeedSync
{
    public function __construct(private SafeFetcher $fetcher)
    {
    }

    /** webcal:// addresses (Apple, some Google links) are the same file over https. */
    public static function normalise(string $url): string
    {
        return preg_replace('#^webcals?://#i', 'https://', trim($url));
    }

    /** @return array{ok: bool, stays: int, error: ?string} */
    public function sync(CalendarFeed $feed): array
    {
        try {
            $result = $this->fetcher->get(self::normalise($feed->url), 2_000_000, allowTruncate: false);
            if (! $result->ok()) {
                throw new FetchException("the calendar address answered {$result->status}");
            }
            if (! str_contains($result->body, 'BEGIN:VCALENDAR')) {
                throw new FetchException('that address is not an iCal calendar');
            }
        } catch (FetchException $e) {
            $feed->forceFill(['last_status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 255), 'last_synced_at' => now()])->save();

            return ['ok' => false, 'stays' => 0, 'error' => $e->getMessage()];
        }

        $stays = IcalParser::stays($result->body);

        DB::transaction(function () use ($feed, $stays) {
            CalendarBooking::where('feed_id', $feed->id)->delete();
            foreach ($stays as $stay) {
                $booking = new CalendarBooking($stay);
                $booking->listing_id = $feed->listing_id;
                $booking->feed_id = $feed->id;
                $booking->save();
            }
            $feed->forceFill(['last_status' => 'ok', 'last_error' => null, 'last_synced_at' => now()])->save();
        });

        return ['ok' => true, 'stays' => count($stays), 'error' => null];
    }
}
