<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs. The server runs `php artisan schedule:run` every minute from cron:
|   * * * * * cd /path/to/justislandholidays && php artisan schedule:run >> /dev/null 2>&1
| Times are UK time. Each job writes a summary to the audit log (actor: System).
*/

$nightly = fn ($event) => $event->timezone('Europe/London')->withoutOverlapping(120)->onOneServer();

// Booking calendars: import each property's iCal feeds (Airbnb, Booking.com, Google Calendar...).
$nightly(Schedule::command('calendars:sync')->dailyAt('01:30'));

// Plans that ended, featured periods, ranking.
$nightly(Schedule::command('listings:reconcile')->dailyAt('02:00'));

// Daily click totals for the last few days (kept after raw events are deleted).
$nightly(Schedule::command('clicks:rollup')->dailyAt('02:30'));

// Check availability links on live listings.
$nightly(Schedule::command('deeplinks:check')->dailyAt('03:00'));

// Retention: enquiries 24 months, raw clicks 13 months, deleted accounts 30 days.
$nightly(Schedule::command('data:retention')->dailyAt('04:00'));

// Expired password reset and invitation links.
$nightly(Schedule::command('auth:clear-resets')->dailyAt('04:30'));
$nightly(Schedule::command('auth:clear-resets invites')->dailyAt('04:35'));

// Hosts without a queue supervisor: work through the queue every minute from cron.
if (config('site.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=5')
        ->everyMinute()
        ->withoutOverlapping(5);
}
