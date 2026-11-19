<?php

namespace App\Console\Commands;

use App\Calendar\FeedSync;
use App\Models\CalendarFeed;
use App\Support\Audit;
use Illuminate\Console\Command;

/** Nightly: imports every iCal feed of properties that have the booking calendar. */
class SyncCalendars extends Command
{
    protected $signature = 'calendars:sync';

    protected $description = 'Import the iCal feeds of properties with a booking calendar';

    public function handle(FeedSync $sync): int
    {
        $feeds = CalendarFeed::whereHas('listing', fn ($q) => $q->whereIn('status', ['draft', 'pending_review', 'live', 'paused']))
            ->with('listing.owner')->get()
            ->filter(fn (CalendarFeed $f) => $f->listing->hasCalendar());

        $ok = 0;
        $failed = 0;
        foreach ($feeds as $i => $feed) {
            $sync->sync($feed)['ok'] ? $ok++ : $failed++;
            if ($i < $feeds->count() - 1) {
                usleep(250_000);   // polite to the calendar sites
            }
        }

        Audit::record(null, 'job.calendars_sync', 'job', 'calendars:sync', ['feeds' => $feeds->count(), 'ok' => $ok, 'failed' => $failed]);
        $this->info("Synced {$feeds->count()} calendar(s): {$ok} ok, {$failed} failed.");

        return self::SUCCESS;
    }
}
