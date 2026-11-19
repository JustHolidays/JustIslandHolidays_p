<?php

namespace Tests\Feature\Public;

use App\Models\Island;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_price_is_an_orange_bar_at_the_foot_of_the_card(): void
    {
        $island = Island::factory()->create(['is_active' => true]);
        $priced = Listing::factory()->live()->onIsland($island)->titled('Priced home')->create(['min_price_week_cents' => 95000])->load(['island', 'area']);
        Listing::factory()->live()->onIsland($island)->titled('No price home')->create(['min_price_week_cents' => null]);

        foreach ([
            route('islands.show', ['lang' => 'en', 'island' => $island->slug]),
            route('areas.show', ['lang' => 'en', 'island' => $island->slug, 'area' => $priced->area->slug]),
            route('search', ['lang' => 'en']),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<p class="bg-orange-700 px-4 py-2 font-semibold text-white" data-card-price>From €950 a week</p>', $html, $url);
        }

        // A home without a guide price has no bar.
        $this->assertSame(1, substr_count($this->get(route('search', ['lang' => 'en']))->getContent(), 'data-card-price'));
    }
}
