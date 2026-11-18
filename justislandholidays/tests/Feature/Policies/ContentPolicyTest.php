<?php

namespace Tests\Feature\Policies;

use App\Models\Ad;
use App\Models\AdminRecommendation;
use App\Models\AdSlot;
use App\Models\Advertiser;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pages, ads, islands and resorts, and recommendations. */
class ContentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Island $island;

    private Island $otherIsland;

    protected function setUp(): void
    {
        parent::setUp();

        $this->island = Island::factory()->create();
        $this->otherIsland = Island::factory()->create();
    }

    private function ad(?int $islandId): Ad
    {
        $advertiser = Advertiser::create(['name' => 'Car hire']);
        $slot = AdSlot::create(['code' => 'slot-'.uniqid(), 'width' => 300, 'height' => 250]);

        return Ad::create([
            'advertiser_id' => $advertiser->id,
            'slot_id' => $slot->id,
            'island_id' => $islandId,
            'image_path' => 'ads/a.webp',
            'alt' => 'Car hire offer',
            'target_url' => 'https://example.com',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_pages(): void
    {
        $admin = $this->islandAdmin($this->island);
        $guide = Page::factory()->create(['island_id' => $this->island->id, 'kind' => 'guide']);
        $otherGuide = Page::factory()->create(['island_id' => $this->otherIsland->id, 'kind' => 'guide']);
        $siteWide = Page::factory()->create(['island_id' => null]);

        $this->assertTrue($admin->can('update', $guide));
        $this->assertFalse($admin->can('update', $otherGuide));
        $this->assertFalse($admin->can('update', $siteWide));
        $this->assertTrue($admin->can('create', [Page::class, $this->island->id]));
        $this->assertFalse($admin->can('create', [Page::class, $this->otherIsland->id]));
        $this->assertFalse($admin->can('create', [Page::class, null]));
        $this->assertTrue($this->superAdmin()->can('update', $siteWide));
        $this->assertFalse(User::factory()->owner()->create()->can('update', $guide));
    }

    public function test_ads(): void
    {
        $admin = $this->islandAdmin($this->island);

        $this->assertTrue($admin->can('update', $this->ad($this->island->id)));
        $this->assertFalse($admin->can('update', $this->ad($this->otherIsland->id)));
        $this->assertFalse($admin->can('update', $this->ad(null)), 'All-island ads are super admin only.');
        $this->assertFalse($admin->can('create', [Ad::class, null]));
        $this->assertTrue($this->superAdmin()->can('update', $this->ad(null)));
    }

    public function test_islands_and_resorts(): void
    {
        $admin = $this->islandAdmin($this->island);
        $area = Area::factory()->create(['island_id' => $this->island->id]);
        $otherArea = Area::factory()->create(['island_id' => $this->otherIsland->id]);
        $super = $this->superAdmin();

        $this->assertTrue($admin->can('updateText', $this->island));
        $this->assertFalse($admin->can('updateText', $this->otherIsland));
        $this->assertFalse($admin->can('manage', $this->island));
        $this->assertTrue($admin->can('updateText', $area));
        $this->assertFalse($admin->can('updateText', $otherArea));
        $this->assertFalse($admin->can('manage', $area));
        $this->assertTrue($super->can('manage', $this->island));
        $this->assertTrue($super->can('manage', $area));
    }

    public function test_only_super_admin_decides_recommendations(): void
    {
        $admin = $this->islandAdmin($this->island);
        $listing = Listing::factory()->onIsland($this->island)->create();

        $rec = new AdminRecommendation([
            'action' => 'reject_listing',
            'listing_id' => $listing->id,
            'island_id' => $this->island->id,
            'reason' => 'Photos do not match the property.',
        ]);
        $rec->recommended_by = $admin->id;
        $rec->save();
        $rec = $rec->fresh();

        $this->assertTrue($admin->can('view', $rec));
        $this->assertFalse($admin->can('decide', $rec));
        $this->assertFalse($this->islandAdmin($this->otherIsland)->can('view', $rec));
        $this->assertTrue($this->superAdmin()->can('decide', $rec));

        $rec->forceFill(['status' => 'accepted'])->save();
        $this->assertFalse($this->superAdmin()->can('decide', $rec->fresh()), 'Already decided.');
    }
}
