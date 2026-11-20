<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Listings\Rates;
use App\Models\Listing;
use App\Models\ListingRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Rates table for paid plans, and the From price that follows it (20 Nov 2026). */
class RatesTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner, null, ['plan_code' => 'standard']), ListingStatus::Live)->load(['island', 'area']);
    }

    private function rate(string $start, string $end, ?int $night, ?int $week, ?int $fortnight = null, ?Listing $listing = null): ListingRate
    {
        $r = new ListingRate([
            'start_date' => $start, 'end_date' => $end,
            'nightly_cents' => $night, 'weekly_cents' => $week, 'fortnight_cents' => $fortnight,
        ]);
        $r->listing_id = ($listing ?? $this->listing)->id;
        $r->save();

        return $r;
    }

    private function add(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)
            ->from(route('account.listings.edit', [$this->listing, 'rates']))
            ->post(route('account.listings.rates.store', $this->listing), $data);
    }

    // --- Editor ---------------------------------------------------------------------------

    public function test_paid_plan_owner_sees_the_rates_step(): void
    {
        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'rates']))
            ->assertOk()->assertSee('Add a period')->assertSee('Per week (€)', false);
        $this->actingAs($this->owner)->get(route('account.listings.edit', $this->listing))
            ->assertSee(route('account.listings.edit', [$this->listing, 'rates']), false);
    }

    public function test_free_plan_has_no_rates_step(): void
    {
        $this->listing->forceFill(['plan_code' => 'free'])->save();

        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'rates']))->assertNotFound();
        $this->actingAs($this->owner)->get(route('account.listings.edit', $this->listing))
            ->assertDontSee(route('account.listings.edit', [$this->listing, 'rates']), false);
        $this->add(['start_date' => '2027-06-01', 'end_date' => '2027-06-30', 'weekly' => 900])->assertNotFound();
    }

    public function test_affiliate_listings_have_no_rates(): void
    {
        $affiliate = User::factory()->create(['role' => 'affiliate']);
        $listing = $this->completeListing($affiliate, null, ['plan_code' => 'standard']);

        $this->assertFalse($listing->hasRates());
        $this->actingAs($affiliate)->get(route('account.listings.edit', [$listing, 'rates']))->assertNotFound();
    }

    public function test_owner_adds_a_period_with_one_price(): void
    {
        $this->add(['start_date' => '2027-06-01', 'end_date' => '2027-06-30', 'weekly' => 900])
            ->assertRedirect()->assertSessionHasNoErrors();

        $rate = $this->listing->rates()->sole();
        $this->assertSame('2027-06-01', $rate->start_date->toDateString());
        $this->assertSame(90000, $rate->weekly_cents);
        $this->assertNull($rate->nightly_cents);
        $this->assertNull($rate->fortnight_cents);
        $this->assertDatabaseHas('audit_log', ['action' => 'rates.add']);
    }

    public function test_a_period_needs_at_least_one_price(): void
    {
        $this->add(['start_date' => '2027-06-01', 'end_date' => '2027-06-30'])->assertSessionHasErrors('nightly');
        $this->assertSame(0, $this->listing->rates()->count());
    }

    public function test_end_date_cannot_be_before_start_or_in_the_past(): void
    {
        $this->add(['start_date' => '2027-06-10', 'end_date' => '2027-06-01', 'weekly' => 900])->assertSessionHasErrors('end_date');
        $this->add(['start_date' => '2027-01-01', 'end_date' => '2027-02-01', 'weekly' => 900])->assertSessionHasErrors('end_date');
        $this->assertSame(0, $this->listing->rates()->count());
    }

    public function test_periods_cannot_overlap(): void
    {
        $this->rate('2027-06-01', '2027-06-30', null, 90000);

        $this->add(['start_date' => '2027-06-30', 'end_date' => '2027-07-15', 'weekly' => 1000])->assertSessionHasErrors('start_date');
        $this->add(['start_date' => '2027-07-01', 'end_date' => '2027-07-15', 'weekly' => 1000])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->listing->rates()->count());
    }

    public function test_owner_removes_a_period_but_not_someone_elses(): void
    {
        $mine = $this->rate('2027-06-01', '2027-06-30', null, 90000);
        $otherOwner = User::factory()->owner()->create();
        $other = $this->completeListing($otherOwner, null, ['plan_code' => 'standard']);
        $theirs = $this->rate('2027-06-01', '2027-06-30', null, 50000, null, $other);

        $this->actingAs($this->owner)->delete(route('account.listings.rates.destroy', [$this->listing, $theirs->id]))->assertNotFound();
        $this->actingAs($this->owner)->delete(route('account.listings.rates.destroy', [$other, $theirs->id]))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('account.listings.rates.destroy', [$this->listing, $mine->id]))->assertRedirect();

        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
    }

    // --- From price -----------------------------------------------------------------------

    public function test_from_price_is_the_lowest_weekly_rate_still_to_come(): void
    {
        $this->rate('2027-01-01', '2027-02-28', null, 40000);   // ended: ignored
        $this->add(['start_date' => '2027-07-01', 'end_date' => '2027-08-31', 'weekly' => 1500]);
        $this->add(['start_date' => '2027-05-01', 'end_date' => '2027-06-30', 'weekly' => 950, 'nightly' => 150]);
        $this->add(['start_date' => '2027-09-01', 'end_date' => '2027-09-30', 'nightly' => 90]);   // no weekly rate

        $this->assertSame(95000, $this->listing->fresh()->min_price_week_cents);
    }

    public function test_removing_the_cheapest_period_moves_the_from_price(): void
    {
        $this->add(['start_date' => '2027-05-01', 'end_date' => '2027-06-30', 'weekly' => 950]);
        $this->add(['start_date' => '2027-07-01', 'end_date' => '2027-08-31', 'weekly' => 1500]);
        $cheap = $this->listing->rates()->where('weekly_cents', 95000)->sole();

        $this->actingAs($this->owner)->delete(route('account.listings.rates.destroy', [$this->listing, $cheap->id]));

        $this->assertSame(150000, $this->listing->fresh()->min_price_week_cents);
    }

    public function test_without_weekly_rates_the_typed_from_price_stays(): void
    {
        $before = $this->listing->min_price_week_cents;
        $this->add(['start_date' => '2027-05-01', 'end_date' => '2027-06-30', 'nightly' => 120]);

        $this->assertSame($before, $this->listing->fresh()->min_price_week_cents);
    }

    public function test_basics_step_cannot_override_the_rates(): void
    {
        $this->rate('2027-05-01', '2027-06-30', null, 95000);
        $this->listing->refresh()->save();
        $this->assertSame(95000, $this->listing->min_price_week_cents);

        $this->actingAs($this->owner)->get(route('account.listings.edit', $this->listing))
            ->assertSee('Set from your', false)->assertDontSee('id="price"', false);

        $this->listing->fill(['min_price_week_cents' => 10000])->save();
        $this->assertSame(95000, $this->listing->fresh()->min_price_week_cents);
    }

    public function test_free_plan_rates_do_not_set_the_from_price(): void
    {
        $this->listing->forceFill(['plan_code' => 'free', 'min_price_week_cents' => 70000])->save();
        $this->rate('2027-05-01', '2027-06-30', null, 95000);   // kept from a paid plan

        $this->listing->refresh()->save();
        $this->assertSame(70000, $this->listing->fresh()->min_price_week_cents);
    }

    public function test_nightly_reconcile_moves_the_from_price_as_periods_end(): void
    {
        // The nightly job re-applies plans, so the owner needs a real subscription.
        (new \App\Models\Subscription())->forceFill([
            'user_id' => $this->owner->id,
            'plan_id' => \App\Models\Plan::where('code', 'standard')->value('id'),
            'billing_interval' => 'year',
            'status' => 'active',
            'next_billing_at' => now()->addYear(),
        ])->save();
        $this->rate('2027-03-01', '2027-04-30', null, 80000);
        $this->rate('2027-05-01', '2027-06-30', null, 95000);
        Rates::sync($this->listing->refresh());
        $this->assertSame(80000, $this->listing->fresh()->min_price_week_cents);

        $this->travelTo(CarbonImmutable::parse('2027-05-02 02:00'));
        $this->artisan('listings:reconcile')->assertSuccessful();

        $this->assertSame(95000, $this->listing->fresh()->min_price_week_cents);
    }

    // --- Property page --------------------------------------------------------------------

    public function test_property_page_shows_upcoming_rates_with_dashes_for_empty_prices(): void
    {
        $this->rate('2027-01-01', '2027-02-28', null, 40000);
        $this->rate('2027-05-01', '2027-06-30', 15000, 95000, null);

        $this->get($this->listing->publicUrl('en'))->assertOk()
            ->assertSee('id="rates"', false)
            ->assertSee('1 May 2027 to 30 Jun 2027')
            ->assertSee('€150')
            ->assertSee('€950')
            ->assertSee('Not offered')
            ->assertDontSee('1 Jan 2027 to 28 Feb 2027');
    }

    public function test_property_page_hides_rates_on_free_plan(): void
    {
        $this->rate('2027-05-01', '2027-06-30', null, 95000);
        $this->listing->forceFill(['plan_code' => 'free'])->save();

        $this->get($this->listing->publicUrl('en'))->assertOk()->assertDontSee('id="rates"', false);
    }

    public function test_property_page_without_rates_has_no_table(): void
    {
        $this->get($this->listing->publicUrl('en'))->assertOk()->assertDontSee('id="rates"', false);
    }
}
