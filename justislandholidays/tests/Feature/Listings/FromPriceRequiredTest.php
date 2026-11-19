<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus as S;
use App\Listings\ListingChecklist;
use App\Listings\ListingWorkflow;
use App\Listings\ListingWorkflowException;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Owner and agent properties need a From price; without one they stay inactive (19 Nov 2026). */
class FromPriceRequiredTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
    }

    public function test_an_owner_property_without_a_from_price_cannot_be_sent_or_approved(): void
    {
        $island = Island::factory()->create();
        $listing = $this->completeListing(null, $island, ['min_price_week_cents' => null], licenceVerified: true);

        $this->assertContains('Add a From price per week.', ListingChecklist::missingForSubmit($listing));

        $workflow = app(ListingWorkflow::class);
        try {
            $workflow->submit($listing, $listing->owner);
            $this->fail('Sending without a From price should be refused.');
        } catch (ListingWorkflowException $e) {
            $this->assertContains('Add a From price per week.', $e->problems);
        }

        $pending = $this->setStatus($listing, S::PendingReview);
        $this->expectException(ListingWorkflowException::class);
        $workflow->approve($pending, $this->islandAdmin($island));
    }

    public function test_with_a_from_price_it_can_go_live(): void
    {
        $island = Island::factory()->create();
        $listing = $this->setStatus($this->completeListing(null, $island, ['min_price_week_cents' => 95000], licenceVerified: true), S::PendingReview);

        app(ListingWorkflow::class)->approve($listing, $this->islandAdmin($island));

        $this->assertSame(S::Live, $listing->fresh()->status);
    }

    public function test_affiliate_listings_do_not_need_one(): void
    {
        $listing = $this->completeListing(User::factory()->affiliate()->create(), null, ['min_price_week_cents' => null, 'deeplink_url' => 'https://b.example/1']);

        $this->assertNotContains('Add a From price per week.', ListingChecklist::missingForSubmit($listing));
    }

    public function test_owner_editor_requires_it_and_affiliates_may_leave_it_out(): void
    {
        $owner = User::factory()->owner()->create();
        $listing = $this->completeListing($owner);
        $basics = fn (array $o) => $o + [
            'title' => 'Villa', 'summary' => 'Nice.', 'island_id' => $listing->island_id, 'area_id' => $listing->area_id,
            'property_type' => 'villa', 'bedrooms' => 2, 'bathrooms' => 1, 'sleeps' => 4, 'pets' => 'not_stated', 'currency' => 'EUR',
        ];
        $listing->island->update(['is_active' => true]);

        $this->actingAs($owner)->get("/account/listings/{$listing->id}/edit/basics")->assertOk()->assertSee('From price per week');
        $this->actingAs($owner)->put("/account/listings/{$listing->id}/basics", $basics([]))->assertSessionHasErrors(['price' => 'Add your From price per week. Your listing cannot go live without one.']);
        $this->actingAs($owner)->put("/account/listings/{$listing->id}/basics", $basics(['price' => 0]))->assertSessionHasErrors('price');
        $this->actingAs($owner)->put("/account/listings/{$listing->id}/basics", $basics(['price' => 1100]))->assertSessionHasNoErrors();
        $this->assertSame(110000, $listing->fresh()->min_price_week_cents);

        $affiliate = User::factory()->affiliate()->create();
        $theirs = $this->completeListing($affiliate, $listing->island, ['deeplink_url' => 'https://b.example/1']);
        $this->actingAs($affiliate)->put("/account/listings/{$theirs->id}/basics", $basics(['island_id' => $theirs->island_id, 'area_id' => $theirs->area_id]))->assertSessionDoesntHaveErrors('price');
    }

    public function test_admin_add_a_property_requires_it(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.listings.create'))->assertOk()
            ->assertSee('From price per week')
            ->assertDontSee('Guide price per week (optional)');

        $this->actingAs($this->superAdmin())->post(route('admin.listings.store'), ['lister_email' => 'x@example.com'])
            ->assertSessionHasErrors(['price' => 'Add the From price per week. The property cannot go live without one.']);
    }
}
