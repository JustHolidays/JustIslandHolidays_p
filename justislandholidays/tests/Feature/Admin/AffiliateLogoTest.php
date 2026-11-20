<?php

namespace Tests\Feature\Admin;

use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Affiliate logo banner (100 pixels tall) above Check availability, managed on the admin affiliate page. */
class AffiliateLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->partner = User::factory()->affiliate()->create(['name' => 'Sunny Stays']);
        $this->listing = Listing::factory()->live()->titled('Villa Sol')->withDeeplink()->create(['owner_id' => $this->partner->id])->load(['island', 'area']);
    }

    private function png(int $width = 400, int $height = 200): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($img);
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, ob_get_clean());

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }

    private function box(): string
    {
        $html = $this->get($this->listing->publicUrl('en'))->assertOk()->getContent();
        $start = strpos($html, 'border-2 border-[var(--colour-brand)]');
        $this->assertNotFalse($start, 'The Check availability box is on the page.');

        return substr($html, $start, strpos($html, 'Check availability</a>', $start) - $start);
    }

    public function test_placeholder_shows_above_check_availability_until_a_logo_is_added(): void
    {
        $box = $this->box();

        $this->assertStringContainsString('data-partner-logo="placeholder"', $box);
        $this->assertStringContainsString('images/placeholders/partner-logo.webp', $box);
        $this->assertStringContainsString('h-[100px]', $box);
        $this->assertFileExists(public_path('images/placeholders/partner-logo.webp'));
    }

    public function test_super_admin_adds_a_logo_and_it_shows_on_the_property_page(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->get(route('admin.affiliates.show', $this->partner))->assertOk()
            ->assertSee('Their property pages show this placeholder until you add a logo.');

        $this->actingAs($admin)->post(route('admin.affiliates.logo', $this->partner), ['logo' => $this->png(1000, 400), 'logo_alt' => 'Sunny Stays'])
            ->assertSessionHasNoErrors();

        $partner = $this->partner->fresh();
        Storage::disk('public')->assertExists($partner->affiliate_logo_path);
        $this->assertSame([500, 200], array_slice(getimagesizefromstring(Storage::disk('public')->get($partner->affiliate_logo_path)), 0, 2), 'Fitted to 200 pixels tall, the shape kept.');
        $this->assertDatabaseHas('audit_log', ['action' => 'affiliate.logo_update']);

        $box = $this->box();
        $this->assertStringContainsString('data-partner-logo="logo"', $box);
        $this->assertStringContainsString('alt="Sunny Stays"', $box);
        $this->assertStringNotContainsString('partner-logo.webp', $box);
    }

    public function test_replace_change_description_and_remove(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.affiliates.logo', $this->partner), ['logo' => $this->png(), 'logo_alt' => 'First']);
        $first = $this->partner->fresh()->affiliate_logo_path;

        $this->actingAs($admin)->post(route('admin.affiliates.logo', $this->partner), ['logo' => $this->png(), 'logo_alt' => 'Second']);
        $second = $this->partner->fresh()->affiliate_logo_path;
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($admin)->post(route('admin.affiliates.logo', $this->partner), ['logo_alt' => 'Better words'])->assertSessionHasNoErrors();
        $this->assertSame($second, $this->partner->fresh()->affiliate_logo_path);
        $this->assertSame('Better words', $this->partner->fresh()->affiliate_logo_alt);

        $this->actingAs($admin)->post(route('admin.affiliates.logo', $this->partner), ['remove' => 1])->assertSessionHasNoErrors();
        $this->assertNull($this->partner->fresh()->affiliate_logo_path);
        Storage::disk('public')->assertMissing($second);
        $this->assertStringContainsString('data-partner-logo="placeholder"', $this->box());
    }

    public function test_bad_uploads_are_refused(): void
    {
        $admin = $this->superAdmin();
        $post = fn (array $d) => $this->actingAs($admin)->post(route('admin.affiliates.logo', $this->partner), $d);

        $post(['logo_alt' => 'No file'])->assertSessionHasErrors('logo');
        $post(['logo' => $this->png(), 'logo_alt' => ''])->assertSessionHasErrors('logo_alt');
        $post(['logo' => $this->png(300, 60), 'logo_alt' => 'Too short'])->assertSessionHasErrors('logo');
        $post(['logo' => UploadedFile::fake()->create('logo.svg', 3, 'image/svg+xml'), 'logo_alt' => 'SVG'])->assertSessionHasErrors('logo');

        $this->assertNull($this->partner->fresh()->affiliate_logo_path);
    }

    public function test_owner_listings_have_no_logo_banner(): void
    {
        $owned = Listing::factory()->live()->titled('Owner home')->withDeeplink()->create(['owner_id' => User::factory()->owner(), 'plan_code' => 'standard'])->load(['island', 'area']);

        $this->get($owned->publicUrl('en'))->assertOk()->assertSee('Check availability')->assertDontSee('data-partner-logo', false);
    }

    public function test_only_super_admins_manage_logos(): void
    {
        $islandAdmin = $this->islandAdmin(Island::factory()->create());

        $this->actingAs($islandAdmin)->post(route('admin.affiliates.logo', $this->partner), ['logo' => $this->png(), 'logo_alt' => 'X'])->assertForbidden();
        $this->actingAs($this->partner)->post(route('admin.affiliates.logo', $this->partner), ['logo' => $this->png(), 'logo_alt' => 'X'])->assertForbidden();
        $this->actingAs($this->superAdmin())->post(route('admin.affiliates.logo', User::factory()->owner()->create()), ['logo' => $this->png(), 'logo_alt' => 'X'])->assertNotFound();

        $this->assertNull($this->partner->fresh()->affiliate_logo_path);
    }
}
