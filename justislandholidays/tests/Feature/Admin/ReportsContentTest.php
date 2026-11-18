<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus as S;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\DeeplinkClick;
use App\Models\Island;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsContentTest extends TestCase
{
    use RefreshDatabase;

    private Island $island;

    private Island $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->island = Island::factory()->create();
        $this->other = Island::factory()->create();
    }

    private function click(Listing $listing, bool $bot = false, bool $unique = true): void
    {
        DeeplinkClick::create([
            'click_id' => (string) Str::ulid(), 'listing_id' => $listing->id, 'affiliate_id' => $listing->owner_id, 'island_id' => $listing->island_id,
            'clicked_at' => now()->subDay(), 'lang' => 'en', 'source_page' => 'listing', 'ip_hash' => str_repeat('f', 64),
            'is_bot' => $bot, 'is_unique' => $unique, 'target_url' => 'https://x.example',
        ]);
    }

    public function test_click_report_counts_humans_on_own_islands_and_can_include_bots(): void
    {
        $mine = Listing::factory()->onIsland($this->island)->live()->titled('Mine')->create();
        $theirs = Listing::factory()->onIsland($this->other)->live()->titled('Theirs')->create();
        $this->click($mine);
        $this->click($mine, unique: false);
        $this->click($mine, bot: true, unique: false);
        $this->click($theirs);
        $admin = $this->islandAdmin($this->island);

        $this->actingAs($admin)->get('/admin/clicks')->assertOk()
            ->assertSeeInOrder(['Clicks', '2', 'Different visitors', '1'])
            ->assertDontSee('Theirs');
        $this->actingAs($admin)->get('/admin/clicks?bots=1')->assertSeeInOrder(['Clicks', '3']);
    }

    public function test_click_export_has_no_ip_hash_and_neutralises_formulas(): void
    {
        $listing = Listing::factory()->onIsland($this->island)->live()->titled('=HYPERLINK("evil")')->create();
        $this->click($listing);

        $csv = $this->actingAs($this->superAdmin())->get('/admin/clicks/export')->assertOk()->streamedContent();

        $this->assertStringNotContainsString(str_repeat('f', 64), $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_island_admin_edits_intro_text_for_own_island_only(): void
    {
        $admin = $this->islandAdmin($this->island);

        $this->actingAs($admin)->put("/admin/islands/{$this->island->id}", ['intro_en' => 'A lovely island.', 'slug' => 'hacked', 'is_active' => 0])->assertRedirect();
        $fresh = $this->island->fresh();
        $this->assertSame('A lovely island.', $fresh->intro['en']);
        $this->assertNotSame('hacked', $fresh->slug, 'Island admins cannot rename or switch off.');
        $this->assertTrue($fresh->is_active);

        $this->actingAs($admin)->get("/admin/islands/{$this->other->id}/edit")->assertForbidden();
        $this->actingAs($admin)->post('/admin/islands', ['slug' => 'ibiza', 'name_en' => 'Ibiza'])->assertForbidden();
    }

    public function test_slug_is_locked_while_there_are_live_listings(): void
    {
        $area = Area::factory()->create(['island_id' => $this->island->id]);
        Listing::factory()->onIsland($this->island)->live()->create(['area_id' => $area->id]);
        $super = $this->superAdmin();

        $this->actingAs($super)->put("/admin/areas/{$area->id}", ['name_en' => 'Renamed', 'slug' => 'new-slug', 'kind' => 'town'])
            ->assertSessionHasErrors('slug');
        $this->actingAs($super)->put("/admin/areas/{$area->id}", ['name_en' => 'Renamed', 'slug' => $area->slug, 'kind' => 'town'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $area->fresh()->name['en']);
    }

    public function test_super_admin_creates_an_island_switched_off(): void
    {
        $this->actingAs($this->superAdmin())->post('/admin/islands', ['slug' => 'ibiza', 'name_en' => 'Ibiza'])->assertRedirect();

        $this->assertFalse(Island::where('slug', 'ibiza')->sole()->is_active);
    }

    public function test_island_admin_writes_guides_for_own_island_but_not_site_wide_pages(): void
    {
        $admin = $this->islandAdmin($this->island);
        $page = fn (array $o) => $o + ['slug' => 'best-beaches', 'kind' => 'guide', 'lang' => 'en', 'title' => 'Best beaches', 'body_md' => 'Text'];

        $this->actingAs($admin)->post('/admin/pages', $page(['island_id' => $this->island->id]))->assertRedirect();
        $this->actingAs($admin)->post('/admin/pages', $page(['island_id' => $this->other->id, 'slug' => 'other-guide']))->assertForbidden();
        $this->actingAs($admin)->post('/admin/pages', $page(['island_id' => null, 'slug' => 'about', 'kind' => 'static']))->assertForbidden();

        $this->assertSame(1, Page::count());
        $this->actingAs($admin)->post('/admin/pages', $page(['island_id' => $this->island->id]))->assertSessionHasErrors('slug');
    }

    public function test_markdown_preview_strips_html(): void
    {
        $this->actingAs($this->superAdmin())->postJson('/admin/markdown-preview', ['body' => "**Hi** <script>alert(1)</script>"])
            ->assertOk()
            ->assertJsonPath('html', fn ($h) => str_contains($h, '<strong>Hi</strong>') && ! str_contains($h, '<script>'));
    }

    public function test_audit_log_is_filterable_and_exports(): void
    {
        $super = $this->superAdmin();
        \App\Support\Audit::record($super, 'listing.reject', 'listing', 5, ['reason' => 'Test']);
        \App\Support\Audit::record(null, 'deeplink.check', 'listing', 6);

        $this->actingAs($super)->get('/admin/audit?action=reject')->assertOk()->assertSee('listing.reject')->assertDontSee('deeplink.check');
        $this->actingAs($super)->get('/admin/audit?actor=system')->assertSee('deeplink.check')->assertDontSee('listing.reject');
        $this->assertStringContainsString('listing.reject', $this->actingAs($super)->get('/admin/audit/export')->streamedContent());
    }

    public function test_every_admin_page_renders_for_a_super_admin(): void
    {
        $super = $this->superAdmin();
        $listing = Listing::factory()->onIsland($this->island)->live()->titled('Smoke')->create();
        $area = Area::first();
        $page = Page::factory()->create();
        $plan = \App\Models\Plan::create(['code' => 'free', 'name' => 'Free', 'audience' => 'owner']);
        $affiliate = User::factory()->affiliate()->create();

        foreach (['/admin', '/admin/listings', '/admin/listings?tab=recommended', "/admin/listings/{$listing->id}", '/admin/listings/import',
            '/admin/licences', '/admin/deeplinks', '/admin/enquiries', '/admin/users', "/admin/users/{$listing->owner_id}",
            '/admin/affiliates', '/admin/affiliates/create', "/admin/affiliates/{$affiliate->id}", '/admin/staff', '/admin/staff/create',
            "/admin/staff/{$super->id}/edit", '/admin/clicks', '/admin/clicks/events', '/admin/islands', "/admin/islands/{$this->island->id}/edit",
            "/admin/areas/{$area->id}/edit", '/admin/pages', '/admin/pages/create', "/admin/pages/{$page->id}/edit",
            '/admin/appearance', '/admin/plans', "/admin/plans/{$plan->id}/edit", '/admin/audit'] as $url) {
            $this->actingAs($super)->get($url)->assertOk();
        }
    }
}
