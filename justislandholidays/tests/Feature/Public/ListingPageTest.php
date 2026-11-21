<?php

namespace Tests\Feature\Public;

use App\Enums\ListingStatus;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPageTest extends TestCase
{
    use RefreshDatabase;

    private function listing(array $attributes = [], ?\Closure $factory = null): Listing
    {
        $f = Listing::factory()->live()->titled('Villa Sunset', 'Lovely sea views')->withPrivate('secret-inbox@example.com');
        if ($factory) {
            $f = $factory($f);
        }

        return $f->create($attributes)->load(['island', 'area']);
    }

    public function test_live_listing_page_shows_the_critical_details(): void
    {
        $listing = $this->listing(['sleeps' => 6, 'bedrooms' => 3, 'min_price_week_cents' => 120000]);

        $this->get($listing->publicUrl('en'))->assertOk()
            ->assertSee('Villa Sunset')
            ->assertSee('Lovely sea views')
            ->assertSee('€1,200')
            ->assertSee('From Price')
            ->assertSee('€1,200 a week')
            ->assertDontSee('Guide price')
            ->assertSee('VacationRental')
            ->assertSee('rel="canonical" href="'.$listing->publicUrl('en').'"', false);
    }

    public function test_private_contact_details_never_appear(): void
    {
        $listing = $this->listing();

        $this->get($listing->publicUrl('en'))->assertOk()
            ->assertDontSee('secret-inbox@example.com')
            ->assertDontSee('+34 600 000 000');
    }

    public function test_pets_fact_is_hidden_when_not_stated(): void
    {
        $unknown = $this->listing(['pets_allowed' => null]);
        $no = $this->listing(['pets_allowed' => false]);
        $yes = $this->listing(['pets_allowed' => true]);

        $this->get($unknown->publicUrl('en'))->assertDontSee('data-fact="pets"', false);
        $this->get($no->publicUrl('en'))->assertSee('data-fact="pets"', false)->assertSee('Not allowed');
        $this->get($yes->publicUrl('en'))->assertSee('data-fact="pets"', false)->assertSee('Allowed');
    }

    public function test_check_availability_banner_shows_owner_links_and_working_affiliate_links(): void
    {
        $none = $this->listing();
        $ok = $this->listing(['plan_code' => 'standard'], fn ($f) => $f->withDeeplink());   // owner links: paid plans
        // An owner's link is never checked, so an old "broken" result does not hide it.
        $ownerOld = $this->listing(['plan_code' => 'standard'], fn ($f) => $f->withDeeplink(status: 'broken'));
        $affiliateBroken = $this->listing(['owner_id' => User::factory()->affiliate()], fn ($f) => $f->withDeeplink(status: 'broken'));

        $this->get($none->publicUrl('en'))->assertDontSee('Check availability');
        $this->get($ownerOld->publicUrl('en'))->assertSee('Check availability');
        $this->get($affiliateBroken->publicUrl('en'))->assertDontSee('Check availability');
        $this->get($ok->publicUrl('en'))
            ->assertSee('Check availability')
            ->assertSee('/go/'.$ok->public_ref.'?', false)
            ->assertSee('rel="sponsored nofollow"', false)
            ->assertDontSee('booking.example.com', false);
    }

    public function test_affiliate_check_availability_opens_in_a_new_tab_and_owner_links_do_not(): void
    {
        $affiliate = $this->listing(['owner_id' => User::factory()->affiliate()], fn ($f) => $f->withDeeplink());
        $owned = $this->listing(['plan_code' => 'standard'], fn ($f) => $f->withDeeplink());

        $html = $this->get($affiliate->publicUrl('en'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="[^"]*/go/'.$affiliate->public_ref.'[^"]*"\s+target="_blank"\s+rel="sponsored nofollow noopener"#', $html);
        $this->assertStringContainsString('<span class="sr-only"> (opens in a new tab)</span>', $html);
        $this->assertStringContainsString('in a new tab. We may earn a commission', $html);

        $ownerHtml = $this->get($owned->publicUrl('en'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="[^"]*/go/'.$owned->public_ref.'[^"]*"\s+rel="sponsored nofollow"#', $ownerHtml);
        $this->assertStringNotContainsString('target="_blank" rel="sponsored', $ownerHtml);
    }

    public function test_price_guide_note_is_left_off_affiliate_homes_and_location_names_the_home(): void
    {
        $affiliate = $this->listing(['owner_id' => User::factory()->affiliate(), 'min_price_week_cents' => 90000], fn ($f) => $f->withDeeplink());
        $owned = $this->listing(['min_price_week_cents' => 120000]);

        $this->get($affiliate->publicUrl('en'))->assertOk()
            ->assertDontSee('data-price-guide-note', false)
            ->assertDontSee('Prices are a guide')
            ->assertSee('Villa Sunset is inside the circle, in or near');
        $this->get($owned->publicUrl('en'))->assertOk()
            ->assertSee('Prices are a guide from the owner. The owner confirms the final price and terms.')
            ->assertSee('Villa Sunset is inside the circle, in or near')
            ->assertDontSee('The home is inside the circle');
    }

    public function test_affiliate_pages_show_the_from_price_above_the_partner_logo(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $priced = $this->listing(['owner_id' => $affiliate->id, 'min_price_week_cents' => 95000], fn ($f) => $f->withDeeplink());
        $unpriced = $this->listing(['owner_id' => $affiliate->id, 'min_price_week_cents' => null], fn ($f) => $f->withDeeplink());
        $owned = $this->listing(['plan_code' => 'standard', 'min_price_week_cents' => 120000], fn ($f) => $f->withDeeplink());

        $html = $this->get($priced->publicUrl('en'))->assertOk()->getContent();
        $this->assertStringContainsString('data-affiliate-price', $html);
        $this->assertMatchesRegularExpression('#data-affiliate-price>.*?€950 a week#s', $html);
        $this->assertLessThan(strpos($html, 'data-partner-logo'), strpos($html, 'data-affiliate-price'), 'The price sits above the logo.');
        $this->assertStringContainsString('bg-orange-700', substr($html, strpos($html, 'data-affiliate-price') - 120, 200));

        $this->get($unpriced->publicUrl('en'))->assertOk()->assertDontSee('data-affiliate-price', false)->assertSee('data-partner-logo', false);
        $this->get($owned->publicUrl('en'))->assertOk()->assertDontSee('data-affiliate-price', false);
    }

    public function test_island_pages_list_only_resorts_with_live_homes(): void
    {
        $island = Island::factory()->create(['is_active' => true, 'slug' => 'menorca']);
        $busy = \App\Models\Area::factory()->create(['island_id' => $island->id, 'name' => ['en' => 'Busy Bay'], 'slug' => 'busy-bay']);
        $quiet = \App\Models\Area::factory()->create(['island_id' => $island->id, 'name' => ['en' => 'Quiet Cove'], 'slug' => 'quiet-cove']);
        $draftOnly = \App\Models\Area::factory()->create(['island_id' => $island->id, 'name' => ['en' => 'Draft Point'], 'slug' => 'draft-point']);
        Listing::factory()->live()->count(2)->create(['island_id' => $island->id, 'area_id' => $busy->id]);
        Listing::factory()->create(['island_id' => $island->id, 'area_id' => $draftOnly->id]);   // a draft does not count

        $html = $this->get(route('islands.show', ['en', 'menorca']))->assertOk()->getContent();
        $this->assertStringContainsString('Busy Bay', $html);
        $this->assertMatchesRegularExpression('#Busy Bay\s*<span[^>]*>\(2\)</span>#', $html);
        $this->assertStringNotContainsString(route('areas.show', ['menorca', 'quiet-cove']), $html);
        $this->assertStringNotContainsString(route('areas.show', ['menorca', 'draft-point']), $html);

        // With no live homes at all, the "Resorts and towns" list is left out.
        Listing::query()->update(['status' => ListingStatus::Paused]);
        $this->get(route('islands.show', ['en', 'menorca']))->assertOk()->assertDontSee('Resorts and towns');
    }

    public function test_free_plan_has_the_contact_form_and_agents_pages_say_agent(): void
    {
        $owners = $this->listing(['plan_code' => 'free']);
        $agents = $this->listing(['plan_code' => 'free', 'owner_id' => User::factory()->agent()]);

        $this->get($owners->publicUrl('en'))->assertOk()
            ->assertSee('id="enquiry"', false)->assertSee('Contact the owner')->assertSee('name="check_in"', false)
            ->assertDontSee('the agent');
        $this->get($agents->publicUrl('en'))->assertOk()
            ->assertSee('id="enquiry"', false)->assertSee('Contact the agent')
            ->assertSee('Your message goes straight to the agent')
            ->assertSee('my details are sent to the agent')
            ->assertDontSee('Contact the owner');
    }

    public function test_bookmark_menu_links_to_the_sections_the_home_has(): void
    {
        $free = $this->listing(['plan_code' => 'free']);
        $html = $this->get($free->publicUrl('en'))->assertOk()->getContent();
        $nav = substr($html, strpos($html, 'data-bookmarks'), 1500);

        $this->assertStringContainsString('href="#key-facts"', $nav);
        $this->assertStringContainsString('href="#location"', $nav);
        $this->assertStringNotContainsString('href="#rates"', $nav, 'No rates on the Free Plan.');
        $this->assertStringNotContainsString('href="#availability"', $nav, 'No calendar on the Free Plan.');
        foreach (['key-facts', 'location'] as $anchor) {
            $this->assertStringContainsString('id="'.$anchor.'"', $html);
        }
        $this->assertLessThan(strpos($html, 'id="key-facts"'), strpos($html, 'data-bookmarks'), 'The menu sits under the title, above the sections.');

        $affiliate = $this->listing(['owner_id' => User::factory()->affiliate()], fn ($f) => $f->withDeeplink());
        $this->get($affiliate->publicUrl('en'))->assertOk()->assertDontSee('data-bookmarks', false);
    }

    public function test_share_buttons_point_to_our_site_which_passes_visitors_on(): void
    {
        $listing = $this->listing();
        $url = rawurlencode($listing->publicUrl('en'));
        $html = $this->get($listing->publicUrl('en'))->assertOk()->getContent();

        // The page links to our own addresses (blockers hide links straight to share pages).
        foreach (['facebook', 'whatsapp', 'x', 'pinterest'] as $network) {
            $this->assertStringContainsString('href="'.e(route('share', ['network' => $network, 'ref' => $listing->public_ref, 'l' => 'en'])).'"', $html);
        }
        $this->assertStringNotContainsString('facebook.com/sharer', $html);
        $this->assertStringNotContainsString('twitter.com/intent', $html);
        $this->assertStringNotContainsString('data-share', $html, 'Nothing marked "share" for blockers to hide.');
        $this->assertStringContainsString('href="mailto:?subject=Villa%20Sunset', $html);
        $this->assertStringContainsString('Share on Facebook', $html);
        $this->assertStringNotContainsString('connect.facebook.net', $html);
        $this->assertMatchesRegularExpression('#<li hidden data-pass-on-copy-item>#', $html);

        // Each address passes the visitor on to that network's share page.
        $this->get(route('share', ['network' => 'facebook', 'ref' => $listing->public_ref]))->assertRedirect('https://www.facebook.com/sharer/sharer.php?u='.$url);
        $this->assertStringStartsWith('https://wa.me/?text=Villa%20Sunset', $this->get(route('share', ['network' => 'whatsapp', 'ref' => $listing->public_ref]))->headers->get('Location'));
        $this->assertStringStartsWith('https://twitter.com/intent/tweet?url='.$url, $this->get(route('share', ['network' => 'x', 'ref' => $listing->public_ref]))->headers->get('Location'));
        $this->assertStringStartsWith('https://pinterest.com/pin/create/button/?url='.$url, $this->get(route('share', ['network' => 'pinterest', 'ref' => $listing->public_ref]))->headers->get('Location'));
    }

    public function test_share_addresses_only_work_for_live_homes_and_known_networks(): void
    {
        $draft = Listing::factory()->create();
        $live = $this->listing();

        $this->get(route('share', ['network' => 'facebook', 'ref' => $draft->public_ref]))->assertNotFound();
        $this->get('/share/myspace/'.$live->public_ref)->assertNotFound();
    }

    public function test_wheelchair_friendly_homes_show_the_icon_on_cards_and_their_page(): void
    {
        $amenity = \App\Models\Amenity::firstOrCreate(['code' => 'wheelchair_friendly'], ['category' => 'accessibility', 'name' => ['en' => 'Wheelchair friendly'], 'is_filterable' => true]);
        $accessible = $this->listing();
        $accessible->amenities()->attach($amenity->id);
        $other = $this->listing(['island_id' => $accessible->island_id]);

        $this->get($accessible->publicUrl('en'))->assertOk()->assertSee('data-wheelchair', false)->assertSee('Wheelchair friendly');
        $this->get($other->publicUrl('en'))->assertOk()
            ->assertDontSee('<span class="mt-2 inline-flex', false);   // not on this home's own page

        // On the island page's cards: once, for the accessible home only.
        $island = $this->get(route('islands.show', ['en', $accessible->island->slug]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($island, 'data-wheelchair'));
    }

    public function test_licence_number_is_shown(): void
    {
        $listing = $this->listing();
        $licence = new ListingLicence(['registration_number' => 'ETV/9999']);
        $licence->listing_id = $listing->id;
        $licence->forceFill(['status' => 'verified'])->save();

        $this->get($listing->publicUrl('en'))->assertSee('ETV/9999');
    }

    public function test_owner_listing_has_an_enquiry_form_and_affiliate_listing_does_not(): void
    {
        $owned = $this->listing();
        $affiliate = User::factory()->affiliate()->create();
        $partner = $this->listing(['owner_id' => $affiliate->id], fn ($f) => $f->withDeeplink());

        $this->get($owned->publicUrl('en'))->assertSee('Contact the owner')->assertSee('altcha-widget', false);
        $this->get($partner->publicUrl('en'))->assertDontSee('Contact the owner')->assertSee('Check availability');
    }

    public function test_old_slug_or_wrong_area_redirects_permanently(): void
    {
        $listing = $this->listing();
        $island = $listing->island->slug;

        $this->get("/en/{$island}/{$listing->area->slug}/old-name-{$listing->public_ref}")
            ->assertStatus(301)->assertRedirect($listing->publicUrl('en'));
        $this->get("/en/{$island}/somewhere-else/{$listing->slug}-{$listing->public_ref}")
            ->assertStatus(301)->assertRedirect($listing->publicUrl('en'));
    }

    public function test_not_live_listings_return_404_and_removed_ones_410(): void
    {
        $url = fn (Listing $l) => '/en/'.$l->island->slug.'/'.$l->area->slug.'/'.$l->slug.'-'.$l->public_ref;

        foreach ([ListingStatus::Draft, ListingStatus::PendingReview, ListingStatus::Paused, ListingStatus::Rejected] as $status) {
            $l = $this->listing(['status' => $status]);
            $this->get($url($l))->assertNotFound();
        }

        $archived = $this->listing(['status' => ListingStatus::Archived]);
        $this->get($url($archived))->assertStatus(410);

        $deleted = $this->listing();
        $deletedUrl = $url($deleted);
        $deleted->delete();
        $this->get($deletedUrl)->assertStatus(410);

        $this->get('/en/menorca/area/villa-zzzzzzzz')->assertNotFound();
    }

    public function test_listing_on_inactive_island_is_hidden(): void
    {
        $listing = $this->listing();
        $listing->island->update(['is_active' => false]);

        $this->get($listing->publicUrl('en'))->assertNotFound();
    }
}
