<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Listings\DeeplinkChecker;
use App\Models\Listing;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Nightly check of the Check availability links on live listings, longest-unchecked first.
 * A link that fails is marked broken, which hides the banner; it then shows on the
 * dashboard and the Deeplink monitor for an admin to deal with.
 *
 * Links already broken or switched off by an admin are not re-checked here, so a link an
 * admin switched off never turns itself back on. Admins use Recheck now after a fix.
 */
class CheckDeeplinks extends Command
{
    protected $signature = 'deeplinks:check {--limit= : How many links to check this run}';

    protected $description = 'Check the Check availability links on live affiliate listings';

    public function handle(DeeplinkChecker $checker): int
    {
        $limit = (int) ($this->option('limit') ?? config('site.deeplink_check.per_run'));
        $pause = (int) config('site.deeplink_check.pause_ms');

        // Affiliate links only. Owners and agents are responsible for their own links
        // (decided 19 Nov 2026), so those are never checked.
        $listings = Listing::where('status', ListingStatus::Live)
            ->whereNotNull('deeplink_url')
            ->whereHas('owner', fn ($q) => $q->where('role', \App\Enums\Role::Affiliate->value))
            ->whereIn('deeplink_status', ['ok', 'unchecked'])
            ->orderByRaw('deeplink_checked_at IS NOT NULL')
            ->orderBy('deeplink_checked_at')
            ->limit($limit)
            ->get();

        $counts = ['ok' => 0, 'broken' => 0];
        foreach ($listings as $i => $listing) {
            $result = $checker->check($listing);
            $counts[$result['status'] === 'ok' ? 'ok' : 'broken']++;

            if ($pause > 0 && $i < $listings->count() - 1) {
                usleep($pause * 1000);
            }
        }

        Audit::record(null, 'job.deeplinks_check', 'job', 'deeplinks:check', ['checked' => $listings->count()] + $counts);
        $this->info("Checked {$listings->count()} links: {$counts['ok']} working, {$counts['broken']} broken.");

        return self::SUCCESS;
    }
}
