<?php

namespace Tests\Feature\Public;

use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    private function trail(string $html): string
    {
        $start = strpos($html, '<nav aria-label="Breadcrumb"');
        $this->assertNotFalse($start, 'There is a breadcrumb.');

        return substr($html, $start, strpos($html, '</nav>', $start) - $start);
    }

    public function test_resort_page_trail_is_home_island_resort_and_lines_up(): void
    {
        $island = Island::factory()->create(['slug' => 'menorca', 'name' => ['en' => 'Menorca'], 'is_active' => true]);
        $area = Area::factory()->create(['island_id' => $island->id, 'name' => ['en' => 'Cala Galdana']]);

        $html = $this->get(route('areas.show', ['lang' => 'en', 'island' => 'menorca', 'area' => $area->slug]))->assertOk()->getContent();
        $nav = $this->trail($html);

        $this->assertMatchesRegularExpression('#>Home</a>.*>Menorca</a>.*aria-current="page"[^>]*>Cala Galdana</span>#s', $nav);
        $this->assertSame(3, substr_count($nav, '<li class="flex min-h-11 items-center">'), 'Every part sits on the same 44-pixel row.');
        $this->assertSame(2, substr_count($nav, 'aria-hidden="true"'), 'A decorative separator between each part.');
        $this->assertStringNotContainsString('>/<', $nav);
        // The search engine trail keeps the full site name.
        $this->assertStringContainsString('"name":"Just Island Holidays"', $html);
    }

    public function test_listing_page_uses_the_same_trail(): void
    {
        $listing = Listing::factory()->live()->titled('Villa Mar')->create()->load(['island', 'area']);

        $nav = $this->trail($this->get($listing->publicUrl('en'))->assertOk()->getContent());

        $this->assertMatchesRegularExpression('#>Home</a>.*>'.preg_quote($listing->island->translate('name'), '#').'</a>.*>'.preg_quote($listing->area->translate('name'), '#').'</a>.*aria-current="page"[^>]*>Villa Mar</span>#s', $nav);
        $this->assertSame(4, substr_count($nav, '<li class="flex min-h-11 items-center">'));
    }
}
