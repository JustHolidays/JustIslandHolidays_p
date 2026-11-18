<?php

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use App\Models\Island;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An island admin opening another island's record by URL must be refused (403),
 * not shown an empty page.
 */
class IslandScopeByUrlTest extends TestCase
{
    use RefreshDatabase;

    private Island $menorca;

    private Island $mallorca;

    private User $menorcaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menorca = Island::factory()->create();
        $this->mallorca = Island::factory()->create();
        $this->menorcaAdmin = $this->islandAdmin($this->menorca);
    }

    public function test_listing_on_another_island_is_refused(): void
    {
        $own = Listing::factory()->onIsland($this->menorca)->create();
        $other = Listing::factory()->onIsland($this->mallorca)->create();

        $this->actingAs($this->menorcaAdmin)->get("/admin/listings/{$own->id}")->assertOk();
        $this->actingAs($this->menorcaAdmin)->get("/admin/listings/{$other->id}")->assertForbidden();
    }

    public function test_enquiry_on_another_island_is_refused(): void
    {
        $own = Enquiry::factory()->for(Listing::factory()->onIsland($this->menorca))->create();
        $other = Enquiry::factory()->for(Listing::factory()->onIsland($this->mallorca))->create();

        $this->actingAs($this->menorcaAdmin)->get("/admin/enquiries/{$own->id}")->assertOk();
        $this->actingAs($this->menorcaAdmin)->get("/admin/enquiries/{$other->id}")->assertForbidden();
    }

    public function test_page_on_another_island_or_site_wide_is_refused(): void
    {
        $own = Page::factory()->create(['island_id' => $this->menorca->id, 'kind' => 'guide']);
        $other = Page::factory()->create(['island_id' => $this->mallorca->id, 'kind' => 'guide']);
        $siteWide = Page::factory()->create(['island_id' => null]);

        $this->actingAs($this->menorcaAdmin)->get("/admin/pages/{$own->id}/edit")->assertOk();
        $this->actingAs($this->menorcaAdmin)->get("/admin/pages/{$other->id}/edit")->assertForbidden();
        $this->actingAs($this->menorcaAdmin)->get("/admin/pages/{$siteWide->id}/edit")->assertForbidden();
    }

    public function test_owner_with_listings_only_on_another_island_is_refused(): void
    {
        $ownOwner = Listing::factory()->onIsland($this->menorca)->create()->owner;
        $otherOwner = Listing::factory()->onIsland($this->mallorca)->create()->owner;

        $this->actingAs($this->menorcaAdmin)->get("/admin/users/{$ownOwner->id}")->assertOk();
        $this->actingAs($this->menorcaAdmin)->get("/admin/users/{$otherOwner->id}")->assertForbidden();
    }

    public function test_admin_covering_two_islands_can_open_both(): void
    {
        $admin = $this->islandAdmin($this->menorca, $this->mallorca);
        $a = Listing::factory()->onIsland($this->menorca)->create();
        $b = Listing::factory()->onIsland($this->mallorca)->create();

        $this->actingAs($admin)->get("/admin/listings/{$a->id}")->assertOk();
        $this->actingAs($admin)->get("/admin/listings/{$b->id}")->assertOk();
    }

    public function test_super_admin_can_open_records_on_every_island(): void
    {
        $super = $this->superAdmin();
        $listing = Listing::factory()->onIsland($this->mallorca)->create();
        $page = Page::factory()->create(['island_id' => null]);

        $this->actingAs($super)->get("/admin/listings/{$listing->id}")->assertOk();
        $this->actingAs($super)->get("/admin/pages/{$page->id}/edit")->assertOk();
    }

    public function test_lister_cannot_open_their_own_listing_in_the_admin_area(): void
    {
        $listing = Listing::factory()->onIsland($this->menorca)->create();

        $this->actingAs($listing->owner)->get("/admin/listings/{$listing->id}")->assertForbidden();
    }
}
