<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Island;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Full-width header images on island and resort pages, added and edited in the admin. */
class HeaderImageTest extends TestCase
{
    use RefreshDatabase;

    private Island $island;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->island = Island::factory()->create(['is_active' => true]);
        $this->area = Area::factory()->create(['island_id' => $this->island->id]);
    }

    private function jpg(int $width = 2000, int $height = 800): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($img);
        $path = tempnam(sys_get_temp_dir(), 'jpg');
        file_put_contents($path, ob_get_clean());

        return new UploadedFile($path, 'beach.jpg', 'image/jpeg', null, true);
    }

    private function islandPage(?Area $area = null): string
    {
        $url = $area
            ? route('areas.show', ['lang' => 'en', 'island' => $this->island->slug, 'area' => $area->slug])
            : route('islands.show', ['lang' => 'en', 'island' => $this->island->slug]);

        return $this->get($url)->assertOk()->getContent();
    }

    public function test_super_admin_adds_an_island_header_and_it_shows_full_width(): void
    {
        // Until a photo is added, the placeholder shows (no alt text: it shows nothing real).
        $before = $this->islandPage();
        $this->assertStringContainsString('data-header-image="placeholder"', $before);
        $this->assertStringContainsString('images/placeholders/header-1600.webp', $before);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.islands.header', $this->island), ['header_image' => $this->jpg(), 'header_image_alt' => 'Turquoise water at Cala Macarella'])
            ->assertSessionHasNoErrors();

        $island = $this->island->fresh();
        $this->assertMatchesRegularExpression('#^places/[0-9a-f-]+-1600\.webp$#', $island->header_image_path, 'Largest copy that does not upscale a 2000px image.');
        Storage::disk('public')->assertExists($island->header_image_path);
        Storage::disk('public')->assertExists(str_replace('-1600.webp', '-800.webp', $island->header_image_path));
        Storage::disk('public')->assertMissing(str_replace('-1600.webp', '-2400.webp', $island->header_image_path));

        $html = $this->islandPage();
        $this->assertStringContainsString('data-header-image="photo"', $html);
        $this->assertStringContainsString('sm:min-h-[400px]', $html);
        $this->assertStringNotContainsString('images/placeholders/header-', strstr($html, '<footer', true), 'The footer has its own placeholder.');
        $this->assertStringContainsString('alt="Turquoise water at Cala Macarella"', $html);
        $this->assertStringContainsString('-800.webp 800w', $html);
        // Below the menu.
        $this->assertLessThan(strpos($html, 'data-header-image'), strpos($html, '</header>'));

        $this->assertDatabaseHas('audit_log', ['action' => 'island.header_update']);
    }

    public function test_island_heading_and_resorts_sit_over_the_image_with_no_breadcrumb(): void
    {
        $this->area->update(['name' => ['en' => 'Cala Galdana']]);

        $html = $this->islandPage();
        $header = substr($html, strpos($html, 'data-header-image'));
        $header = substr($header, 0, strpos($header, '<div class="mx-auto max-w-6xl px-4 py-8">'));

        $this->assertStringContainsString('<h1', $header);
        $this->assertStringContainsString('Holiday homes in', $header);
        $this->assertStringContainsString('Resorts and towns', $header);
        $this->assertStringContainsString('Cala Galdana', $header);
        $this->assertStringContainsString('from-black/65', $header, 'A dark layer behind the text keeps it readable.');
        // Orange resort buttons with white text (orange-700 on white text is 5.2 to 1).
        $this->assertStringContainsString('bg-orange-700', $header);
        $this->assertStringContainsString('text-white', $header);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('aria-label="Breadcrumb"', $html);
        // The breadcrumb stays in the search engine data.
        $this->assertStringContainsString('BreadcrumbList', $html);

        // Resort pages keep their breadcrumb and heading below the menu.
        $resort = $this->islandPage($this->area);
        $this->assertStringContainsString('aria-label="Breadcrumb"', $resort);
        $this->assertStringContainsString('<h1', $resort);
    }

    public function test_resort_pages_have_no_header_image(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['header_image' => $this->jpg(), 'header_image_alt' => 'Island view']);

        $html = $this->islandPage($this->area);
        $this->assertStringNotContainsString('data-header-image', $html);
        $this->assertStringNotContainsString('alt="Island view"', $html);
        $this->assertStringNotContainsString('images/placeholders/header-', strstr($html, '<footer', true), 'The footer has its own placeholder.');

        // And nothing to edit for a resort.
        $this->actingAs($admin)->get(route('admin.areas.edit', $this->area))->assertOk()->assertDontSee('Page header image');
        $this->actingAs($admin)->post("/admin/areas/{$this->area->id}/header", ['header_image' => $this->jpg(), 'header_image_alt' => 'X'])->assertStatus(404);
    }

    public function test_replacing_deletes_the_old_copies_and_alt_can_change_alone(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['header_image' => $this->jpg(), 'header_image_alt' => 'First']);
        $first = $this->island->fresh()->header_image_path;

        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['header_image' => $this->jpg(2600, 900), 'header_image_alt' => 'Second']);
        $second = $this->island->fresh()->header_image_path;

        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('-2400.webp', $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertMissing(str_replace('-1600.webp', '-800.webp', $first));

        // Description only: the image stays.
        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['header_image_alt' => 'Better description'])->assertSessionHasNoErrors();
        $this->assertSame($second, $this->island->fresh()->header_image_path);
        $this->assertSame('Better description', $this->island->fresh()->header_image_alt);
    }

    public function test_remove_deletes_the_image(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['header_image' => $this->jpg(), 'header_image_alt' => 'Gone soon']);
        $path = $this->island->fresh()->header_image_path;

        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['remove' => 1])->assertSessionHasNoErrors();

        $this->assertNull($this->island->fresh()->header_image_path);
        Storage::disk('public')->assertMissing($path);
        // Back to the placeholder.
        $this->assertStringContainsString('data-header-image="placeholder"', $this->islandPage());
        $this->assertTrue(AuditLog::where('action', 'island.header_remove')->exists());
    }

    public function test_placeholder_files_exist_and_show_on_an_island_without_a_photo(): void
    {
        foreach (\App\Support\HeaderImage::WIDTHS as $w) {
            $file = public_path("images/placeholders/header-{$w}.webp");
            $this->assertFileExists($file);
            $this->assertSame([$w, (int) round(400 * $w / 2400)], array_slice(getimagesize($file), 0, 2));
        }

        $html = $this->islandPage();
        $this->assertStringContainsString('data-header-image="placeholder"', $html);
        $this->assertMatchesRegularExpression('#<img src="[^"]*images/placeholders/header-1600\.webp"[^>]*alt=""#', $html);

        $this->actingAs($this->superAdmin())->get(route('admin.islands.edit', $this->island))->assertOk()
            ->assertSee('The page shows this placeholder until you add a photo');
    }

    public function test_bad_uploads_are_refused(): void
    {
        $admin = $this->superAdmin();
        $post = fn (array $data) => $this->actingAs($admin)->post(route('admin.islands.header', $this->island), $data);

        $post(['header_image_alt' => 'No file'])->assertSessionHasErrors('header_image');
        $post(['header_image' => $this->jpg(), 'header_image_alt' => ''])->assertSessionHasErrors('header_image_alt');
        $post(['header_image' => $this->jpg(1200, 600), 'header_image_alt' => 'Too narrow'])->assertSessionHasErrors('header_image');
        $post(['header_image' => $this->jpg(2400, 300), 'header_image_alt' => 'Too short'])->assertSessionHasErrors('header_image');
        $post(['header_image' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml'), 'header_image_alt' => 'SVG'])->assertSessionHasErrors('header_image');

        $this->assertNull($this->island->fresh()->header_image_path);
    }

    public function test_island_admin_edits_headers_on_their_own_islands_only(): void
    {
        $other = Island::factory()->create();
        $admin = $this->islandAdmin($this->island);

        $this->actingAs($admin)->get(route('admin.islands.edit', $this->island))->assertOk()->assertSee('Page header image');
        $this->actingAs($admin)->post(route('admin.islands.header', $this->island), ['header_image' => $this->jpg(), 'header_image_alt' => 'Mine'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.islands.header', $other), ['header_image' => $this->jpg(), 'header_image_alt' => 'Not mine'])->assertForbidden();
        $this->assertNull($other->fresh()->header_image_path);

        $this->actingAs(User::factory()->owner()->create())->post(route('admin.islands.header', $this->island), ['remove' => 1])->assertForbidden();
    }
}
