<?php

namespace Tests\Feature\Database;

use App\Models\Amenity;
use App\Models\Island;
use App\Models\Listing;
use Database\Seeders\AmenitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "Electric car charging" was removed from the property facilities (19 Nov 2026). */
class RemovedAmenityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_no_longer_adds_it(): void
    {
        $this->seed(AmenitySeeder::class);

        $this->assertFalse(Amenity::where('code', 'ev_charging')->exists());
        $this->assertTrue(Amenity::where('code', 'wifi')->exists());
    }

    public function test_the_migration_removes_it_from_listings_and_the_list(): void
    {
        $ev = Amenity::create(['code' => 'ev_charging', 'category' => 'services', 'name' => ['en' => 'Electric car charging'], 'is_filterable' => true, 'sort_order' => 1]);
        $wifi = Amenity::create(['code' => 'wifi', 'category' => 'essentials', 'name' => ['en' => 'Wi-Fi'], 'is_filterable' => true, 'sort_order' => 2]);
        $listing = Listing::factory()->create();
        $listing->amenities()->attach([$ev->id, $wifi->id]);

        (require database_path('migrations/2026_11_19_000003_remove_ev_charging_amenity.php'))->up();

        $this->assertNull(Amenity::find($ev->id));
        $this->assertSame([$wifi->id], DB::table('listing_amenities')->where('listing_id', $listing->id)->pluck('amenity_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_an_old_search_link_with_it_still_shows_results(): void
    {
        $island = Island::factory()->create(['is_active' => true]);
        Listing::factory()->live()->onIsland($island)->titled('Still here')->create();

        $this->get('/en/search?amenity[]=ev_charging')->assertOk()->assertSee('Still here');
    }
}
