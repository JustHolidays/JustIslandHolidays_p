<?php

namespace App\Billing;

use App\Enums\ListingStatus;
use App\Listings\ListingWorkflow;
use App\Listings\RankScore;
use App\Models\Agency;
use App\Models\Listing;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Works out which plan an account is on and keeps its listings in step:
 * plan_code, featured_until and rank_score. When a plan lapses the account falls back
 * to Free, and live listings beyond the plan's limit are paused (never deleted).
 *
 * The account is the agency for agents in an agency, otherwise the user.
 * Affiliates are outside billing and always stay on standard ranking.
 */
final class AccountPlan
{
    /** The subscription that currently gives access, if any. */
    public function currentSubscription(User|Agency $account): ?Subscription
    {
        return Subscription::query()
            ->where($account instanceof Agency ? 'agency_id' : 'user_id', $account->id)
            ->where(function (Builder $q) {
                // Active; a complimentary plan granted by a super admin has no PayPal id and ends at ends_at.
                $q->where(fn (Builder $q) => $q->where('status', 'active')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now())))
                    // Cancelled or suspended: access runs until the end of the paid period.
                    ->orWhere(fn (Builder $q) => $q->whereIn('status', ['cancelled', 'suspended'])->where('ends_at', '>', now()));
            })
            ->with('plan')
            ->latest('id')
            ->first();
    }

    public function planFor(User|Agency $account): Plan
    {
        return $this->currentSubscription($account)?->plan ?? $this->freePlan();
    }

    public function accountFor(User $user): User|Agency
    {
        return $user->role?->value === 'agent' && $user->agency_id ? $user->agency : $user;
    }

    public function planForListing(Listing $listing): Plan
    {
        $listing->loadMissing(['owner', 'agency']);

        return $this->planFor($listing->agency ?? $listing->owner);
    }

    /** All listings that count against the same account as this listing. */
    public function listingsQueryFor(Listing $listing): Builder
    {
        return $listing->agency_id
            ? Listing::where('agency_id', $listing->agency_id)
            : Listing::where('owner_id', $listing->owner_id)->whereNull('agency_id');
    }

    /** Re-applies the account's plan to all its listings. Called after any subscription change. */
    public function sync(User|Agency $account): void
    {
        $plan = $this->planFor($account);
        $subscription = $this->currentSubscription($account);
        $paidUntil = $subscription?->ends_at ?? $subscription?->next_billing_at;

        $listings = ($account instanceof Agency
            ? Listing::where('agency_id', $account->id)
            : Listing::where('owner_id', $account->id)->whereNull('agency_id'))
            ->with(['owner', 'translations'])
            ->get()
            ->reject(fn (Listing $l) => $l->isAffiliateListing());

        foreach ($listings as $listing) {
            $this->apply($listing, $plan, $paidUntil);
        }

        $this->enforceLimit($listings, $plan);
    }

    public function syncListing(Listing $listing): void
    {
        $listing->loadMissing(['owner', 'agency']);

        if ($listing->isAffiliateListing()) {
            $listing->forceFill(['plan_code' => 'free', 'featured_until' => null, 'rank_score' => RankScore::for($listing)])->save();

            return;
        }

        $account = $listing->agency ?? $listing->owner;
        $subscription = $this->currentSubscription($account);
        $this->apply($listing, $this->planFor($account), $subscription?->ends_at ?? $subscription?->next_billing_at);
    }

    private function apply(Listing $listing, Plan $plan, $paidUntil): void
    {
        $listing->plan_code = $plan->code;
        // Featured plans feature the listing for as long as the plan is paid for.
        $listing->featured_until = $plan->is_featured ? ($paidUntil ?? now()->addMonth()) : null;
        $listing->rank_score = RankScore::for($listing);
        $listing->save();
    }

    /** Pauses the most recently published live listings beyond the plan's limit. */
    private function enforceLimit($listings, Plan $plan): void
    {
        if ($plan->max_listings === null) {
            return;
        }

        $counting = $listings->filter(fn (Listing $l) => in_array($l->status, [ListingStatus::Live, ListingStatus::PendingReview], true));
        $excess = $counting->count() - $plan->max_listings;
        if ($excess <= 0) {
            return;
        }

        $workflow = app(ListingWorkflow::class);
        $counting->filter(fn (Listing $l) => $l->status === ListingStatus::Live)
            ->sortByDesc(fn (Listing $l) => $l->published_at?->timestamp ?? 0)
            ->take($excess)
            ->each(fn (Listing $l) => $workflow->pauseForPlan($l, "Your plan now allows {$plan->max_listings} listing(s)."));
    }

    private function freePlan(): Plan
    {
        return Plan::where('code', 'free')->first()
            ?? new Plan(['code' => 'free', 'name' => 'Free', 'audience' => 'owner', 'max_listings' => 1, 'search_boost' => 0]);
    }
}
