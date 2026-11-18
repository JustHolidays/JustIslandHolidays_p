<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\Plan;

/**
 * rank_score = plan boost + completeness + freshness. Set when a plan changes or a
 * listing is saved, and recalculated nightly (build step 5).
 */
final class RankScore
{
    /** @var array<string, int> */
    private static array $boosts = [];

    public static function for(Listing $listing): int
    {
        $listing->loadMissing('translations');

        $score = self::planBoost($listing->plan_code);

        // Completeness
        $score += $listing->image_path ? 10 : 0;
        $score += $listing->min_price_week_cents !== null ? 5 : 0;
        $score += $listing->deeplink_url !== null ? 5 : 0;
        $score += filled($listing->translations->firstWhere('lang', 'en')?->summary) ? 5 : 0;
        $score += 2 * max(0, $listing->translations->count() - 1);   // extra languages

        // Freshness: updated in the last 90 days
        $score += ($listing->updated_at && $listing->updated_at->gt(now()->subDays(90))) ? 10 : 0;

        return $score;
    }

    public static function planBoost(string $planCode): int
    {
        return self::$boosts[$planCode] ??= (int) (Plan::where('code', $planCode)->value('search_boost') ?? 0);
    }

    /** For tests and after plans are edited. */
    public static function flush(): void
    {
        self::$boosts = [];
    }
}
