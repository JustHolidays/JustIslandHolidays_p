<?php

namespace Tests\Feature\Listings;

use App\Billing\AccountPlan;
use App\Enums\ListingStatus as S;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class AccountPlanTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private AccountPlan $plans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->plans = app(AccountPlan::class);
    }

    private function subscribe(User|Agency $account, string $planCode, string $status = 'active', array $extra = []): Subscription
    {
        $sub = new Subscription();
        $sub->forceFill([
            'user_id' => $account instanceof User ? $account->id : null,
            'agency_id' => $account instanceof Agency ? $account->id : null,
            'plan_id' => Plan::where('code', $planCode)->value('id'),
            'billing_interval' => 'year',
            'status' => $status,
            'next_billing_at' => now()->addYear(),
        ] + $extra)->save();

        return $sub;
    }

    public function test_no_subscription_means_free(): void
    {
        $this->assertSame('free', $this->plans->planFor(User::factory()->owner()->create())->code);
    }

    public function test_active_subscription_gives_its_plan_and_updates_listings(): void
    {
        $owner = User::factory()->owner()->create();
        $listing = $this->setStatus($this->completeListing($owner), S::Live);
        $this->subscribe($owner, 'standard');

        $this->plans->sync($owner);

        $fresh = $listing->fresh();
        $this->assertSame('standard', $fresh->plan_code);
        $this->assertNull($fresh->featured_until);
        $this->assertGreaterThanOrEqual(10, $fresh->rank_score, 'Plan boost is included.');
    }

    public function test_featured_plan_features_listings_until_paid_through(): void
    {
        $owner = User::factory()->owner()->create();
        $listing = $this->setStatus($this->completeListing($owner), S::Live);
        $sub = $this->subscribe($owner, 'featured');

        $this->plans->sync($owner);

        $this->assertTrue($listing->fresh()->featured_until->equalTo($sub->next_billing_at));
    }

    public function test_cancelled_plan_lasts_until_ends_at_then_falls_back_to_free(): void
    {
        $owner = User::factory()->owner()->create();
        $sub = $this->subscribe($owner, 'standard', 'cancelled', ['ends_at' => now()->addDays(10), 'cancelled_at' => now()]);

        $this->assertSame('standard', $this->plans->planFor($owner)->code);

        $this->travel(11)->days();
        $this->assertSame('free', $this->plans->planFor($owner)->code);
    }

    public function test_lapse_pauses_listings_beyond_the_limit_newest_first(): void
    {
        $owner = User::factory()->owner()->create();
        $sub = $this->subscribe($owner, 'featured');   // 3 listings
        $old = $this->completeListing($owner, attributes: ['slug' => 'old']);
        $old->forceFill(['status' => S::Live, 'published_at' => now()->subMonths(3)])->save();
        $mid = $this->completeListing($owner, attributes: ['slug' => 'mid']);
        $mid->forceFill(['status' => S::Live, 'published_at' => now()->subMonth()])->save();
        $new = $this->completeListing($owner, attributes: ['slug' => 'new']);
        $new->forceFill(['status' => S::Live, 'published_at' => now()])->save();

        $sub->forceFill(['status' => 'expired', 'ends_at' => now()->subDay()])->save();
        $this->plans->sync($owner);

        $this->assertSame(S::Live, $old->fresh()->status, 'The oldest listing stays live.');
        $this->assertSame(S::Paused, $mid->fresh()->status);
        $this->assertSame(S::Paused, $new->fresh()->status);
        $this->assertSame('free', $old->fresh()->plan_code);
        $this->assertNull($old->fresh()->featured_until);
        $this->assertNull($new->fresh()->deleted_at, 'Nothing is deleted.');
    }

    public function test_agency_plan_covers_agency_listings(): void
    {
        $agency = Agency::factory()->create();
        $agent = User::factory()->agent()->create();
        $agent->forceFill(['agency_id' => $agency->id])->save();
        $listing = $this->completeListing($agent);
        $listing->forceFill(['agency_id' => $agency->id])->save();
        $this->subscribe($agency, 'agent_plus');

        $this->assertSame('agent_plus', $this->plans->planFor($this->plans->accountFor($agent->fresh()))->code);
        $this->plans->sync($agency);
        $this->assertSame('agent_plus', $listing->fresh()->plan_code);
    }

    public function test_affiliate_listings_never_get_a_plan(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->completeListing($affiliate);
        $listing->forceFill(['plan_code' => 'featured', 'featured_until' => now()->addYear()])->save();

        $this->plans->syncListing($listing->fresh());

        $this->assertSame('free', $listing->fresh()->plan_code);
        $this->assertNull($listing->fresh()->featured_until);
    }
}
