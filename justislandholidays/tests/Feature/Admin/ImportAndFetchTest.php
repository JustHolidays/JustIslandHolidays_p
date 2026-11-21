<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus as S;
use App\Listings\Import\PageParser;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Support\Fetch\FetchException;
use App\Support\Fetch\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportAndFetchTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = <<<'HTML'
        <html><head>
        <title>Ignored title</title>
        <meta property="og:title" content="Villa Mar Blau &amp; pool">
        <meta property="og:description" content="Sea views over Cala Galdana.">
        <meta property="og:image" content="https://provider.example/img/villa.jpg">
        <script type="application/ld+json">{"@context":"https://schema.org","@type":"VacationRental","name":"Villa Mar Blau",
          "geo":{"latitude":39.938,"longitude":3.958},"address":{"addressLocality":"Cala Galdana"},
          "containsPlace":{"@type":"Accommodation","numberOfBedrooms":4,"numberOfBathroomsTotal":3,"occupancy":{"maxValue":8}}}</script>
        </head><body>Hello</body></html>
        HTML;

    private function jpeg(int $width = 1200, int $height = 900): string
    {
        $img = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($img);

        return (string) ob_get_clean();
    }

    private function fetcher(array $ips = ['93.184.216.34']): SafeFetcher
    {
        return new SafeFetcher(fn () => $ips);
    }

    // --- SafeFetcher -------------------------------------------------------------------------

    public function test_only_https_public_addresses_are_fetched(): void
    {
        Http::fake();
        $cases = [
            'http://provider.example/x' => [],
            'https://localhost/x' => [],
            'https://user:pass@provider.example/x' => [],
            'https://provider.example:8443/x' => [],
            'https://10.0.0.5/x' => [],
            'https://[::1]/x' => [],
            'https://internal.example/x' => ['192.168.1.10'],
            'https://metadata.example/x' => ['169.254.169.254'],
        ];

        foreach ($cases as $url => $ips) {
            try {
                $this->fetcher($ips ?: ['93.184.216.34'])->get($url);
                $this->fail("Should have refused {$url}");
            } catch (FetchException) {
                $this->addToAssertionCount(1);
            }
        }
        Http::assertNothingSent();
    }

    public function test_redirects_are_followed_a_limited_number_of_times_and_rechecked(): void
    {
        Http::fake([
            'https://a.example/start' => Http::response('', 302, ['Location' => '/next']),
            'https://a.example/next' => Http::response('done', 200),
        ]);
        $result = $this->fetcher()->get('https://a.example/start');
        $this->assertSame('https://a.example/next', $result->url);
        $this->assertSame('done', $result->body);

        Http::fake(['https://b.example/*' => Http::response('', 302, ['Location' => 'http://b.example/plain'])]);
        $this->expectException(FetchException::class);
        $this->fetcher()->get('https://b.example/start');
    }

    public function test_redirect_loops_stop(): void
    {
        Http::fake(['https://loop.example/*' => Http::response('', 302, ['Location' => 'https://loop.example/again'])]);

        $this->expectExceptionMessage('redirects too many times');
        $this->fetcher()->get('https://loop.example/start');
    }

    public function test_size_cap(): void
    {
        // A fresh response each time: a response body stream can only be read once.
        Http::fake(['https://big.example/*' => fn () => Http::response(str_repeat('a', 5000))]);

        $this->assertTrue($this->fetcher()->get('https://big.example/x', 1000)->truncated);
        $this->expectException(FetchException::class);
        $this->fetcher()->get('https://big.example/x', 1000, allowTruncate: false);
    }

    // --- Parser ------------------------------------------------------------------------------

    public function test_parser_reads_open_graph_and_schema_org(): void
    {
        $out = (new PageParser())->parse(self::PAGE, 'https://provider.example/villa');

        $this->assertSame('Villa Mar Blau & pool', $out['title']);
        $this->assertSame('Sea views over Cala Galdana.', $out['summary']);
        $this->assertSame('https://provider.example/img/villa.jpg', $out['image_url']);
        $this->assertSame([39.938, 3.958], [$out['lat'], $out['lng']]);
        $this->assertSame([4, 3, 8], [$out['bedrooms'], $out['bathrooms'], $out['sleeps']]);
        $this->assertSame('Cala Galdana', $out['locality']);
    }

    public function test_parser_copes_with_an_empty_page(): void
    {
        $out = (new PageParser())->parse('<html><body>nothing</body></html>', 'https://p.example/');

        $this->assertNull($out['title']);
        $this->assertSame([], $out['found']);
    }

    // --- Import ------------------------------------------------------------------------------

    private function setUpImport(): array
    {
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->app->instance(SafeFetcher::class, $this->fetcher());

        $island = Island::factory()->create(['slug' => 'menorca']);
        $area = Area::factory()->create(['island_id' => $island->id, 'slug' => 'cala-galdana', 'name' => ['en' => 'Cala Galdana']]);
        $affiliate = User::factory()->affiliate()->create();
        $admin = $this->islandAdmin($island);

        return [$island, $area, $affiliate, $admin];
    }

    private function storeData(Area $area, User $affiliate, array $o = []): array
    {
        return $o + [
            'affiliate_id' => $affiliate->id, 'source_url' => 'https://provider.example/villa', 'title' => 'Villa Mar Blau',
            'summary' => 'Sea views.', 'area_id' => $area->id, 'property_type' => 'villa', 'bedrooms' => 4, 'bathrooms' => 3,
            'sleeps' => 8, 'currency' => 'EUR', 'coordinates' => '39.938, 3.958', 'deeplink_url' => 'https://provider.example/book?aff=JIH',
            'image_url' => 'https://provider.example/img/villa.jpg', 'image_permission' => 1, 'image_alt' => 'Villa with pool', 'save' => 'draft',
        ];
    }

    public function test_fetch_fills_the_draft_and_guesses_the_resort(): void
    {
        [, $area, $affiliate, $admin] = $this->setUpImport();
        Http::fake(['https://provider.example/villa' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs($admin)->post('/admin/listings/import/fetch', ['affiliate_id' => $affiliate->id, 'source_url' => 'https://provider.example/villa'])
            ->assertOk()
            ->assertSee('value="Villa Mar Blau &amp; pool"', false)
            ->assertSee('value="39.938, 3.958"', false)
            ->assertSee('<option value="'.$area->id.'" selected', false)
            ->assertDontSee('<img src="https://provider.example/img/villa.jpg"', false);
    }

    public function test_unreachable_page_still_gives_a_blank_draft(): void
    {
        [, , $affiliate, $admin] = $this->setUpImport();
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->actingAs($admin)->post('/admin/listings/import/fetch', ['affiliate_id' => $affiliate->id, 'source_url' => 'https://provider.example/villa'])
            ->assertOk()->assertSee('Fill in the details by hand')->assertSee('name="title"', false);
    }

    public function test_fetched_photo_needs_permission_and_is_copied_not_hot_linked(): void
    {
        [, $area, $affiliate, $admin] = $this->setUpImport();
        $jpg = $this->jpeg();
        Http::fake(['https://provider.example/img/villa.jpg' => fn () => Http::response($jpg, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->actingAs($admin)->post('/admin/listings/import', $this->storeData($area, $affiliate, ['image_permission' => 0]))->assertSessionHasErrors('image_permission');
        $this->assertSame(0, Listing::count());

        $this->actingAs($admin)->post('/admin/listings/import', $this->storeData($area, $affiliate))->assertRedirect();

        $listing = Listing::sole();
        $this->assertSame($affiliate->id, $listing->owner_id);
        $this->assertSame(S::Draft, $listing->status);
        $this->assertNull($listing->pets_allowed);
        $this->assertSame('https://provider.example/villa', $listing->source_url);
        $this->assertStringStartsWith('listings/'.$listing->id.'/', $listing->image_path);
        Storage::disk('public')->assertExists($listing->image_path);
    }

    public function test_approve_and_publish_needs_a_tested_link(): void
    {
        [, $area, $affiliate, $admin] = $this->setUpImport();
        $jpg = $this->jpeg();
        Http::fake(['https://provider.example/img/villa.jpg' => fn () => Http::response($jpg, 200)]);

        $this->actingAs($admin)->post('/admin/listings/import', $this->storeData($area, $affiliate, ['save' => 'publish']))->assertSessionHasErrors('link_tested');

        $this->actingAs($admin)->post('/admin/listings/import', $this->storeData($area, $affiliate, ['save' => 'publish', 'link_tested' => 1]))->assertRedirect();

        $listing = Listing::sole();
        $this->assertSame(S::Live, $listing->status);
        $this->get($listing->publicUrl('en'))->assertOk()->assertDontSee('provider.example/villa')->assertSee('Check availability');
    }

    // --- Finding a photo big enough (21 Nov 2026) ----------------------------------------------

    public function test_parser_lists_every_photo_the_page_offers_main_first(): void
    {
        $html = <<<'HTML'
            <html><head>
            <meta property="og:image" content="https://cdn.example/pic/600/main.jpg">
            <meta name="twitter:image" content="/share/main-wide.jpg">
            <script type="application/ld+json">{"@type":"VacationRental","image":["https://cdn.example/a.jpg",{"url":"https://cdn.example/b.jpg"}]}</script>
            </head><body>
            <img src="/img/logo.svg" alt="">
            <img src="/img/gallery-small.jpg" srcset="/img/g-600.jpg 600w, /img/g-1400.jpg 1400w" alt="">
            <img data-src="https://cdn.example/lazy.webp" src="data:image/gif;base64,R0lGOD" alt="">
            <img src="http://insecure.example/x.jpg" alt="">
            </body></html>
            HTML;

        $out = (new PageParser())->parse($html, 'https://provider.example/villa');

        $this->assertSame([
            'https://cdn.example/pic/600/main.jpg',
            'https://provider.example/share/main-wide.jpg',
            'https://cdn.example/a.jpg',
            'https://cdn.example/b.jpg',
            'https://provider.example/img/g-1400.jpg',
            'https://provider.example/img/gallery-small.jpg',
            'https://cdn.example/lazy.webp',
        ], $out['image_candidates']);
        $this->assertNotContains('image_candidates', $out['found']);
    }

    public function test_larger_versions_follow_common_size_patterns(): void
    {
        $f = fn ($u) => \App\Listings\Import\ImageFinder::largerVersions($u);

        $this->assertSame(['https://image.novasol.com/pic/1600/eml/x.jpg', 'https://image.novasol.com/pic/1024/eml/x.jpg'], $f('https://image.novasol.com/pic/600/eml/x.jpg'));
        $this->assertSame(['https://s.example/sites/default/files/Villa%20One-1001.jpg'], $f('https://s.example/sites/default/files/styles/villa_main/public/Villa%20One-1001.jpg.webp?itok=abc'));
        $this->assertSame(['https://w.example/uploads/2026/05/pool.jpg'], $f('https://w.example/uploads/2026/05/pool-600x400.jpg'));
        $this->assertSame(['https://q.example/img.jpg?w=1600&fit=crop'], $f('https://q.example/img.jpg?w=600&h=400&fit=crop'));
        $this->assertSame([], $f('https://plain.example/photos/villa.jpg'));
    }

    public function test_a_small_preview_is_swapped_for_a_bigger_copy(): void
    {
        [, , $affiliate, $admin] = $this->setUpImport();
        $page = str_replace('https://provider.example/img/villa.jpg', 'https://cdn.example/pic/600/villa.jpg', self::PAGE);
        $small = $this->jpeg(600, 382);
        $big = $this->jpeg(1024, 652);
        Http::fake([
            'https://provider.example/villa' => Http::response($page, 200),
            'https://cdn.example/pic/1600/*' => Http::response('', 403),
            'https://cdn.example/pic/1024/*' => fn () => Http::response($big, 200),
            'https://cdn.example/pic/600/*' => fn () => Http::response($small, 200),
        ]);

        $this->actingAs($admin)->post('/admin/listings/import/fetch', ['affiliate_id' => $affiliate->id, 'source_url' => 'https://provider.example/villa'])
            ->assertOk()
            ->assertSee('name="image_url" value="https://cdn.example/pic/1024/villa.jpg"', false)
            ->assertSee('Photo found on the page (1024 × 652 pixels)')
            ->assertDontSee('<img src="https://cdn.example', false);
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_a_bigger_gallery_photo_is_used_when_the_main_one_is_small(): void
    {
        [, , $affiliate, $admin] = $this->setUpImport();
        $page = str_replace('</body>', '<img src="/img/gallery-2.jpg" alt=""></body>', self::PAGE);
        $small = $this->jpeg(640, 480);
        $big = $this->jpeg(1200, 800);
        Http::fake([
            'https://provider.example/villa' => Http::response($page, 200),
            'https://provider.example/img/villa.jpg' => fn () => Http::response($small, 200),
            'https://provider.example/img/gallery-2.jpg' => fn () => Http::response($big, 200),
        ]);

        $this->actingAs($admin)->post('/admin/listings/import/fetch', ['affiliate_id' => $affiliate->id, 'source_url' => 'https://provider.example/villa'])
            ->assertOk()
            ->assertSee('name="image_url" value="https://provider.example/img/gallery-2.jpg"', false)
            ->assertSee('(1200 × 800 pixels)');
    }

    public function test_only_small_photos_ask_for_an_upload(): void
    {
        [, , $affiliate, $admin] = $this->setUpImport();
        $small = $this->jpeg(600, 382);
        Http::fake([
            'https://provider.example/villa' => Http::response(self::PAGE, 200),
            'https://provider.example/img/villa.jpg' => fn () => Http::response($small, 200),
        ]);

        $this->actingAs($admin)->post('/admin/listings/import/fetch', ['affiliate_id' => $affiliate->id, 'source_url' => 'https://provider.example/villa'])
            ->assertOk()
            ->assertSee('Only a small photo was found on the page (600 × 382 pixels). Upload one at least 800 pixels wide.')
            ->assertSee('name="image_url" value=""', false)
            ->assertDontSee('id="image_permission"', false);
    }

    public function test_island_admin_cannot_import_onto_another_island(): void
    {
        [, , $affiliate, $admin] = $this->setUpImport();
        $otherArea = Area::factory()->create();

        $this->actingAs($admin)->post('/admin/listings/import', $this->storeData($otherArea, $affiliate, ['image_url' => null]))->assertForbidden();
        $this->assertSame(0, Listing::count());
    }

    public function test_affiliate_must_be_an_active_affiliate(): void
    {
        [, $area, , $admin] = $this->setUpImport();
        $owner = User::factory()->owner()->create();

        $this->actingAs($admin)->post('/admin/listings/import', $this->storeData($area, $owner))->assertSessionHasErrors('affiliate_id');
    }
}
