<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus;
use App\Models\Agency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** "Add a listing" is switched off once the plan's listing limit is reached (20 Nov 2026). */
class AddListingLimitTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    private function subscribe(User|Agency $account, string $code): void
    {
        (new Subscription())->forceFill([
            'user_id' => $account instanceof User ? $account->id : null,
            'agency_id' => $account instanceof Agency ? $account->id : null,
            'plan_id' => Plan::where('code', $code)->value('id'),
            'billing_interval' => 'year',
            'status' => 'active',
            'next_billing_at' => now()->addYear(),
        ])->save();
    }

    public function test_owner_under_the_limit_can_add(): void
    {
        $owner = User::factory()->owner()->create();
        $this->subscribe($owner, 'standard');

        $this->actingAs($owner)->get(route('account.listings.index'))->assertOk()
            ->assertSee('data-add-listing="enabled"', false)->assertSee(route('account.listings.create'), false);
        $this->actingAs($owner)->get(route('account.listings.create'))->assertOk();
    }

    public function test_owner_at_the_limit_sees_a_disabled_button_and_cannot_add(): void
    {
        $owner = User::factory()->owner()->create();
        $this->subscribe($owner, 'standard');   // Owners Plan: 1 listing
        $this->setStatus($this->completeListing($owner), ListingStatus::Live);

        $this->actingAs($owner)->get(route('account.listings.index'))->assertOk()
            ->assertSee('data-add-listing="disabled"', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('href="'.route('account.listings.create').'"', false)
            ->assertSee('the most the Owners Plan allows')
            ->assertSee(route('account.billing'), false);

        // Typing the address or posting the form is refused too.
        $this->actingAs($owner)->get(route('account.listings.create'))->assertRedirect(route('account.listings.index'))->assertSessionHasErrors('limit');
        $before = \App\Models\Listing::count();
        $this->actingAs($owner)->post(route('account.listings.store'), ['title' => 'Another'])->assertRedirect(route('account.listings.index'))->assertSessionHasErrors('limit');
        $this->assertSame($before, \App\Models\Listing::count());
    }

    public function test_drafts_and_paused_count_but_archived_do_not(): void
    {
        $owner = User::factory()->owner()->create();
        $this->subscribe($owner, 'standard');
        $listing = $this->completeListing($owner);   // a draft still takes the place

        $this->actingAs($owner)->get(route('account.listings.index'))->assertSee('data-add-listing="disabled"', false);

        $listing->forceFill(['status' => ListingStatus::Archived])->save();
        $this->actingAs($owner)->get(route('account.listings.index'))->assertSee('data-add-listing="enabled"', false);
    }

    public function test_agents_count_their_agencys_listings(): void
    {
        $agency = Agency::factory()->create();
        $agent = User::factory()->agent()->create(['agency_id' => $agency->id]);
        $colleague = User::factory()->agent()->create(['agency_id' => $agency->id]);
        $this->subscribe($agency, 'agent_plus');
        Plan::where('code', 'agent_plus')->update(['max_listings' => 2]);
        foreach ([$agent, $colleague] as $who) {
            $l = $this->completeListing($who);
            $l->forceFill(['agency_id' => $agency->id])->save();
        }

        $this->actingAs($agent)->get(route('account.listings.index'))->assertSee('data-add-listing="disabled"', false);
    }

    public function test_plans_without_a_limit_and_affiliates_can_always_add(): void
    {
        $owner = User::factory()->owner()->create();
        $this->subscribe($owner, 'standard');
        Plan::where('code', 'standard')->update(['max_listings' => null]);
        $this->completeListing($owner);
        $this->actingAs($owner)->get(route('account.listings.index'))->assertSee('data-add-listing="enabled"', false);

        $affiliate = User::factory()->affiliate()->create();
        $this->completeListing($affiliate);
        $this->completeListing($affiliate);
        $this->actingAs($affiliate)->get(route('account.listings.index'))->assertSee('data-add-listing="enabled"', false);
    }
}
