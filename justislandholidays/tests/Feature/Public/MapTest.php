<?php

namespace Tests\Feature\Public;

use App\Enums\ListingStatus;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingPrivate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    private function live(array $attributes = [], string $title = 'Villa Sol'): Listing
    {
        return Listing::factory()->live()->titled($title)->create($attributes)->load(['island', 'area']);
    }

    private function withExactLocation(Listing $listing): Listing
    {
        $private = new ListingPrivate(['lat' => 39.912345, 'lng' => 4.165432, 'contact_email' => 'x@example.com']);
        $private->listing_id = $listing->id;
        $private->save();

        return $listing;
    }

    // --- Listing page ----------------------------------------------------------------

    public function test_listing_page_has_an_area_map_on_the_public_point_only(): void
    {
        $listing = $this->withExactLocation($this->live(['display_lat' => 39.915, 'display_lng' => 4.162]));

        $this->get($listing->publicUrl('en'))->assertOk()
            ->assertSee('data-map="area"', false)
            ->assertSee('data-lat="39.915', false)
            ->assertSee('data-lng="4.162', false)
            ->assertSee('You get the exact address when you book.')
            ->assertDontSee('39.912345')
            ->assertDontSee('4.165432')
            ->assertDontSee('data-exact-lat', false);
    }

    public function test_affiliate_listing_page_also_has_the_map(): void
    {
        $listing = $this->live(['owner_id' => User::factory()->affiliate()]);

        $this->get($listing->publicUrl('en'))->assertOk()->assertSee('data-map="area"', false);
    }

    public function test_admin_review_shows_the_exact_location_on_the_map(): void
    {
        $listing = $this->withExactLocation($this->live());

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.listings.show', $listing))->assertOk()
            ->assertSee('data-map="area"', false)
            ->assertSee('data-exact-lat="39.912345"', false);
    }

    // --- Island, resort and search pages ----------------------------------------------

    public function test_island_and_resort_pages_have_a_pins_map_for_that_place(): void
    {
        $listing = $this->live();

        $this->get(route('islands.show', ['lang' => 'en', 'island' => $listing->island->slug]))->assertOk()
            ->assertSee('data-map="pins"', false)
            ->assertSee('island='.$listing->island->slug, false);

        $this->get(route('areas.show', ['lang' => 'en', 'island' => $listing->island->slug, 'area' => $listing->area->slug]))->assertOk()
            ->assertSee('data-map="pins"', false)
            ->assertSee('area='.$listing->area->slug, false);
    }

    public function test_search_switches_between_list_and_map(): void
    {
        $this->live([], 'Casa Azul');

        $this->get(route('search', ['lang' => 'en']))->assertOk()
            ->assertSee('Casa Azul')
            ->assertDontSee('data-map="pins"', false);

        $this->get(route('search', ['lang' => 'en', 'view' => 'map', 'pool' => 1]))->assertOk()
            ->assertSee('data-map="pins"', false)
            ->assertSee('pool=1', false)
            ->assertSee('name="view" value="map"', false)
            ->assertSee('aria-current="page"', false);
    }

    // --- /api/map ---------------------------------------------------------------------

    public function test_pins_api_returns_live_listings_in_the_box_with_public_data_only(): void
    {
        $inside = $this->withExactLocation($this->live(['display_lat' => 39.95, 'display_lng' => 4.05, 'min_price_week_cents' => 90000], 'Inside'));
        $this->live(['display_lat' => 39.50, 'display_lng' => 2.70], 'Outside');
        Listing::factory()->status(ListingStatus::PendingReview)->titled('Waiting')->create(['display_lat' => 39.95, 'display_lng' => 4.05]);

        $response = $this->getJson('/api/map?lang=en&bbox=39.8,3.8,40.1,4.4')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertHeaderMissing('Set-Cookie')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('more', false)
            ->assertJsonPath('pins.0.title', 'Inside')
            ->assertJsonPath('pins.0.ref', $inside->public_ref)
            ->assertJsonPath('pins.0.price', '€900')
            ->assertJsonPath('pins.0.url', $inside->publicUrl('en'))
            ->assertJsonPath('pins.0.lat', 39.95);

        $this->assertSame(['ref', 'title', 'place', 'price', 'image', 'url', 'lat', 'lng'], array_keys($response->json('pins.0')));
        $this->assertStringNotContainsString('39.912345', $response->getContent());
        $this->assertStringNotContainsString('x@example.com', $response->getContent());
    }

    public function test_pins_api_applies_the_search_filters(): void
    {
        $island = Island::factory()->create();
        $this->live(['island_id' => $island->id, 'has_private_pool' => true], 'Pool');
        $this->live(['island_id' => $island->id, 'has_private_pool' => false], 'No pool');
        $this->live([], 'Other island');

        $this->getJson("/api/map?island={$island->slug}&pool=1")->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('pins.0.title', 'Pool');
    }

    public function test_pins_api_caps_the_number_of_pins(): void
    {
        config(['site.map.max_pins' => 2]);
        foreach (range(1, 3) as $i) {
            $this->live([], "Home {$i}");
        }

        $this->getJson('/api/map')->assertOk()
            ->assertJsonCount(2, 'pins')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('more', true);
    }

    public function test_pins_api_ignores_a_bad_box_and_language(): void
    {
        $this->live();

        $this->getJson('/api/map?lang=xx&bbox=40,4,39,3')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/map?bbox=not-a-box')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_pins_api_hides_listings_on_switched_off_islands(): void
    {
        $listing = $this->live();
        $listing->island->update(['is_active' => false]);

        $this->getJson('/api/map')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_privacy_notice_explains_the_map_provider(): void
    {
        $this->seed(\Database\Seeders\LegalPagesSeeder::class);

        $this->get('/en/p/privacy')->assertOk()->assertSee('>Maps</h2>', false)->assertSee('It sets no cookies.');
    }
}
