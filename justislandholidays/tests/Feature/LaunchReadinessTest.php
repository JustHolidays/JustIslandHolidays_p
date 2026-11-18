<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    // --- Security headers and the main domain --------------------------------------------

    public function test_security_headers_on_pages_and_redirects(): void
    {
        foreach (['/en', '/'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/en')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/en')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_other_domain_redirects_permanently_to_the_main_one(): void
    {
        config(['site.canonical_host' => 'justislandholidays.co.uk']);

        $this->get('https://justislandholidays.uk/en/menorca?x=1')->assertStatus(301)->assertRedirect('https://justislandholidays.co.uk/en/menorca?x=1');
        $this->get('https://www.justislandholidays.co.uk/en')->assertStatus(301)->assertRedirect('https://justislandholidays.co.uk/en');
        $this->get('https://justislandholidays.co.uk/en')->assertOk();
    }

    public function test_no_redirect_when_no_main_domain_is_set(): void
    {
        config(['site.canonical_host' => '']);

        $this->get('https://justislandholidays.uk/en')->assertOk();
    }

    // --- Legal pages ----------------------------------------------------------------------

    public function test_legal_pages_are_seeded_as_published_drafts(): void
    {
        $this->seed(LegalPagesSeeder::class);

        foreach (LegalPagesSeeder::SLUGS as $slug) {
            $this->get("/en/p/{$slug}")->assertOk()->assertSee('Draft for legal review');
        }
    }

    public function test_privacy_notice_covers_what_the_spec_requires(): void
    {
        $this->seed(LegalPagesSeeder::class);

        $this->get('/en/p/privacy')->assertOk()
            ->assertSee('86 Manston Road, Ramsgate, CT11 0RJ')
            ->assertSee('We email your enquiry to the owner')
            ->assertSee('Our senior staff, and the staff member who looks after the island')
            ->assertSee('New enquiries are flagged for them')
            ->assertSee('24 months')
            ->assertSee('13 months')
            ->assertSee('We never store your internet address')
            ->assertSee('We never see or store card details')
            ->assertSee('do not need to ask for cookie consent')
            ->assertSee('Information Commissioner')
            ->assertSee('<table>', false);
    }

    public function test_terms_cover_licence_responsibility_and_no_vat(): void
    {
        $this->seed(LegalPagesSeeder::class);

        $this->get('/en/p/terms')->assertOk()
            ->assertSee('You are responsible for holding a valid licence')
            ->assertSee('not VAT registered')
            ->assertSee('We do not take bookings or payments from guests');
    }

    public function test_seeder_never_overwrites_an_edited_page(): void
    {
        $this->seed(LegalPagesSeeder::class);
        $page = Page::where('slug', 'privacy')->whereNull('island_id')->sole();
        PageTranslation::where('page_id', $page->id)->where('lang', 'en')->update(['body_md' => 'Reviewed text']);

        $this->seed(LegalPagesSeeder::class);

        $this->assertSame('Reviewed text', PageTranslation::where('page_id', $page->id)->where('lang', 'en')->value('body_md'));
        $this->assertSame(1, Page::where('slug', 'privacy')->count());
    }

    public function test_footer_links_to_the_accessibility_statement(): void
    {
        $this->get('/en')->assertSee(route('pages.show', ['lang' => 'en', 'slug' => 'accessibility']), false);
    }

    // --- Launch gate ----------------------------------------------------------------------

    public function test_launch_gate_fails_on_a_fresh_install(): void
    {
        $this->seed(LegalPagesSeeder::class);

        $this->artisan('launch:check')
            ->expectsOutputToContain('not ready to launch')
            ->assertFailed();
    }

    public function test_manual_items_are_recorded_with_who_confirmed_them(): void
    {
        $this->artisan('launch:check --confirm=legal_review')->assertFailed();
        $this->artisan('launch:check --confirm=nonsense --by=X')->assertFailed();

        $this->artisan('launch:check', ['--confirm' => 'legal_review', '--by' => 'A. Lawyer', '--note' => 'Reviewed v1'])->assertSuccessful();

        $this->assertSame('A. Lawyer', SiteSetting::find('launch.confirmed')->value['legal_review']['by']);
    }

    public function test_launch_gate_passes_when_everything_is_in_place(): void
    {
        $this->seed(LegalPagesSeeder::class);
        foreach (PageTranslation::all() as $t) {
            PageTranslation::where('page_id', $t->page_id)->where('lang', 'en')
                ->update(['body_md' => str_replace([LegalPagesSeeder::DRAFT_NOTICE, '[TO CONFIRM'], ['', '[confirmed'], $t->body_md)]);
        }
        SiteSetting::create(['setting_key' => 'licences.register_url', 'value' => ['url' => 'https://register.example/']]);
        User::factory()->superAdmin()->count(2)->create();
        foreach (array_keys(\App\Console\Commands\LaunchCheck::MANUAL) as $key) {
            $this->artisan('launch:check', ['--confirm' => $key, '--by' => 'Owner']);
        }

        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://justislandholidays.co.uk',
            'site.canonical_host' => 'justislandholidays.co.uk', 'site.contact_email' => 'hello@justislandholidays.co.uk',
            'mail.default' => 'smtp',
            'services.paypal.mode' => 'live', 'services.paypal.client_id' => 'id', 'services.paypal.webhook_id' => 'WH',
            'site.ip_hash_salt' => str_repeat('s', 64), 'site.altcha.hmac_key' => str_repeat('h', 64),
        ]);
        $this->app['env'] = 'production';

        $checks = collect((new \App\Console\Commands\LaunchCheck())->automaticChecks());
        $failing = $checks->reject(fn ($c) => $c[1])->map(fn ($c) => $c[0])->values()->all();

        // The database check can only pass when the suite itself runs on MySQL (composer test:mysql).
        $expected = \App\Support\Database\MySql::isMySql() ? [] : ['Database is MySQL'];
        $this->assertSame($expected, $failing);
    }
}
