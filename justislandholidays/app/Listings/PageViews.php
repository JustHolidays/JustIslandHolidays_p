<?php

namespace App\Listings;

use App\Models\Listing;
use App\Support\IpHasher;
use App\Support\VisitorAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Property page views (20 Nov 2026): counted per property per day in listing_views_daily.
 * Nothing about the visitor is stored. Not counted: known bots, the lister and their agency
 * colleagues, admins, and the same visitor again within 30 minutes (a salted hash of their
 * address and browser, kept only in the cache for those 30 minutes).
 */
final class PageViews
{
    public const REPEAT_MINUTES = 30;

    public static function record(Listing $listing, Request $request): void
    {
        if (VisitorAgent::isBot($request)) {
            return;
        }
        $user = $request->user();
        if ($user && ($user->isAdmin() || $user->can('editAsLister', $listing))) {
            return;   // looking at their own (or, for admins, any) property is not a visit
        }

        $visitor = hash('sha256', IpHasher::hash($request->ip()).'|'.$request->userAgent());
        if (! Cache::add('view:'.$listing->id.':'.$visitor, 1, now()->addMinutes(self::REPEAT_MINUTES))) {
            return;
        }

        $key = ['listing_id' => $listing->id, 'day' => now()->toDateString()];
        DB::table('listing_views_daily')->insertOrIgnore($key + ['views' => 0]);
        DB::table('listing_views_daily')->where($key)->increment('views');
    }

    /**
     * Views per property since a day (null: all time).
     *
     * @param  iterable<int>  $listingIds
     * @return array<int, int> listing id => views
     */
    public static function totals(iterable $listingIds, ?string $since = null): array
    {
        return DB::table('listing_views_daily')
            ->whereIn('listing_id', collect($listingIds)->all())
            ->when($since, fn ($q) => $q->where('day', '>=', $since))
            ->groupBy('listing_id')
            ->selectRaw('listing_id, SUM(views) as total')
            ->pluck('total', 'listing_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
