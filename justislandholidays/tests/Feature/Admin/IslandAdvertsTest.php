<?php

namespace Tests\Feature\Admin;

use App\Ads\IslandAdverts;
use App\Models\Ad;
use App\Models\AdSlot;
use App\Models\Advertiser;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\AdSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Affiliate adverts by section: the Menu Advert Section (island and resort pages, 4 at a time),
 * the Sidebar Section (property pages, all of them) or both. Island-specific, super admins only.
 */
class IslandAdvertsTest extends TestCase
{
    use RefreshDatabase;

    private Island $menorca;

    private Island $mallorca;

    private Area $resort;

    private Listing $home;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(AdSlotSeeder::class);
        $this->menorca = Island::factory()->create(['slug' => 'menorca', 'is_active' => true]);
        $this->mallorca = Island::factory()->create(['slug' => 'mallorca', 'is_active' => true]);
        $this->resort = Area::factory()->create(['island_id' => $this->menorca->id]);
        $this->home = Listing::factory()->live()->titled('Villa Sol')->create(['island_id' => $this->menorca->id, 'area_id' => $this->resort->id])->load(['island', 'area']);
    }

    private function png(int $width = 600, int $height = 150): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($img);
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, ob_get_clean());

        return new UploadedFile($path, 'advert.png', 'image/png', null, true);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'island_id' => $this->menorca->id,
            'section' => 'menu',
            'advertiser' => 'Island Car Hire',
            'image' => $this->png(),
            'alt' => 'Island Car Hire: 10% off',
            'target_url' => 'https://carhire.example/menorca',
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'weight' => 1,
        ];
    }

    private function ad(Island $island, string $section, string $alt, array $attributes = []): Ad
    {
        return Ad::create($attributes + [
            'advertiser_id' => Advertiser::firstOrCreate(['name' => 'Advertiser'])->id,
            'slot_id' => AdSlot::where('code', IslandAdverts::SECTIONS[$section])->value('id'),
            'island_id' => $island->id,
            'image_path' => 'ads/00000000-0000-0000-0000-000000000000-300.webp',
            'alt' => $alt,
            'target_url' => 'https://example.com/'.md5($alt),
            'status' => 'active',
            'weight' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
    }

    private function islandPage(?Island $island = null): string
    {
        return $this->get(route('islands.show', ['lang' => 'en', 'island' => ($island ?? $this->menorca)->slug]))->assertOk()->getContent();
    }

    private function resortPage(): string
    {
        return $this->get(route('areas.show', ['lang' => 'en', 'island' => 'menorca', 'area' => $this->resort->slug]))->assertOk()->getContent();
    }

    /** Only the Sidebar Section part of the property page. */
    private function sidebar(): string
    {
        $html = $this->get($this->home->publicUrl('en'))->assertOk()->getContent();
        $start = strpos($html, 'data-advert-section="sidebar"');
        $this->assertNotFalse($start, 'The property page has a Sidebar Section.');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    // --- Menu Advert Section --------------------------------------------------------------

    public function test_menu_section_shows_four_placeholders_on_island_and_resort_pages(): void
    {
        $this->menorca->update(['intro' => ['en' => 'Menorca intro text.']]);

        foreach ([$this->islandPage(), $this->resortPage()] as $html) {
            $this->assertStringContainsString('data-advert-section="menu"', $html);
            $this->assertSame(4, substr_count($html, 'images/placeholders/advert-300.webp"'));
            $this->assertStringNotContainsString(route('ads.click', 1), $html, 'Placeholders are not links.');
        }
        $html = $this->islandPage();
        $this->assertLessThan(strpos($html, 'data-advert="1"'), strpos($html, 'Menorca intro text.'), 'Below the intro.');
    }

    public function test_menu_section_shows_menu_and_both_adverts_not_sidebar_ones(): void
    {
        $menu = $this->ad($this->menorca, 'menu', 'Menu one');
        $both = $this->ad($this->menorca, 'both', 'Both one');
        $this->ad($this->menorca, 'sidebar', 'Sidebar one');

        foreach ([$this->islandPage(), $this->resortPage()] as $html) {
            $this->assertStringContainsString('Menu one', $html);
            $this->assertStringContainsString('Both one', $html);
            $this->assertStringNotContainsString('Sidebar one', $html);
            $this->assertSame(2, substr_count($html, 'images/placeholders/advert-300.webp"'), 'Two spaces left for placeholders.');
            $this->assertStringContainsString('href="'.route('ads.click', $menu->id).'" rel="sponsored nofollow noopener" target="_blank"', $html);
        }
    }

    public function test_more_than_four_menu_adverts_show_four_at_random(): void
    {
        foreach (range(1, 6) as $i) {
            $this->ad($this->menorca, $i % 2 ? 'menu' : 'both', "Advert {$i}");
        }

        $seen = [];
        foreach (range(1, 40) as $i) {
            $picked = IslandAdverts::menuFor($this->menorca);
            $this->assertCount(4, $picked);
            $alts = array_map(fn ($a) => $a->alt, $picked);
            $this->assertSame($alts, array_unique($alts), 'No advert twice on one page.');
            foreach ($alts as $alt) {
                $seen[$alt] = true;
            }
        }

        $this->assertCount(6, $seen, 'Over many visits, every advert gets shown.');
        $html = $this->islandPage();
        $this->assertSame(0, substr_count($html, 'images/placeholders/advert-300.webp"'));
    }

    // --- Sidebar Section -------------------------------------------------------------------

    public function test_sidebar_section_shows_every_sidebar_and_both_advert_below_check_availability(): void
    {
        foreach (range(1, 7) as $i) {
            $this->ad($this->menorca, 'sidebar', "Side {$i}");
        }
        $this->ad($this->menorca, 'both', 'Both one');
        $this->ad($this->menorca, 'menu', 'Menu only');

        $sidebar = $this->sidebar();
        $this->assertSame(8, substr_count($sidebar, 'data-sidebar-advert'), 'No limit on the number shown.');
        $this->assertStringContainsString('Both one', $sidebar);
        $this->assertStringNotContainsString('Menu only', $sidebar);
    }

    public function test_sidebar_section_sits_directly_below_check_availability(): void
    {
        $affiliateHome = Listing::factory()->live()->titled('Partner home')->withDeeplink()
            ->create(['island_id' => $this->menorca->id, 'area_id' => $this->resort->id, 'owner_id' => User::factory()->affiliate()])->load(['island', 'area']);

        $html = $this->get($affiliateHome->publicUrl('en'))->assertOk()->getContent();
        $button = strpos($html, 'Check availability</a>');
        $this->assertNotFalse($button);
        $this->assertGreaterThan($button, strpos($html, 'data-advert-section="sidebar"'));
    }

    public function test_sidebar_section_shows_one_placeholder_when_there_are_no_adverts(): void
    {
        $sidebar = $this->sidebar();

        $this->assertSame(1, substr_count($sidebar, 'images/placeholders/advert-300.webp"'));
        $this->assertStringNotContainsString('data-sidebar-advert', $sidebar);
    }

    public function test_adverts_are_island_specific_in_both_sections(): void
    {
        $this->ad($this->mallorca, 'both', 'Only for Mallorca');

        $this->assertStringNotContainsString('Only for Mallorca', $this->islandPage());
        $this->assertStringNotContainsString('Only for Mallorca', $this->resortPage());
        $this->assertStringNotContainsString('Only for Mallorca', $this->sidebar());
        $this->assertStringContainsString('Only for Mallorca', $this->islandPage($this->mallorca));
    }

    public function test_paused_future_and_ended_adverts_do_not_show(): void
    {
        $this->ad($this->menorca, 'both', 'Paused one', ['status' => 'paused']);
        $this->ad($this->menorca, 'both', 'Future one', ['starts_at' => now()->addDay(), 'ends_at' => now()->addMonth()]);
        $this->ad($this->menorca, 'both', 'Old one', ['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);

        foreach ([$this->islandPage(), $this->sidebar()] as $html) {
            $this->assertStringNotContainsString('Paused one', $html);
            $this->assertStringNotContainsString('Future one', $html);
            $this->assertStringNotContainsString('Old one', $html);
        }
    }

    // --- Click counting ------------------------------------------------------------------

    public function test_a_click_is_counted_and_goes_to_the_advertiser_without_cookies(): void
    {
        $ad = $this->ad($this->menorca, 'sidebar', 'Clicky');

        $this->get(route('ads.click', $ad->id))->assertRedirect($ad->target_url)->assertHeaderMissing('Set-Cookie');
        $this->get(route('ads.click', $ad->id));

        $this->assertSame(2, (int) DB::table('ad_stats_daily')->where('ad_id', $ad->id)->where('stat_date', now()->toDateString())->value('clicks'));
    }

    public function test_an_advert_that_is_not_running_is_not_followed(): void
    {
        $ad = $this->ad($this->menorca, 'menu', 'Paused', ['status' => 'paused']);

        $this->get(route('ads.click', $ad->id))->assertRedirect(route('islands.show', ['lang' => 'en', 'island' => 'menorca']));
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
        $this->get('/api/ads/999999/click')->assertNotFound();
    }

    // --- Admin -----------------------------------------------------------------------------

    public function test_super_admin_adds_an_advert_choosing_the_section(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/admin')->assertSee('>Affiliate Adverts</a>', false);
        $html = $this->actingAs($admin)->get(route('admin.ads.create', ['island' => $this->menorca->id]))->assertOk()->getContent();
        $this->assertStringContainsString('<label for="section"', $html);
        $this->assertStringContainsString('<option value="menu" selected>Menu Advert Section</option>', $html);
        $this->assertStringContainsString('<option value="sidebar" >Sidebar Section</option>', $html);
        $this->assertStringContainsString('<option value="both" >Both</option>', $html);
        $this->assertStringNotContainsString('name="position"', $html, 'No column to choose any more.');

        $this->actingAs($admin)->post(route('admin.ads.store'), $this->form(['section' => 'both']))->assertRedirect()->assertSessionHasNoErrors();

        $ad = Ad::sole();
        $this->assertSame('both_sections', $ad->slot->code);
        $this->assertSame('both', IslandAdverts::sectionOf($ad));
        $this->assertSame([300, 75], array_slice(getimagesizefromstring(Storage::disk('public')->get($ad->image_path)), 0, 2));
        $this->assertDatabaseHas('audit_log', ['action' => 'ad.create']);

        $this->assertStringContainsString('Island Car Hire: 10% off', $this->islandPage());
        $this->assertStringContainsString('Island Car Hire: 10% off', $this->sidebar());
        $this->actingAs($admin)->get(route('admin.ads.index'))->assertOk()->assertSee('Island Car Hire')->assertSee('Both')->assertSee('Showing');
    }

    public function test_super_admin_moves_edits_and_deletes_an_advert(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.ads.store'), $this->form());
        $ad = Ad::sole();
        $oldImage = $ad->image_path;

        $this->actingAs($admin)->get(route('admin.ads.edit', $ad))->assertOk()->assertSee('<option value="menu" selected>', false);
        $this->actingAs($admin)->put(route('admin.ads.update', $ad), $this->form(['image' => $this->png(1200, 400), 'section' => 'sidebar']))->assertSessionHasNoErrors();
        $ad->refresh();
        $this->assertSame('sidebar', IslandAdverts::sectionOf($ad));
        Storage::disk('public')->assertMissing($oldImage);
        $this->assertStringNotContainsString('Island Car Hire: 10% off', $this->islandPage());
        $this->assertStringContainsString('Island Car Hire: 10% off', $this->sidebar());

        $path = $ad->image_path;
        $this->actingAs($admin)->delete(route('admin.ads.destroy', $ad))->assertRedirect();
        $this->assertSame(0, Ad::count());
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('audit_log', ['action' => 'ad.delete']);
    }

    public function test_an_island_and_section_are_required_and_bad_input_is_refused(): void
    {
        $admin = $this->superAdmin();
        $post = fn (array $o) => $this->actingAs($admin)->post(route('admin.ads.store'), $this->form($o));

        $post(['island_id' => ''])->assertSessionHasErrors('island_id');
        $post(['section' => 'footer'])->assertSessionHasErrors('section');
        $post(['target_url' => 'http://insecure.example'])->assertSessionHasErrors('target_url');
        $post(['alt' => ''])->assertSessionHasErrors('alt');
        $post(['ends_at' => now()->subYear()->toDateString()])->assertSessionHasErrors('ends_at');
        $post(['image' => $this->png(200, 50)])->assertSessionHasErrors('image');
        $post(['image' => UploadedFile::fake()->create('x.svg', 3, 'image/svg+xml')])->assertSessionHasErrors('image');

        $this->assertSame(0, Ad::count());
    }

    public function test_only_super_admins_manage_adverts(): void
    {
        $ad = $this->ad($this->menorca, 'menu', 'Mine');
        $islandAdmin = $this->islandAdmin($this->menorca);

        $this->assertStringNotContainsString('>Affiliate Adverts</a>', $this->actingAs($islandAdmin)->get('/admin')->getContent());
        $this->actingAs($islandAdmin)->get(route('admin.ads.index'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.ads.store'), $this->form())->assertForbidden();
        $this->actingAs($islandAdmin)->get(route('admin.ads.edit', $ad))->assertForbidden();
        $this->actingAs($islandAdmin)->delete(route('admin.ads.destroy', $ad))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get(route('admin.ads.index'))->assertForbidden();

        $this->assertSame(1, Ad::count());
    }

    // --- Moving from columns to sections --------------------------------------------------

    public function test_adverts_in_the_old_columns_move_to_the_menu_section(): void
    {
        $oldSlot = AdSlot::create(['code' => 'island_intro_3', 'width' => 300, 'height' => 75]);
        $old = $this->ad($this->menorca, 'menu', 'Old column advert', ['slot_id' => $oldSlot->id]);

        (require database_path('migrations/2026_11_19_000005_advert_sections.php'))->up();

        $this->assertSame('menu', IslandAdverts::sectionOf($old->fresh()));
        $this->assertNull(AdSlot::find($oldSlot->id));
    }
}
