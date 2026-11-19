<?php

namespace App\Http\Controllers\Account;

use App\Calendar\Availability;
use App\Calendar\FeedSync;
use App\Http\Controllers\Controller;
use App\Models\CalendarBooking;
use App\Models\CalendarFeed;
use App\Models\Listing;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The booking calendar in the owner's editor (paid plans): iCal feeds to import, and nights
 * the owner marks as booked themselves. Changes show on the property page straight away.
 */
class CalendarController extends Controller
{
    public const MAX_FEEDS = 10;

    public function addFeed(Request $request, Listing $listing, FeedSync $sync): RedirectResponse
    {
        $this->ensureCalendar($listing);
        if ($listing->calendarFeeds()->count() >= self::MAX_FEEDS) {
            return back()->withErrors(['feed_url' => 'You can add up to '.self::MAX_FEEDS.' calendars.']);
        }

        $request->merge(['feed_url' => FeedSync::normalise((string) $request->input('feed_url'))]);
        $data = $request->validate([
            'feed_name' => ['required', 'string', 'max:60'],
            'feed_url' => ['required', 'url:https', 'max:1000'],
        ], [
            'feed_name.required' => 'Give the calendar a name, for example Airbnb.',
            'feed_url.required' => 'Paste the calendar\'s iCal address.',
            'feed_url.url' => 'The iCal address must start with https:// (or webcal://).',
        ]);

        $feed = new CalendarFeed(['name' => trim($data['feed_name']), 'url' => $data['feed_url']]);
        $feed->listing_id = $listing->id;
        $feed->save();
        Audit::record($request->user(), 'calendar.feed_add', $listing, null, ['feed_id' => $feed->id, 'name' => $feed->name]);

        $result = $sync->sync($feed);

        return back()->with('status', $result['ok']
            ? "Calendar added and imported: {$result['stays']} booked stay(s) found."
            : "Calendar added, but it could not be read yet: {$result['error']}. Check the address, then press Sync now.");
    }

    public function syncFeed(Request $request, Listing $listing, int $feed, FeedSync $sync): RedirectResponse
    {
        $this->ensureCalendar($listing);
        $record = $listing->calendarFeeds()->whereKey($feed)->firstOrFail();
        $result = $sync->sync($record);

        return $result['ok']
            ? back()->with('status', "{$record->name} imported: {$result['stays']} booked stay(s).")
            : back()->withErrors(['feed_url' => "{$record->name} could not be read: {$result['error']}."]);
    }

    public function removeFeed(Request $request, Listing $listing, int $feed): RedirectResponse
    {
        $this->ensureCalendar($listing);
        $record = $listing->calendarFeeds()->whereKey($feed)->firstOrFail();
        $record->delete();   // its imported stays go with it
        Audit::record($request->user(), 'calendar.feed_remove', $listing, null, ['feed_id' => $feed, 'name' => $record->name]);

        return back()->with('status', "{$record->name} removed, with the dates it brought in.");
    }

    /** Book dates by hand: from arrival up to the departure morning. */
    public function addBooking(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureCalendar($listing);
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today', 'before:+3 years'],
            'check_out' => ['required', 'date', 'after:check_in'],
        ], [
            'check_in.after_or_equal' => 'The arrival date cannot be in the past.',
            'check_out.after' => 'The departure date must be after the arrival date.',
        ]);

        Availability::bookRange($listing, CarbonImmutable::parse($data['check_in']), CarbonImmutable::parse($data['check_out']));
        Audit::record($request->user(), 'calendar.book', $listing, null, $data);

        return back()->with('status', 'Dates marked as booked.');
    }

    /** A day on the calendar: books the night from that day, or frees it if the owner booked it. */
    public function toggleNight(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureCalendar($listing);
        $date = CarbonImmutable::parse($request->validate(['date' => ['required', 'date', 'after_or_equal:today', 'before:+3 years']])['date']);

        if (Availability::freeNight($listing, $date)) {
            $message = 'The night of '.$date->format('j F').' is free again.';
        } elseif (Availability::nightFromFeed($listing, $date)) {
            return back()->withErrors(['date' => 'The night of '.$date->format('j F').' comes from one of your imported calendars. Change it there.']);
        } else {
            Availability::bookNight($listing, $date);
            $message = 'The night of '.$date->format('j F').' is marked as booked.';
        }
        Audit::record($request->user(), 'calendar.toggle', $listing, null, ['date' => $date->toDateString()]);

        return back()->with('status', $message);
    }

    public function removeBooking(Request $request, Listing $listing, int $booking): RedirectResponse
    {
        $this->ensureCalendar($listing);
        CalendarBooking::where('listing_id', $listing->id)->whereNull('feed_id')->whereKey($booking)->firstOrFail()->delete();
        Audit::record($request->user(), 'calendar.unbook', $listing, null, ['booking_id' => $booking]);

        return back()->with('status', 'Those dates are free again.');
    }

    private function ensureCalendar(Listing $listing): void
    {
        abort_unless($listing->hasCalendar(), 404);
    }
}
