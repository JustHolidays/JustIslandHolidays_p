<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Support\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Map markers: one per kind of listing, edited by super admins on the Appearance page. */
class MapMarkersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function pngFile(int $size = 64): UploadedFile
    {
        $img = imagecreatetruecolor($size, $size);
        ob_start();
        imagepng($img);
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, ob_get_clean());

        return new UploadedFile($path, 'icon.png', 'image/png', null, true);
    }

    // --- Which marker a listing gets ----------------------------------------------------

    public function test_marker_kind_follows_the_account(): void
    {
        $affiliate = Listing::factory()->live()->create(['owner_id' => User::factory()->affiliate()]);
        $free = Listing::factory()->live()->create(['plan_code' => 'free']);
        $paid = Listing::factory()->live()->create(['plan_code' => 'standard']);

        $this->assertSame('affiliate', $affiliate->markerKind());
        $this->assertSame('free', $free->markerKind());
        $this->assertSame('paid', $paid->markerKind());
    }

    public function test_pins_api_says_which_marker_each_home_gets(): void
    {
        Listing::factory()->live()->titled('Partner')->create(['owner_id' => User::factory()->affiliate(), 'rank_score' => 30]);
        Listing::factory()->live()->titled('Paid')->create(['plan_code' => 'featured', 'rank_score' => 20]);
        Listing::factory()->live()->titled('Free')->create(['plan_code' => 'free', 'rank_score' => 10]);

        $pins = collect($this->getJson('/api/map')->assertOk()->json('pins'))->pluck('kind', 'title')->all();

        $this->assertSame(['Partner' => 'affiliate', 'Paid' => 'paid', 'Free' => 'free'], $pins);
    }

    // --- Public maps ----------------------------------------------------------------------

    public function test_maps_carry_the_published_markers_and_a_key(): void
    {
        $island = Island::factory()->create();

        $this->get(route('islands.show', ['lang' => 'en', 'island' => $island->slug]))->assertOk()
            ->assertSee('data-markers=', false)
            ->assertSee('#9a3412', false)
            ->assertSee('Partner booking site')
            ->assertSee('Owner listing')
            ->assertSee('Premium listing');
    }

    // --- Editing (super admin only) -------------------------------------------------------

    public function test_super_admin_saves_a_marker_as_a_draft_then_publishes_it(): void
    {
        $admin = $this->superAdmin();
        $island = Island::factory()->create();

        $this->actingAs($admin)->get('/admin/appearance')->assertOk()->assertSee('Map markers');

        $this->actingAs($admin)->put('/admin/appearance/markers/paid', [
            'label' => 'Gold member', 'colour' => '#14532D', 'icon' => $this->pngFile(128),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $draft = Appearance::markers(true)['paid'];
        $this->assertSame('Gold member', $draft['label']);
        $this->assertSame('#14532d', $draft['colour']);
        Storage::disk('public')->assertExists($draft['icon']);
        $this->assertSame([64, 64], array_slice(getimagesizefromstring(Storage::disk('public')->get($draft['icon'])), 0, 2));

        // Not live until published.
        $this->assertSame('Premium listing', Appearance::markers(false)['paid']['label']);
        $this->get(route('islands.show', ['lang' => 'en', 'island' => $island->slug]))->assertDontSee('Gold member');

        $this->actingAs($admin)->post('/admin/appearance/publish')->assertSessionHasNoErrors();

        $this->assertSame('Gold member', Appearance::markers(false)['paid']['label']);
        $this->get(route('islands.show', ['lang' => 'en', 'island' => $island->slug]))->assertSee('Gold member')->assertSee('#14532d', false);
        $this->assertTrue(AuditLog::where('action', 'appearance.publish')->get()->contains(fn ($a) => array_key_exists('map.markers', $a->detail ?? [])));
    }

    public function test_editing_one_marker_keeps_the_others(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put('/admin/appearance/markers/free', ['label' => 'Basic', 'colour' => '#333333']);
        $this->actingAs($admin)->put('/admin/appearance/markers/affiliate', ['label' => 'Partner', 'colour' => '#7f1d1d']);

        $markers = Appearance::markers(true);
        $this->assertSame('Basic', $markers['free']['label']);
        $this->assertSame('Partner', $markers['affiliate']['label']);
        $this->assertSame('Premium listing', $markers['paid']['label']);
    }

    public function test_icon_can_be_removed(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put('/admin/appearance/markers/free', ['label' => 'Owner', 'colour' => '#475569', 'icon' => $this->pngFile()]);
        $this->assertNotNull(Appearance::markers(true)['free']['icon']);

        $this->actingAs($admin)->put('/admin/appearance/markers/free', ['label' => 'Owner', 'colour' => '#475569', 'remove_icon' => 1]);

        $this->assertNull(Appearance::markers(true)['free']['icon']);
    }

    public function test_a_light_marker_colour_blocks_publishing(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put('/admin/appearance/markers/free', ['label' => 'Owner', 'colour' => '#ffff00'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'publishing is blocked'));

        $this->actingAs($admin)->post('/admin/appearance/publish')->assertSessionHasErrors('markers');
        $this->assertSame('#475569', Appearance::markers(false)['free']['colour']);
    }

    public function test_bad_input_is_refused(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->put('/admin/appearance/markers/paid', ['label' => '', 'colour' => 'red'])
            ->assertSessionHasErrors(['label', 'colour']);
        $this->actingAs($admin)->put('/admin/appearance/markers/paid', ['label' => 'X', 'colour' => '#000000', 'icon' => UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml')])
            ->assertSessionHasErrors('icon');
        $this->actingAs($admin)->put('/admin/appearance/markers/paid', ['label' => 'X', 'colour' => '#000000', 'icon' => $this->pngFile(32)])
            ->assertSessionHasErrors('icon');
        $this->actingAs($admin)->put('/admin/appearance/markers/gold', ['label' => 'X', 'colour' => '#000000'])->assertNotFound();
    }

    public function test_island_admins_and_listers_cannot_edit_markers(): void
    {
        $islandAdmin = $this->islandAdmin(Island::factory()->create());
        $this->actingAs($islandAdmin)->put('/admin/appearance/markers/paid', ['label' => 'X', 'colour' => '#000000'])->assertForbidden();

        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->put('/admin/appearance/markers/paid', ['label' => 'X', 'colour' => '#000000'])->assertForbidden();

        $this->assertSame('Premium listing', Appearance::markers(true)['paid']['label']);
    }

    public function test_undo_puts_the_previous_markers_back(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put('/admin/appearance/markers/paid', ['label' => 'Gold', 'colour' => '#000000']);
        $this->actingAs($admin)->post('/admin/appearance/publish');
        $this->assertSame('Gold', Appearance::markers(false)['paid']['label']);

        $this->actingAs($admin)->post('/admin/appearance/undo');

        $this->assertSame('Premium listing', Appearance::markers(false)['paid']['label']);
    }
}
