<?php

namespace App\Console\Commands;

use App\Billing\AccountPlan;
use App\Listings\RankScore;
use App\Models\Agency;
use App\Models\Listing;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Nightly reconcile of the derived listing columns (plan_code, featured_until, rank_score):
 *  - subscriptions and granted plans past their end date are marked expired;
 *  - every owner and agency account has its plan re-applied (which also pauses listings
 *    beyond a smaller plan's limit);
 *  - every live listing gets a fresh rank score (plan boost, completeness, freshness).
 */
class ReconcileListings extends Command
{
    protected $signature = 'listings:reconcile';

    protected $description = 'Expire ended plans and recalculate plan, featured period and ranking for listings';

    public function handle(AccountPlan $plans): int
    {
        RankScore::flush();

        $expired = Subscription::whereIn('status', ['active', 'cancelled', 'suspended'])
            ->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->get();
        foreach ($expired as $sub) {
            $before = $sub->status;
            $sub->forceFill(['status' => 'expired'])->save();
            Audit::record(null, 'subscription.expire', $sub, null, ['before' => ['status' => $before]]);
        }

        // Re-apply each account's plan: users without an agency, then agencies.
        $userIds = Listing::whereNull('agency_id')->distinct()->pluck('owner_id');
        User::whereIn('id', $userIds)->whereIn('role', ['owner', 'agent'])->each(fn (User $u) => $plans->sync($u));
        Agency::whereIn('id', Listing::whereNotNull('agency_id')->distinct()->pluck('agency_id'))->each(fn (Agency $a) => $plans->sync($a));

        // Affiliates and anything not covered above: refresh the rank score only.
        $refreshed = 0;
        Listing::with(['translations', 'owner'])->where('status', 'live')->chunkById(500, function ($chunk) use (&$refreshed) {
            foreach ($chunk as $listing) {
                $score = RankScore::for($listing);
                if ($score !== $listing->rank_score) {
                    $listing->forceFill(['rank_score' => $score])->saveQuietly();
                    $refreshed++;
                }
            }
        });

        Audit::record(null, 'job.reconcile', 'job', 'listings:reconcile', ['expired_subscriptions' => $expired->count(), 'rank_changes' => $refreshed]);
        $this->info("Expired {$expired->count()} subscriptions; {$refreshed} rank scores changed.");

        return self::SUCCESS;
    }
}
