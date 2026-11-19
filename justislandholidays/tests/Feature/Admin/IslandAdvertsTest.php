<?php

namespace Tests\Feature\Admin;

use App\Ads\IslandAdverts;
use App\Models\Ad;
use App\Models\AdSlot;
use App\Models\Advertiser;
use App\Models\Island;
use App\Models\User;
use Database\Seeders\AdSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Four advert columns below the island introduction, managed by super admins only. */
class IslandAdvertsTest extends TestCase
{
    use RefreshDatabase;

    private Island $menorca;

    private Island $mallorca;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(AdSlotSeeder::class);
        $this->menorca = Island::factory()->create(['slug' => 'menorca', 'is_active' => true]);
        $this->mallorca = Island::factory()->create(['slug' => 'mallorca', 'is_active' => true]);
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
            'position' => 2,
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

    private function page(Island $island): string
    {
        return $this->get(route('islands.show', ['lang' => 'en', 'island' => $island->slug]))->assertOk()->getContent();
    }

    private function ad(Island $island, int $position, array $attributes = []): Ad
    {
        return Ad::create($attributes + [
            'advertiser_id' => Advertiser::firstOrCreate(['name' => 'Advertiser'])->id,
            'slot_id' => AdSlot::where('code', IslandAdverts::SLOTS[$position])->value('id'),
            'island_id' => $island->id,
            'image_path' => 'ads/00000000-0000-0000-0000-000000000000-300.webp',
            'alt' => "Advert {$position}",
            'target_url' => 'https://example.com/'.$position,
            'status' => 'active',
            'weight' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
    }

    // --- Public island page ------------------------------------------------------------

    public function test_four_placeholder_columns_show_below_the_intro_until_adverts_are_added(): void
    {
        $this->menorca->update(['intro' => ['en' => 'Menorca intro text.']]);

        $html = $this->page($this->menorca);

        $this->assertSame(4, substr_count($html, 'data-advert='));
        $this->assertSame(4, substr_count($html, 'images/placeholders/advert-300.webp"'));
        $this->assertStringContainsString('md:grid-cols-4', $html);
        $this->assertLessThan(strpos($html, 'data-advert="1"'), strpos($html, 'Menorca intro text.'), 'Below the intro.');
        $this->assertStringNotContainsString(route('ads.click', 1), $html, 'Placeholders are not links.');
        $this->assertStringContainsString('h-[75px]', $html);
    }

    public function test_an_advert_replaces_its_column_and_links_through_the_click_counter(): void
    {
        $ad = $this->ad($this->menorca, 3);

        $html = $this->page($this->menorca);
        $this->assertSame(3, substr_count($html, 'images/placeholders/advert-300.webp"'));
        $this->assertStringContainsString('href="'.route('ads.click', $ad->id).'" rel="sponsored nofollow noopener" target="_blank"', $html);
        $this->assertStringContainsString('alt="Advert: Advert 3 (opens in a new tab)"', $html);
    }

    public function test_adverts_are_island_specific(): void
    {
        $this->ad($this->mallorca, 1, ['alt' => 'Only for Mallorca']);

        $this->assertStringNotContainsString('Only for Mallorca', $this->page($this->menorca));
        $this->assertStringContainsString('Only for Mallorca', $this->page($this->mallorca));
    }

    public function test_paused_future_and_ended_adverts_do_not_show(): void
    {
        $this->ad($this->menorca, 1, ['status' => 'paused', 'alt' => 'Paused one']);
        $this->ad($this->menorca, 2, ['starts_at' => now()->addDay(), 'ends_at' => now()->addMonth(), 'alt' => 'Future one']);
        $this->ad($this->menorca, 3, ['starts_at' => now()->subMonth(), 'ends_at' => now()->subDay(), 'alt' => 'Old one']);

        $html = $this->page($this->menorca);
        $this->assertSame(4, substr_count($html, 'images/placeholders/advert-300.webp"'));
        $this->assertStringNotContainsString('Paused one', $html);
        $this->assertStringNotContainsString('Future one', $html);
        $this->assertStringNotContainsString('Old one', $html);
    }

    public function test_resort_pages_have_no_advert_columns(): void
    {
        $area = \App\Models\Area::factory()->create(['island_id' => $this->menorca->id]);

        $html = $this->get(route('areas.show', ['lang' => 'en', 'island' => 'menorca', 'area' => $area->slug]))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-advert=', $html);
    }

    // --- Click counting ------------------------------------------------------------------

    public function test_a_click_is_counted_and_goes_to_the_advertiser_without_cookies(): void
    {
        $ad = $this->ad($this->menorca, 1);

        $this->get(route('ads.click', $ad->id))->assertRedirect('https://example.com/1')->assertHeaderMissing('Set-Cookie');
        $this->get(route('ads.click', $ad->id));

        $this->assertSame(2, (int) DB::table('ad_stats_daily')->where('ad_id', $ad->id)->where('stat_date', now()->toDateString())->value('clicks'));
    }

    public function test_an_advert_that_is_not_running_is_not_followed(): void
    {
        $ad = $this->ad($this->menorca, 1, ['status' => 'paused']);

        $this->get(route('ads.click', $ad->id))->assertRedirect(route('islands.show', ['lang' => 'en', 'island' => 'menorca']));
        $this->assertSame(0, DB::table('ad_stats_daily')->count());
        $this->get('/api/ads/999999/click')->assertNotFound();
    }

    // --- Admin -----------------------------------------------------------------------------

    public function test_super_admin_adds_an_advert(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/admin')->assertSee('>Affiliate Adverts</a>', false);
        $this->actingAs($admin)->get(route('admin.ads.create', ['island' => $this->menorca->id, 'position' => 2]))->assertOk()
            ->assertSee('images/placeholders/advert-300.webp');

        $this->actingAs($admin)->post(route('admin.ads.store'), $this->form())->assertRedirect()->assertSessionHasNoErrors();

        $ad = Ad::sole();
        $this->assertSame($this->menorca->id, $ad->island_id);
        $this->assertSame('island_intro_2', $ad->slot->code);
        $this->assertSame('Island Car Hire', $ad->advertiser->name);
        $this->assertMatchesRegularExpression('#^ads/[0-9a-f-]+-300\.webp$#', $ad->image_path);
        $this->assertSame([300, 75], array_slice(getimagesizefromstring(Storage::disk('public')->get($ad->image_path)), 0, 2));
        Storage::disk('public')->assertExists(str_replace('-300.webp', '-600.webp', $ad->image_path));
        $this->assertDatabaseHas('audit_log', ['action' => 'ad.create']);

        $this->assertStringContainsString('Island Car Hire: 10% off', $this->page($this->menorca));
        $this->actingAs($admin)->get(route('admin.ads.index'))->assertOk()->assertSee('Island Car Hire')->assertSee('Showing');
    }

    public function test_super_admin_edits_and_deletes_an_advert(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.ads.store'), $this->form());
        $ad = Ad::sole();
        $oldImage = $ad->image_path;

        $this->actingAs($admin)->put(route('admin.ads.update', $ad), $this->form(['image' => $this->png(1200, 400), 'target_url' => 'https://carhire.example/new', 'position' => 4]))
            ->assertSessionHasNoErrors();
        $ad->refresh();
        $this->assertSame('https://carhire.example/new', $ad->target_url);
        $this->assertSame('island_intro_4', $ad->slot->code);
        Storage::disk('public')->assertMissing($oldImage);

        // Without a new image, the image stays.
        $this->actingAs($admin)->put(route('admin.ads.update', $ad), array_diff_key($this->form(['alt' => 'New words']), ['image' => 1]))->assertSessionHasNoErrors();
        $this->assertSame($ad->image_path, $ad->fresh()->image_path);

        $path = $ad->fresh()->image_path;
        $this->actingAs($admin)->delete(route('admin.ads.destroy', $ad))->assertRedirect();
        $this->assertSame(0, Ad::count());
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('audit_log', ['action' => 'ad.delete']);
    }

    public function test_an_island_is_required_and_bad_input_is_refused(): void
    {
        $admin = $this->superAdmin();
        $post = fn (array $o) => $this->actingAs($admin)->post(route('admin.ads.store'), $this->form($o));

        $post(['island_id' => ''])->assertSessionHasErrors('island_id');
        $post(['target_url' => 'http://insecure.example'])->assertSessionHasErrors('target_url');
        $post(['alt' => ''])->assertSessionHasErrors('alt');
        $post(['ends_at' => now()->subYear()->toDateString()])->assertSessionHasErrors('ends_at');
        $post(['position' => 5])->assertSessionHasErrors('position');
        $post(['image' => $this->png(200, 50)])->assertSessionHasErrors('image');
        $post(['image' => UploadedFile::fake()->create('x.svg', 3, 'image/svg+xml')])->assertSessionHasErrors('image');

        $this->assertSame(0, Ad::count());
    }

    public function test_only_super_admins_manage_adverts(): void
    {
        $ad = $this->ad($this->menorca, 1);
        $islandAdmin = $this->islandAdmin($this->menorca);

        $this->assertStringNotContainsString('>Affiliate Adverts</a>', $this->actingAs($islandAdmin)->get('/admin')->getContent());
        $this->actingAs($islandAdmin)->get(route('admin.ads.index'))->assertForbidden();
        $this->actingAs($islandAdmin)->get(route('admin.ads.create'))->assertForbidden();
        $this->actingAs($islandAdmin)->post(route('admin.ads.store'), $this->form())->assertForbidden();
        $this->actingAs($islandAdmin)->get(route('admin.ads.edit', $ad))->assertForbidden();
        $this->actingAs($islandAdmin)->delete(route('admin.ads.destroy', $ad))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get(route('admin.ads.index'))->assertForbidden();

        $this->assertSame(1, Ad::count());
    }

    public function test_two_adverts_in_one_column_take_turns(): void
    {
        $this->ad($this->menorca, 1, ['alt' => 'First']);
        $this->ad($this->menorca, 1, ['alt' => 'Second']);

        $seen = [];
        foreach (range(1, 40) as $i) {
            $seen[IslandAdverts::for($this->menorca)[1]->alt] = true;
        }

        $this->assertEqualsCanonicalizing(['First', 'Second'], array_keys($seen));
    }
}
