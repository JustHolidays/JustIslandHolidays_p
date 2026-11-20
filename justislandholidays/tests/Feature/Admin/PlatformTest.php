<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus as S;
use App\Models\AuditLog;
use App\Models\HeroImage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Appearance, and plans and subscriptions. */
class PlatformTest extends TestCase
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

    private function colours(array $o = []): array
    {
        return $o + ['brand' => '#0b4f6c', 'button' => '#0b4f6c', 'background' => '#ffffff', 'band' => '#f1f5f4', 'text' => '#1f2933'];
    }

    public function test_contrast_ratio_matches_wcag(): void
    {
        $this->assertSame(21.0, Appearance::contrast('#000000', '#ffffff'));
        $this->assertSame(1.0, Appearance::contrast('#777777', '#777777'));
        $this->assertEqualsWithDelta(4.48, Appearance::contrast('#777777', '#ffffff'), 0.01);
    }

    public function test_colours_go_live_only_when_published(): void
    {
        $this->actingAs($this->super)->put('/admin/appearance/colours', $this->colours(['brand' => '#123456']))->assertSessionHasNoErrors();

        $this->get('/en')->assertDontSee('--colour-brand: #123456', false);
        $this->actingAs($this->super)->get('/en?preview=draft')->assertSee('--colour-brand: #123456', false)->assertSee('Preview of the draft');

        $this->actingAs($this->super)->post('/admin/appearance/publish')->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/en')->assertSee('--colour-brand: #123456', false);
        $this->assertSame(1, AuditLog::where('action', 'appearance.publish')->count());
    }

    public function test_preview_is_for_super_admins_only(): void
    {
        $this->actingAs($this->super)->put('/admin/appearance/colours', $this->colours(['brand' => '#123456']));
        auth()->logout();

        $this->get('/en?preview=draft')->assertDontSee('--colour-brand: #123456', false);
    }

    public function test_low_contrast_blocks_publishing(): void
    {
        $this->actingAs($this->super)->put('/admin/appearance/colours', $this->colours(['text' => '#dddddd']));

        $this->actingAs($this->super)->get('/admin/appearance')->assertSee('too low, needs 4.5');
        $this->actingAs($this->super)->post('/admin/appearance/publish')->assertSessionHasErrors('colours');
        $this->assertSame('#1f2933', Appearance::colours(false)['text']);
    }

    public function test_undo_restores_the_previous_values(): void
    {
        $this->actingAs($this->super)->put('/admin/appearance/colours', $this->colours(['brand' => '#111111']));
        $this->actingAs($this->super)->post('/admin/appearance/publish');
        $this->actingAs($this->super)->put('/admin/appearance/colours', $this->colours(['brand' => '#222222']));
        $this->actingAs($this->super)->post('/admin/appearance/publish');

        $this->actingAs($this->super)->post('/admin/appearance/undo')->assertSessionHasNoErrors();

        $this->assertSame('#111111', Appearance::colours(false)['brand']);
    }

    public function test_logo_must_be_png_with_alt_text_and_shows_after_publishing(): void
    {
        $this->actingAs($this->super)->post('/admin/appearance/logo', ['logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'), 'alt' => 'JIH'])
            ->assertSessionHasErrors('logo');

        $this->actingAs($this->super)->post('/admin/appearance/logo', ['logo' => UploadedFile::fake()->image('logo.png', 400, 80), 'alt' => 'Just Island Holidays logo'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->super)->post('/admin/appearance/publish');

        $this->get('/en')->assertSee('alt="Just Island Holidays logo"', false);
    }

    public function test_favicon_sizes_are_generated(): void
    {
        $this->actingAs($this->super)->post('/admin/appearance/favicon', ['favicon' => UploadedFile::fake()->image('f.png', 600, 400)])->assertSessionHasErrors('favicon');
        $this->actingAs($this->super)->post('/admin/appearance/favicon', ['favicon' => UploadedFile::fake()->image('f.png', 512, 512)])->assertSessionHasNoErrors();
        $this->actingAs($this->super)->post('/admin/appearance/publish');

        $this->assertSame([32, 180, 192, 512], array_keys(Appearance::favicons(false)));
        $this->get('/en')->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_hero_upload_starts_off_and_goes_live_after_switching_on_and_publishing(): void
    {
        $this->actingAs($this->super)->post('/admin/appearance/hero', ['hero' => UploadedFile::fake()->image('h.jpg', 1200, 600), 'alt_text' => 'Beach'])->assertSessionHasErrors('hero');
        $this->actingAs($this->super)->post('/admin/appearance/hero', ['hero' => UploadedFile::fake()->image('h.jpg', 2400, 1000), 'alt_text' => 'Turquoise cove'])->assertSessionHasNoErrors();

        $hero = HeroImage::sole();
        $this->assertFalse($hero->is_active);
        Storage::disk('public')->assertExists(str_replace('-2400', '-800', $hero->image_path));

        $this->actingAs($this->super)->put('/admin/appearance/hero', ['mode' => 'first', 'heroes' => [$hero->id => ['active' => 1, 'sort' => 1, 'alt_text' => 'Turquoise cove']]]);
        $this->assertFalse($hero->fresh()->is_active, 'Still a draft.');
        $this->actingAs($this->super)->post('/admin/appearance/publish');

        $this->assertTrue($hero->fresh()->is_active);
        $this->get('/en')->assertSee('alt="Turquoise cove"', false);
    }

    public function test_island_admins_cannot_open_appearance_or_plans(): void
    {
        $admin = $this->islandAdmin(\App\Models\Island::factory()->create());

        $this->actingAs($admin)->put('/admin/appearance/colours', $this->colours())->assertForbidden();
        $this->actingAs($admin)->post('/admin/appearance/publish')->assertForbidden();
        $this->actingAs($admin)->put('/admin/plans/'.Plan::value('id'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($admin)->post('/admin/plans/grant', [])->assertForbidden();
    }

    public function test_home_page_hero_images_are_a_cross_fading_slideshow(): void
    {
        foreach (['Beach', 'Harbour', 'Cliffs'] as $i => $alt) {
            HeroImage::create(['image_path' => "hero/h{$i}-2400.webp", 'alt_text' => $alt, 'sort_order' => $i, 'is_active' => true]);
        }

        $html = $this->get('/en')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'data-hero-slide>') + substr_count($html, 'data-hero-slide '), 'Every active picture is on the page.');
        $this->assertSame(1, substr_count($html, 'opacity-100'), 'One picture shows at first.');
        $this->assertStringContainsString('transition-opacity duration-[1600ms]', $html);
        $this->assertStringContainsString('data-hero-pause', $html);
        $this->assertStringContainsString('Pause pictures', $html);

        // "First" mode always starts with the first picture.
        \App\Models\SiteSetting::updateOrCreate(['setting_key' => 'home.hero_mode'], ['value' => ['mode' => 'first']]);
        \Illuminate\Support\Facades\Cache::flush();
        $first = $this->get('/en')->getContent();
        $this->assertMatchesRegularExpression('#alt="Beach"[^>]*fetchpriority="high"#', $first);

        // One picture: no slideshow controls.
        HeroImage::where('alt_text', '!=', 'Beach')->update(['is_active' => false]);
        $this->assertStringNotContainsString('data-hero-pause', $this->get('/en')->getContent());
    }

    public function test_plans_list_shows_yearly_and_featured_period_prices_and_no_monthly_column(): void
    {
        Plan::where('code', 'featured')->update(['price_3m_cents' => 4500, 'price_12m_cents' => 15000, 'price_year_cents' => null]);
        $owner = User::factory()->owner()->create();
        (new Subscription())->forceFill(['user_id' => $owner->id, 'plan_id' => Plan::where('code', 'featured')->value('id'),
            'billing_interval' => 'one_off', 'status' => 'active', 'ends_at' => now()->addMonths(3)])->save();

        $this->actingAs($this->super)->get('/admin/plans')->assertOk()
            ->assertSee('Price for 1 year')
            ->assertDontSee('>Monthly<', false)
            ->assertSee('3 months:')->assertSee('12 months:')
            ->assertSee('(one-off payment)')
            ->assertDontSee('(monthly)');
    }

    public function test_plan_prices_and_paypal_ids_are_edited_here(): void
    {
        $plan = Plan::where('code', 'standard')->sole();

        $this->actingAs($this->super)->put("/admin/plans/{$plan->id}", [
            'name' => 'Standard', 'max_listings' => 2, 'search_boost' => 15, 'show_direct_contact' => 1, 'currency' => 'EUR',
            'price_year' => '149.50', 'paypal_plan_id_year' => 'P-1AB23456CD789012E', 'is_active' => 1, 'max_photos' => 12,
        ])->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(14950, $plan->price_year_cents);
        // Yearly only: the old monthly price is left as it was, so existing monthly subscriptions keep running.
        $this->assertSame(1200, $plan->price_month_cents);
        $this->assertSame(2, $plan->max_listings);

        $this->actingAs($this->super)->put("/admin/plans/{$plan->id}", ['name' => 'S', 'search_boost' => 1, 'currency' => 'EUR', 'max_photos' => 3, 'paypal_plan_id_year' => 'nonsense'])
            ->assertSessionHasErrors('paypal_plan_id_year');
    }

    public function test_granted_plan_applies_until_the_date_then_lapses(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'early@example.com']);
        $listing = $this->setStatus($this->completeListing($owner), S::Live);

        $this->actingAs($this->super)->post('/admin/plans/grant', ['email' => 'early@example.com', 'plan' => 'featured', 'until' => now()->addMonths(6)->toDateString(), 'reason' => 'Early owner offer'])
            ->assertSessionHasNoErrors();

        $this->assertSame('featured', $listing->fresh()->plan_code);
        $this->assertTrue($listing->fresh()->isFeatured());
        $this->assertSame('Early owner offer', AuditLog::where('action', 'plan.override')->sole()->detail['reason']);

        $this->travel(7)->months();
        app(\App\Billing\AccountPlan::class)->sync($owner);
        $this->assertSame('free', $listing->fresh()->plan_code);
    }

    public function test_grant_is_refused_for_affiliates_and_wrong_audience(): void
    {
        User::factory()->affiliate()->create(['email' => 'aff@example.com']);
        User::factory()->owner()->create(['email' => 'own@example.com']);

        $this->actingAs($this->super)->post('/admin/plans/grant', ['email' => 'aff@example.com', 'plan' => 'standard', 'until' => now()->addMonth()->toDateString(), 'reason' => 'x x x'])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->super)->post('/admin/plans/grant', ['email' => 'own@example.com', 'plan' => 'agent_plus', 'until' => now()->addMonth()->toDateString(), 'reason' => 'x x x'])
            ->assertSessionHasErrors('plan');
        $this->assertSame(0, Subscription::count());
    }
}
