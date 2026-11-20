<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Settings › SEO: sitemap, titles and descriptions, sharing previews, health check (20 Nov 2026). */
class SeoTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $admin;

    private Island $island;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Storage::fake('public');
        Cache::flush();
        $this->admin = $this->superAdmin();
        $this->island = Island::factory()->create(['slug' => 'menorca', 'is_active' => true, 'name' => ['en' => 'Menorca']]);
        $this->area = Area::factory()->create(['island_id' => $this->island->id, 'slug' => 'cala-galdana', 'name' => ['en' => 'Cala Galdana']]);
    }

    public function test_only_super_admins_open_the_page(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('>SEO</a>', false);
        $this->actingAs($this->admin)->get(route('admin.seo.edit'))->assertOk()
            ->assertSee('Sitemap')->assertSee('Titles and descriptions')->assertSee('Sharing previews')->assertSee('Health check');

        $islandAdmin = $this->islandAdmin($this->island);
        $this->actingAs($islandAdmin)->get(route('admin.seo.edit'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.seo.meta'), ['meta' => ['home' => ['en' => ['title' => 'x']]]])->assertForbidden();
    }

    public function test_sitemap_lists_public_pages_and_robots_points_to_it(): void
    {
        $live = $this->setStatus($this->completeListing(null, $this->island), ListingStatus::Live)->load(['island', 'area']);
        $draft = $this->completeListing(null, $this->island);
        $hidden = Island::factory()->create(['slug' => 'ibiza', 'is_active' => false]);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringStartsWith('<?xml', ltrim($xml));
        $this->assertNotFalse(simplexml_load_string($xml), 'Valid XML.');
        $this->assertStringContainsString('<loc>'.route('home', 'en').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('islands.show', ['en', 'menorca']).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('areas.show', ['en', 'menorca', 'cala-galdana']).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$live->publicUrl('en').'</loc>', $xml);
        $this->assertStringNotContainsString($draft->public_ref, $xml);
        $this->assertStringNotContainsString('/ibiza', $xml);
        $this->assertStringContainsString('<loc>'.route('owners', 'en').'</loc>', $xml);

        config(['site.block_indexing' => false]);
        $this->get('/robots.txt')->assertSee('Sitemap: '.route('sitemap'));

        $this->actingAs($this->admin)->post(route('admin.seo.sitemap'))->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.seo.edit'))->assertSee('Last built');
    }

    public function test_saved_wording_replaces_the_built_in_wording_with_placeholders(): void
    {
        $this->setStatus($this->completeListing(null, $this->island, ['area_id' => $this->area->id]), ListingStatus::Live);

        $this->actingAs($this->admin)->post(route('admin.seo.meta'), ['meta' => [
            'home' => ['en' => ['title' => 'Balearic holiday homes direct from owners', 'description' => 'No booking fees.']],
            'island' => ['en' => ['title' => '{count} holiday homes in {island}', 'description' => '']],
            'area:'.$this->area->id => ['en' => ['title' => 'Cala Galdana villas by the beach', 'description' => '']],
        ]])->assertRedirect();
        $this->assertDatabaseHas('audit_log', ['action' => 'seo.meta']);
        auth()->logout();

        $this->get(route('home', 'en'))->assertSee('<title>Balearic holiday homes direct from owners</title>', false)
            ->assertSee('<meta name="description" content="No booking fees.">', false);
        $this->get(route('islands.show', ['en', 'menorca']))->assertSee('<title>1 holiday homes in Menorca</title>', false)
            ->assertSee('Villas, apartments and fincas to rent in Menorca', false);   // description left empty: built-in wording
        $this->get(route('areas.show', ['en', 'menorca', 'cala-galdana']))->assertSee('<title>Cala Galdana villas by the beach</title>', false);

        // Emptying both boxes brings the built-in wording back.
        $this->actingAs($this->admin)->post(route('admin.seo.meta'), ['meta' => ['home' => ['en' => ['title' => '', 'description' => '']]]]);
        auth()->logout();
        $this->get(route('home', 'en'))->assertSee('<title>Holiday homes in Mallorca and Menorca | Just Island Holidays</title>', false);
    }

    public function test_wording_is_checked(): void
    {
        $this->actingAs($this->admin)->post(route('admin.seo.meta'), ['meta' => ['home' => ['en' => ['title' => str_repeat('x', 191)]]]])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('admin.seo.meta'), ['meta' => ['nowhere' => ['en' => ['title' => 'x']]]])->assertSessionHasErrors('meta');
    }

    public function test_sharing_tags_use_the_default_picture_or_the_homes_photo(): void
    {
        $this->get(route('home', 'en'))->assertSee('<meta property="og:title"', false)->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('"@type":"Organization"', false)->assertSee('86 Manston Road');

        $this->actingAs($this->admin)->post(route('admin.seo.share'), ['share_image' => UploadedFile::fake()->image('beach.jpg', 1600, 900)])->assertSessionHasNoErrors();
        auth()->logout();
        $url = Seo::shareImageUrl();
        $this->assertNotNull($url);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get(str_replace('/storage/', '', parse_url($url, PHP_URL_PATH))));
        $this->assertSame([1200, 630], [$w, $h]);
        $this->get(route('owners', 'en'))->assertSee('<meta property="og:image" content="'.$url.'">', false)->assertSee('summary_large_image');

        $listing = $this->setStatus($this->completeListing(null, $this->island, ['image_path' => 'listings/x-1600.webp']), ListingStatus::Live)->load(['island', 'area']);
        $this->get($listing->publicUrl('en'))->assertSee('<meta property="og:image" content="'.$listing->imageUrl(1600).'">', false)
            ->assertSee('<meta property="og:type" content="article">', false);

        $this->actingAs($this->admin)->post(route('admin.seo.share'), ['share_image' => UploadedFile::fake()->image('small.jpg', 600, 300)])->assertSessionHasErrors('share_image');
    }

    public function test_health_check_lists_what_needs_attention(): void
    {
        $short = $this->setStatus($this->completeListing(null, $this->island), ListingStatus::Live);
        $short->translations()->update(['summary' => 'Too short.', 'title' => 'Villa Twin']);
        $twin = $this->setStatus($this->completeListing(null, $this->island), ListingStatus::Live);
        $twin->translations()->update(['title' => 'Villa Twin', 'summary' => str_repeat('A lovely home by the sea. ', 5)]);

        $this->actingAs($this->admin)->get(route('admin.seo.edit'))->assertOk()
            ->assertSeeInOrder(['Short summaries: 1', 'Villa Twin ('.$short->public_ref.')'], false)
            ->assertSee('Titles used more than once: 2')
            ->assertSee('Islands and resorts without an introduction:')   // the test properties add resorts of their own
            ->assertSee(route('admin.islands.edit', $this->island), false);
    }
}
