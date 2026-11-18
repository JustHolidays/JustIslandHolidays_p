<?php

namespace Tests\Feature\Public;

use App\Models\Amenity;
use App\Models\Island;
use App\Models\Listing;
use App\Search\ListingSearch;
use App\Search\SearchFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ListingSearchTest extends TestCase
{
    use RefreshDatabase;

    private Island $island;

    protected function setUp(): void
    {
        parent::setUp();

        $this->island = Island::factory()->create();
    }

    private function search(array $query): \App\Search\SearchResult
    {
        return app(ListingSearch::class)->run(SearchFilters::fromRequest(Request::create('/en/search', 'GET', $query)), 'en');
    }

    private function titles(\App\Search\SearchResult $result): array
    {
        return $result->listings->getCollection()->map->title()->sort()->values()->all();
    }

    private function make(string $title, array $attributes = []): Listing
    {
        return Listing::factory()->onIsland($this->island)->live()->titled($title)->create($attributes);
    }

    public function test_only_live_listings_on_active_islands(): void
    {
        $this->make('Live');
        Listing::factory()->onIsland($this->island)->titled('Draft')->create();
        $closed = Island::factory()->create(['is_active' => false]);
        Listing::factory()->onIsland($closed)->live()->titled('Closed island')->create();
        $this->make('Deleted')->delete();

        $this->assertSame(['Live'], $this->titles($this->search([])));
    }

    public function test_type_sleeps_bedrooms_pool_and_pets_filters(): void
    {
        $this->make('Big villa', ['property_type' => 'villa', 'sleeps' => 10, 'bedrooms' => 5, 'has_private_pool' => true, 'pets_allowed' => true]);
        $this->make('Small flat', ['property_type' => 'apartment', 'sleeps' => 2, 'bedrooms' => 1]);
        $this->make('Pets unknown', ['property_type' => 'villa', 'sleeps' => 8, 'bedrooms' => 4, 'has_private_pool' => true, 'pets_allowed' => null]);

        $this->assertSame(['Big villa', 'Pets unknown'], $this->titles($this->search(['type' => ['villa']])));
        $this->assertSame(['Big villa'], $this->titles($this->search(['sleeps' => 9])));
        $this->assertSame(['Big villa', 'Pets unknown'], $this->titles($this->search(['bedrooms' => 4])));
        $this->assertSame(['Big villa', 'Pets unknown'], $this->titles($this->search(['pool' => 1])));
        $this->assertSame(['Big villa'], $this->titles($this->search(['pets' => 1])), 'Unknown pets do not match.');
    }

    public function test_price_range_and_price_sort(): void
    {
        $this->make('Cheap', ['min_price_week_cents' => 50000]);
        $this->make('Dear', ['min_price_week_cents' => 300000]);
        $this->make('No price', ['min_price_week_cents' => null]);

        $this->assertSame(['Cheap'], $this->titles($this->search(['price_max' => 1000])));
        $this->assertSame(['Dear'], $this->titles($this->search(['price_min' => 1000])));

        $sorted = $this->search(['sort' => 'price_asc'])->listings->getCollection()->map->title()->all();
        $this->assertSame(['Cheap', 'Dear', 'No price'], $sorted, 'Listings without a price come last.');
    }

    public function test_all_selected_amenities_must_match(): void
    {
        $wifi = Amenity::create(['code' => 'wifi', 'category' => 'essentials', 'name' => ['en' => 'Wi-Fi']]);
        $bbq = Amenity::create(['code' => 'bbq', 'category' => 'outdoor', 'name' => ['en' => 'Barbecue']]);
        $this->make('Both')->amenities()->sync([$wifi->id, $bbq->id]);
        $this->make('Wifi only')->amenities()->sync([$wifi->id]);

        $this->assertSame(['Both', 'Wifi only'], $this->titles($this->search(['amenity' => ['wifi']])));
        $this->assertSame(['Both'], $this->titles($this->search(['amenity' => ['wifi', 'bbq']])));
    }

    public function test_text_search_matches_title_prefix_and_words(): void
    {
        $this->make('Villa Sa Punta');
        Listing::factory()->onIsland($this->island)->live()->titled('Casa Sol', 'Quiet home near the harbour')->create();

        $this->assertSame(['Villa Sa Punta'], $this->titles($this->search(['q' => 'Villa Sa'])));
        $this->assertSame([], $this->titles($this->search(['q' => 'castle'])));

        // On MySQL the word match uses FULLTEXT, which cannot see uncommitted rows;
        // MySqlFullTextTest covers it there with committed data.
        if (! \App\Support\Database\MySql::isMySql()) {
            $this->assertSame(['Casa Sol'], $this->titles($this->search(['q' => 'harbour'])));
        }
    }

    public function test_type_counts_ignore_the_type_filter(): void
    {
        $this->make('V1', ['property_type' => 'villa']);
        $this->make('V2', ['property_type' => 'villa']);
        $this->make('A1', ['property_type' => 'apartment']);

        $result = $this->search(['type' => ['villa']]);

        $this->assertSame(2, $result->total());
        $this->assertSame(['apartment' => 1, 'villa' => 2], collect($result->typeCounts)->sortKeys()->all());
    }

    public function test_featured_listings_are_capped_per_page_and_rotate(): void
    {
        foreach (range(1, 5) as $i) {
            Listing::factory()->onIsland($this->island)->live()->featured()->titled("Featured {$i}")->create(['rank_score' => 100 - $i]);
        }
        $this->make('Ordinary');

        $page1 = $this->search([])->featured->map->title()->all();
        $page2 = $this->search(['page' => 2])->featured->map->title()->all();

        $this->assertCount(3, $page1);
        $this->assertSame(['Featured 1', 'Featured 2', 'Featured 3'], $page1);
        $this->assertSame(['Featured 4', 'Featured 5', 'Featured 1'], $page2);
    }

    public function test_expired_featured_period_is_not_featured(): void
    {
        $this->make('Expired', ['featured_until' => now()->subDay()]);

        $this->assertCount(0, $this->search([])->featured);
    }

    public function test_bad_query_values_are_ignored(): void
    {
        $this->make('Any');

        $result = $this->search(['sleeps' => 'lots', 'type' => ['castle'], 'sort' => 'drop table', 'amenity' => ['<script>']]);

        $this->assertSame(1, $result->total());
    }
}
