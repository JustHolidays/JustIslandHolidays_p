<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingRate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/**
 * Admins edit owner and agent properties fully (20 Nov 2026): the owner's editor steps,
 * including photos, calendar and rates, in the admin area.
 */
class AdminPropertyEditorTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private Island $island;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        $this->seed(\Database\Seeders\PlanSeeder::class);
        \App\Models\Plan::whereIn('code', ['standard', 'featured', 'agent_plus'])->update(['has_booking_calendar' => true]);
        Storage::fake('public');
        Storage::fake('local');
        Queue::fake();
        $this->island = Island::factory()->create();
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner, $this->island, ['plan_code' => 'standard']), ListingStatus::Live)->load(['island', 'area']);
    }

    private function url(string $step = 'basics'): string
    {
        return route('admin.listings.editor.edit', [$this->listing, $step]);
    }

    public function test_super_admin_opens_every_editing_step_in_the_admin_area(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.listings.show', $this->listing))->assertOk()
            ->assertSee('Edit the whole property')->assertSee(route('admin.listings.editor.edit', $this->listing), false);

        foreach (['basics', 'location', 'photo', 'link', 'calendar', 'bookings', 'rates'] as $step) {
            $this->actingAs($admin)->get($this->url($step))->assertOk()
                ->assertSee('data-admin-editor', false)
                ->assertSee('You are editing this property for')
                ->assertDontSee('route(\'account.', false)
                ->assertDontSee('/account/listings/', false);
        }

        // The current step is a solid button in the admin area too, which has no site brand colour.
        $this->actingAs($admin)->get($this->url('rates'))
            ->assertSee('bg-[var(--colour-brand,#0f172a)] text-white', false)->assertSee('aria-current="step"', false);

        // Admins have no Licence or Review step (they have the Licence checks and review pages).
        $this->actingAs($admin)->get(route('admin.listings.editor.edit', [$this->listing, 'basics']))
            ->assertDontSee('Review and send')->assertDontSee('>5. Licence<', false);
        $this->get('/admin/listings/'.$this->listing->id.'/editor/licence')->assertNotFound();
    }

    public function test_admin_changes_details_and_stays_in_the_admin_area(): void
    {
        $admin = $this->superAdmin();
        $data = [
            'title' => 'Villa Renamed', 'summary' => 'New words', 'island_id' => $this->listing->island_id, 'area_id' => $this->listing->area_id,
            'property_type' => $this->listing->property_type, 'bedrooms' => 5, 'bathrooms' => 3, 'sleeps' => 10,
            'pets' => 'no', 'price' => 2000, 'currency' => 'EUR',
        ];

        $this->actingAs($admin)->put(route('admin.listings.editor.basics', $this->listing), $data)
            ->assertSessionHasNoErrors()->assertRedirect($this->url('location'));

        $fresh = $this->listing->fresh();
        $this->assertSame('Villa Renamed', $fresh->title('en'));
        $this->assertSame(5, $fresh->bedrooms);
        $this->assertSame(ListingStatus::Live, $fresh->status, 'An admin edit does not change the status.');
        $this->assertDatabaseHas('audit_log', ['action' => 'listing.update', 'actor_id' => $admin->id]);
    }

    public function test_admin_edits_photos_calendar_and_rates(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('admin.listings.editor.photos.store', $this->listing), [
            'extra_photo' => UploadedFile::fake()->image('pool.jpg', 1200, 800), 'extra_alt' => 'The pool', 'extra_permission' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->listing->photos()->count());

        $this->actingAs($admin)->from($this->url('calendar'))->post(route('admin.listings.editor.calendar.bookings.store', $this->listing), ['check_in' => '2027-06-10', 'check_out' => '2027-06-17'])
            ->assertSessionHasNoErrors()->assertRedirect($this->url('calendar'));
        $this->assertSame(1, $this->listing->calendarBookings()->count());

        $this->actingAs($admin)->put(route('admin.listings.editor.calendar.changeover', $this->listing), ['arrivals' => 'fixed', 'changeover_day' => 6])->assertSessionHasNoErrors();
        $this->assertSame(6, $this->listing->fresh()->changeover_day);

        $this->actingAs($admin)->from($this->url('rates'))->post(route('admin.listings.editor.rates.store', $this->listing), ['start_date' => '2027-06-01', 'end_date' => '2027-06-30', 'weekly' => 900])
            ->assertSessionHasNoErrors()->assertRedirect($this->url('rates'));
        $rate = ListingRate::where('listing_id', $this->listing->id)->sole();
        $this->assertSame(90000, $this->listing->fresh()->min_price_week_cents);

        $this->actingAs($admin)->delete(route('admin.listings.editor.rates.destroy', [$this->listing, $rate->id]))->assertRedirect();
        $this->assertModelMissing($rate);
        $this->assertDatabaseHas('audit_log', ['action' => 'rates.add', 'actor_id' => $admin->id]);
    }

    public function test_admin_can_edit_a_property_waiting_for_review(): void
    {
        $this->listing->forceFill(['status' => ListingStatus::PendingReview])->save();

        $this->actingAs($this->superAdmin())->put(route('admin.listings.editor.location', $this->listing), [])
            ->assertSessionDoesntHaveErrors('status');
    }

    public function test_island_admin_edits_own_island_only(): void
    {
        $own = $this->islandAdmin($this->island);
        $other = $this->islandAdmin(Island::factory()->create());

        $this->actingAs($own)->get($this->url('rates'))->assertOk();
        $this->actingAs($other)->get($this->url('rates'))->assertForbidden();
        $this->actingAs($other)->post(route('admin.listings.editor.rates.store', $this->listing), ['start_date' => '2027-06-01', 'end_date' => '2027-06-30', 'weekly' => 900])->assertForbidden();
        $this->actingAs($other)->put(route('admin.listings.editor.calendar.changeover', $this->listing), ['arrivals' => 'any'])->assertForbidden();
        $this->assertSame(0, ListingRate::count());
    }

    public function test_island_admin_cannot_move_a_property_to_another_island(): void
    {
        $own = $this->islandAdmin($this->island);
        $elsewhere = Island::factory()->create();
        $area = \App\Models\Area::factory()->create(['island_id' => $elsewhere->id]);

        $this->actingAs($own)->put(route('admin.listings.editor.basics', $this->listing), [
            'title' => 'Moved', 'summary' => 'x', 'island_id' => $elsewhere->id, 'area_id' => $area->id,
            'property_type' => $this->listing->property_type, 'bedrooms' => 2, 'bathrooms' => 1, 'sleeps' => 4,
            'pets' => 'no', 'price' => 900, 'currency' => 'EUR',
        ])->assertSessionHasErrors('island_id');
        $this->assertSame($this->island->id, $this->listing->fresh()->island_id);
    }

    public function test_affiliate_properties_and_non_admins_are_refused(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $theirs = $this->completeListing($affiliate, $this->island);
        $this->actingAs($this->superAdmin())->get(route('admin.listings.editor.edit', $theirs))->assertForbidden();

        $this->actingAs($this->owner)->get($this->url())->assertForbidden();
    }
}
