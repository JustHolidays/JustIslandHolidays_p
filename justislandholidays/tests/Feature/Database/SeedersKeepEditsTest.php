<?php

namespace Tests\Feature\Database;

use App\Models\Amenity;
use App\Models\Area;
use App\Models\Island;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * deploy.sh runs the seeders on every deploy (20 Nov 2026): they add missing reference data
 * but must never undo changes made in the admin.
 */
class SeedersKeepEditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_again_keeps_admin_changes(): void
    {
        $this->seed();

        $ibiza = Island::where('slug', 'ibiza')->firstOrFail();
        $ibiza->forceFill(['is_active' => true, 'sort_order' => 9, 'name' => ['en' => 'Eivissa']])->save();
        $area = Area::firstOrFail();
        $area->forceFill(['name' => ['en' => 'Renamed resort'], 'sort_order' => 42])->save();
        $amenity = Amenity::firstOrFail();
        $amenity->forceFill(['name' => ['en' => 'Renamed facility'], 'is_filterable' => ! $amenity->is_filterable])->save();
        $counts = [Island::count(), Area::count(), Amenity::count()];

        $this->seed();

        $this->assertTrue($ibiza->fresh()->is_active, 'Switching an island on survives a deploy.');
        $this->assertSame(9, (int) $ibiza->fresh()->sort_order);
        $this->assertSame('Eivissa', $ibiza->fresh()->translate('name', 'en'));
        $this->assertSame('Renamed resort', $area->fresh()->translate('name', 'en'));
        $this->assertSame(42, (int) $area->fresh()->sort_order);
        $this->assertSame('Renamed facility', $amenity->fresh()->translate('name', 'en'));
        $this->assertSame($counts, [Island::count(), Area::count(), Amenity::count()], 'Nothing is added twice.');
    }

    public function test_missing_reference_data_is_added(): void
    {
        $this->seed();
        Amenity::where('code', 'wheelchair_friendly')->delete();

        $this->seed();

        $this->assertTrue(Amenity::where('code', 'wheelchair_friendly')->exists());
    }
}
