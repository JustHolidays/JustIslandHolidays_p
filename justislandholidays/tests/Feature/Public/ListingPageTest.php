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
