<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Footer image set on the Frontend Styler, and the orange footer link buttons (20 Nov 2026). */
class FooterTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Storage::fake('public');
        $this->super = $this->superAdmin();
    }

    private function upload(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->super)->post(route('admin.appearance.footer-image'), $extra + [
            'footer_image' => UploadedFile::fake()->image('footer.jpg', 1600, 400),
            'footer_image_alt' => 'Boats in Mahón harbour',
        ]);
    }

    public function test_footer_shows_the_placeholder_until_an_image_is_added(): void
    {
        $this->get('/en')->assertOk()
            ->assertSee('data-footer-image="placeholder"', false)
            ->assertSee('images/placeholders/header-1600.webp', false);
    }

    public function test_footer_links_are_orange_buttons_with_white_text(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $footer = substr($html, strpos($html, '<footer'));

        $this->assertMatchesRegularExpression('#<a href="[^"]*/partners"[^>]*class="[^"]*bg-orange-700[^"]*text-white#', $footer);
        $this->assertStringNotContainsString('items-center underline', $footer);
    }

    public function test_super_admin_adds_an_image_that_goes_live_on_publish(): void
    {
        $this->actingAs($this->super)->get(route('admin.appearance.edit'))->assertOk()->assertSee('Footer image');

        $this->upload()->assertSessionHasNoErrors();
        $draft = \App\Support\Appearance::footerImage(true);
        $this->assertNotNull($draft);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', parse_url($draft['url'], PHP_URL_PATH)));

        auth()->logout();
        $this->get('/en')->assertSee('data-footer-image="placeholder"', false);

        $this->actingAs($this->super)->post(route('admin.appearance.publish'))->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/en')->assertSee('data-footer-image="photo"', false)->assertSee('alt="Boats in Mahón harbour"', false);
        $this->get('/en/partners')->assertSee('alt="Boats in Mahón harbour"', false);
    }

    public function test_image_needs_alt_text_and_a_large_enough_file(): void
    {
        $this->upload(['footer_image_alt' => ''])->assertSessionHasErrors('footer_image_alt');
        $this->upload(['footer_image' => UploadedFile::fake()->image('small.jpg', 800, 200)])->assertSessionHasErrors('footer_image');
        $this->upload(['footer_image' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('footer_image');
        $this->assertNull(\App\Support\Appearance::footerImage(true));
    }

    public function test_alt_text_can_change_without_a_new_file_and_the_image_can_be_removed(): void
    {
        $this->upload();
        $path = \App\Support\Appearance::get('footer.image', true)['path'];

        $this->actingAs($this->super)->post(route('admin.appearance.footer-image'), ['footer_image_alt' => 'Cala Macarella'])->assertSessionHasNoErrors();
        $this->assertEquals(['path' => $path, 'alt' => 'Cala Macarella'], \App\Support\Appearance::get('footer.image', true));   // MySQL JSON reorders keys

        $this->actingAs($this->super)->post(route('admin.appearance.footer-image'), ['remove' => 1])->assertSessionHasNoErrors();
        $this->assertNull(\App\Support\Appearance::footerImage(true));
    }

    public function test_only_super_admins_can_set_it(): void
    {
        $admin = $this->islandAdmin(\App\Models\Island::factory()->create());
        $owner = User::factory()->owner()->create();

        $this->actingAs($admin)->post(route('admin.appearance.footer-image'), ['footer_image_alt' => 'x'])->assertForbidden();
        $this->actingAs($owner)->post(route('admin.appearance.footer-image'), ['footer_image_alt' => 'x'])->assertForbidden();
        $this->assertNull(\App\Support\Appearance::footerImage(true));
    }
}
