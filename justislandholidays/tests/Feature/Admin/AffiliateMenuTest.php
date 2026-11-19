<?php

namespace Tests\Feature\Admin;

use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Affiliates dropdown, each affiliate's Deeplinks page, and the Owner and agent links page. */
class AffiliateMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private User $other;

    private Island $island;

    protected function setUp(): void
    {
        parent::setUp();
        $this->island = Island::factory()->create();
        $this->partner = User::factory()->affiliate()->create(['name' => 'Sunny Stays']);
        $this->other = User::factory()->affiliate()->create(['name' => 'Bay Villas']);
    }

    private function linked(User $owner, string $title, string $status = 'ok'): Listing
    {
        return Listing::factory()->live()->onIsland($this->island)->titled($title)
            ->withDeeplink('https://booking.example.com/'.md5($title), null, $status)
            ->create(['owner_id' => $owner->id]);
    }

    public function test_menu_lists_active_affiliates(): void
    {
        $gone = User::factory()->affiliate()->create(['name' => 'Closed Co', 'deactivated_at' => now()]);

        $html = $this->actingAs($this->superAdmin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('>All affiliates</a>', $html);
        $this->assertStringContainsString(route('admin.affiliates.show', $this->partner), $html);
        $this->assertStringContainsString(route('admin.affiliates.show', $this->other), $html);
        $this->assertStringNotContainsString(route('admin.affiliates.show', $gone), $html);
        // Owners and agents look after their own links, so there is no menu item for them.
        $this->assertStringNotContainsString('Owner and agent links', $html);
        // Alphabetical.
        $this->assertLessThan(strpos($html, 'Sunny Stays'), strpos($html, 'Bay Villas'));
    }

    public function test_affiliates_dropdown_is_super_admin_only(): void
    {
        $html = $this->actingAs($this->islandAdmin($this->island))->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('All affiliates', $html);
        $this->assertStringNotContainsString('Sunny Stays', $html);
        $this->assertStringNotContainsString('Owner and agent links', $html);
    }

    public function test_current_affiliate_shows_its_deeplinks_item(): void
    {
        $admin = $this->superAdmin();

        $html = $this->actingAs($admin)->get(route('admin.affiliates.show', $this->partner))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details\s+open/', $html);
        $this->assertStringContainsString(route('admin.affiliates.deeplinks', $this->partner), $html);
        $this->assertStringNotContainsString(route('admin.affiliates.deeplinks', $this->other), $html);

        $html = $this->actingAs($admin)->get(route('admin.affiliates.deeplinks', $this->partner))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s*>Deeplinks<\/a>/', $html);
    }

    public function test_affiliate_deeplinks_page_shows_only_that_affiliates_links(): void
    {
        $this->linked($this->partner, 'Sunny One', 'broken');
        $this->linked($this->other, 'Bay One');
        $this->linked(User::factory()->owner()->create(), 'Owner Home');

        $this->actingAs($this->superAdmin())->get(route('admin.affiliates.deeplinks', $this->partner))->assertOk()
            ->assertSee('Sunny Stays: deeplinks')
            ->assertSee('Sunny One')
            ->assertDontSee('Bay One')
            ->assertDontSee('Owner Home');

        $this->actingAs($this->superAdmin())->get(route('admin.affiliates.deeplinks', ['affiliate' => $this->partner, 'status' => 'ok']))->assertOk()
            ->assertDontSee('Sunny One');
    }

    public function test_there_is_no_page_for_owner_and_agent_links(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin/deeplinks')->assertNotFound();
        $this->actingAs($this->islandAdmin($this->island))->get('/admin/deeplinks')->assertNotFound();
    }

    public function test_affiliate_deeplinks_page_is_super_admin_only_and_affiliates_only(): void
    {
        $this->actingAs($this->islandAdmin($this->island))->get(route('admin.affiliates.deeplinks', $this->partner))->assertForbidden();

        $owner = User::factory()->owner()->create();
        $this->actingAs($this->superAdmin())->get(route('admin.affiliates.deeplinks', $owner))->assertNotFound();
    }

    public function test_affiliate_page_and_list_show_broken_links(): void
    {
        $this->linked($this->partner, 'Sunny One', 'broken');
        $this->linked($this->partner, 'Sunny Two');
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.affiliates.show', $this->partner))->assertOk()->assertSee('Deeplinks (1 broken)');
        $this->actingAs($admin)->get(route('admin.affiliates.index'))->assertOk()->assertSee('1<span class="sr-only"> broken links for Sunny Stays</span>', false);
    }

    public function test_dashboard_counts_broken_affiliate_links_only(): void
    {
        $this->linked($this->partner, 'Sunny One', 'broken');
        $this->linked($this->partner, 'Sunny Two', 'broken');
        $this->linked(User::factory()->owner()->create(), 'Owner Home', 'broken');

        $this->actingAs($this->superAdmin())->get('/admin')->assertOk()
            ->assertViewHas('brokenAffiliate', 2)
            ->assertViewMissing('broken')
            ->assertSee('Broken affiliate links on live listings')
            ->assertDontSee('owner and agent links');

        $this->actingAs($this->islandAdmin($this->island))->get('/admin')->assertOk()
            ->assertDontSee('Broken affiliate links');
    }
}
