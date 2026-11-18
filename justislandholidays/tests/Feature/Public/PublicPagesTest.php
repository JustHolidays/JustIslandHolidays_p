<?php

namespace Tests\Feature\Public;

use App\Enums\ListingStatus;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private Island $menorca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menorca = Island::factory()->create(['slug' => 'menorca', 'name' => ['en' => 'Menorca']]);
    }

    public function test_languages_not_yet_switched_on_return_404(): void
    {
        $this->get('/en')->assertOk();
        $this->get('/es')->assertNotFound();
        $this->get('/de/menorca')->assertNotFound();
        $this->get('/fr')->assertNotFound();
    }

    public function test_home_shows_active_islands_and_live_listings_only(): void
    {
        Island::factory()->create(['slug' => 'ibiza', 'name' => ['en' => 'Ibiza'], 'is_active' => false]);
        Listing::factory()->onIsland($this->menorca)->live()->titled('Live villa')->create();
        Listing::factory()->onIsland($this->menorca)->titled('Draft villa')->create();

        $this->get('/en')->assertOk()
            ->assertSee('Menorca')
            ->assertDontSee('Ibiza')
            ->assertSee('Live villa')
            ->assertDontSee('Draft villa');
    }

    public function test_island_page_lists_only_its_live_listings(): void
    {
        $mallorca = Island::factory()->create(['slug' => 'mallorca', 'name' => ['en' => 'Mallorca']]);
        Listing::factory()->onIsland($this->menorca)->live()->titled('Menorca home')->create();
        Listing::factory()->onIsland($mallorca)->live()->titled('Mallorca home')->create();
        Listing::factory()->onIsland($this->menorca)->status(ListingStatus::Paused)->titled('Paused home')->create();

        $this->get('/en/menorca')->assertOk()
            ->assertSee('Holiday homes in Menorca')
            ->assertSee('Menorca home')
            ->assertDontSee('Mallorca home')
            ->assertDontSee('Paused home');
    }

    public function test_inactive_island_and_unknown_area_return_404(): void
    {
        Island::factory()->create(['slug' => 'ibiza', 'is_active' => false]);

        $this->get('/en/ibiza')->assertNotFound();
        $this->get('/en/menorca/nowhere')->assertNotFound();
    }

    public function test_area_page_filters_to_the_resort(): void
    {
        $area = Area::factory()->create(['island_id' => $this->menorca->id, 'slug' => 'son-bou', 'name' => ['en' => 'Son Bou']]);
        $here = Listing::factory()->onIsland($this->menorca)->live()->titled('In Son Bou')->create(['area_id' => $area->id]);
        Listing::factory()->onIsland($this->menorca)->live()->titled('Elsewhere')->create();

        $this->get('/en/menorca/son-bou')->assertOk()
            ->assertSee('Holiday homes in Son Bou')
            ->assertSee('In Son Bou')
            ->assertDontSee('Elsewhere');
    }

    public function test_filtered_island_pages_are_noindex_unless_simple(): void
    {
        $this->get('/en/menorca?type[]=villa')->assertDontSee('noindex', false);
        $this->get('/en/menorca?bedrooms=3')->assertSee('noindex, follow', false);
        $this->get('/en/search')->assertSee('noindex, follow', false);
    }

    public function test_static_page_renders_markdown_safely(): void
    {
        $page = Page::factory()->create(['slug' => 'about', 'is_published' => true]);
        PageTranslation::create([
            'page_id' => $page->id, 'lang' => 'en', 'title' => 'About us',
            'body_md' => "We are **local**.\n\n<script>alert('x')</script>",
        ]);

        $this->get('/en/p/about')->assertOk()
            ->assertSee('<strong>local</strong>', false)
            ->assertDontSee("<script>alert('x')</script>", false);
    }

    public function test_unpublished_or_missing_page_returns_404(): void
    {
        Page::factory()->create(['slug' => 'draft', 'is_published' => false]);

        $this->get('/en/p/draft')->assertNotFound();
        $this->get('/en/p/missing')->assertNotFound();
    }

    public function test_owners_page_shows_plans_without_prices(): void
    {
        Plan::create(['code' => 'standard', 'name' => 'Standard', 'audience' => 'owner', 'max_listings' => 1, 'search_boost' => 10, 'show_direct_contact' => true]);

        $this->get('/en/owners')->assertOk()
            ->assertSee('Standard')
            ->assertSee('Price to be confirmed');
    }
}
