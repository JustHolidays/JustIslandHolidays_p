<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Arrivals any day or on a fixed changeover day, set in the Calendar step (20 Nov 2026). */
class ChangeoverDayTest extends TestCase
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
        \App\Models\Plan::whereIn('code', ['standard', 'featured', 'agent_plus'])->update(['has_booking_calendar' => true]);
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner, null, ['plan_code' => 'standard']), ListingStatus::Live)->load(['island', 'area']);
    }

    private function save(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)
            ->from(route('account.listings.edit', [$this->listing, 'calendar']))
            ->put(route('account.listings.calendar.changeover', $this->listing), $data);
    }

    private function rate(string $start, string $end, ?int $night, ?int $week): void
    {
        $r = new ListingRate(['start_date' => $start, 'end_date' => $end, 'nightly_cents' => $night, 'weekly_cents' => $week]);
        $r->listing_id = $this->listing->id;
        $r->save();
    }

    public function test_calendar_step_offers_the_choice(): void
    {
        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'calendar']))->assertOk()
            ->assertSee('When can guests arrive?')
            ->assertSee('Any day of the week')
            ->assertSee('Not chosen yet.')
            ->assertSee('<option value="6" selected', false);
    }

    public function test_a_fixed_changeover_day_shows_above_the_calendar(): void
    {
        $this->save(['arrivals' => 'fixed', 'changeover_day' => 6])->assertSessionHasNoErrors();
        $this->assertSame(6, $this->listing->fresh()->changeover_day);
        $this->assertDatabaseHas('audit_log', ['action' => 'calendar.changeover']);

        $this->get($this->listing->publicUrl('en'))->assertOk()
            ->assertSeeInOrder(['id="availability"', 'Changeover day: Saturday', 'data-day='], false);
        $this->get(route('calendar.embed', $this->listing->public_ref))->assertOk()->assertSee('Changeover day: Saturday');
    }

    public function test_any_day_or_not_chosen_shows_no_changeover_day(): void
    {
        $this->get($this->listing->publicUrl('en'))->assertOk()->assertDontSee('data-changeover', false);

        $this->save(['arrivals' => 'any'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->listing->fresh()->changeover_day);
        $this->get($this->listing->publicUrl('en'))->assertOk()->assertDontSee('data-changeover', false);
    }

    public function test_choices_are_checked(): void
    {
        $this->save([])->assertSessionHasErrors('arrivals');
        $this->save(['arrivals' => 'fixed'])->assertSessionHasErrors('changeover_day');
        $this->save(['arrivals' => 'fixed', 'changeover_day' => 8])->assertSessionHasErrors('changeover_day');
        $this->save(['arrivals' => 'fixed', 'changeover_day' => 0])->assertSessionHasErrors('changeover_day');
        $this->assertNull($this->listing->fresh()->changeover_day);
    }

    public function test_any_day_is_blocked_while_a_period_still_to_come_has_no_nightly_rate(): void
    {
        $this->rate('2027-01-01', '2027-02-28', null, 50000);   // ended: does not count
        $this->rate('2027-06-01', '2027-06-30', null, 90000);

        $this->save(['arrivals' => 'any'])->assertSessionHasErrors('arrivals');
        $this->assertNull($this->listing->fresh()->changeover_day);
        $this->assertStringContainsString('1 Jun 2027 to 30 Jun 2027', session('errors')->first('arrivals'));

        ListingRate::where('start_date', '2027-06-01')->update(['nightly_cents' => 15000]);
        $this->save(['arrivals' => 'any'])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->listing->fresh()->changeover_day);
    }

    public function test_with_any_day_new_rate_periods_need_a_nightly_rate(): void
    {
        $this->save(['arrivals' => 'any']);
        $add = fn (array $d) => $this->actingAs($this->owner)->post(route('account.listings.rates.store', $this->listing), $d + ['start_date' => '2027-06-01', 'end_date' => '2027-06-30']);

        $add(['weekly' => 900])->assertSessionHasErrors('nightly');
        $add(['nightly' => 150, 'weekly' => 900])->assertSessionHasNoErrors();

        $this->save(['arrivals' => 'fixed', 'changeover_day' => 6]);
        $this->actingAs($this->owner)->post(route('account.listings.rates.store', $this->listing), ['start_date' => '2027-07-01', 'end_date' => '2027-07-31', 'weekly' => 1200])
            ->assertSessionHasNoErrors();
    }

    public function test_paid_plan_bookmark_menu_includes_rates_and_availability(): void
    {
        $this->rate('2027-06-01', '2027-06-30', 15000, 90000);

        $this->get($this->listing->publicUrl('en'))->assertOk()
            ->assertSeeInOrder(['data-bookmarks', 'href="#key-facts"', 'href="#rates"', 'href="#availability"', 'href="#location"', 'id="key-facts"', 'id="rates"', 'id="availability"', 'id="location"'], false);
    }

    public function test_free_plan_and_other_owners_cannot_set_it(): void
    {
        $other = User::factory()->owner()->create();
        $this->actingAs($other)->put(route('account.listings.calendar.changeover', $this->listing), ['arrivals' => 'any'])->assertForbidden();

        $this->listing->forceFill(['plan_code' => 'free'])->save();
        $this->save(['arrivals' => 'any'])->assertNotFound();
        $this->assertNull($this->listing->fresh()->changeover_day);
    }
}
