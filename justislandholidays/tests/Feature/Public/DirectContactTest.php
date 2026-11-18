<?php

namespace Tests\Feature\Public;

use App\Models\Listing;
use App\Models\ListingPrivate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Paid plans with show_direct_contact show the owner's phone and website; nothing else private. */
class DirectContactTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();   // free: no direct contact; standard: direct contact
    }

    private function listing(string $planCode, ?User $owner = null): Listing
    {
        $listing = Listing::factory()->live()->titled('Villa Sunset')->create(['plan_code' => $planCode] + ($owner ? ['owner_id' => $owner->id] : []));

        $private = new ListingPrivate([
            'lat' => 39.911234, 'lng' => 4.265012,
            'address_line' => 'Carrer Secret 7', 'postcode' => '07769',
            'contact_email' => 'private-inbox@example.com',
            'contact_phone' => '+34 600 111 222',
            'website_url' => 'https://www.villasunset.example/',
        ]);
        $private->listing_id = $listing->id;
        $private->save();

        return $listing->load(['island', 'area']);
    }

    public function test_free_plan_shows_the_enquiry_form_only(): void
    {
        $this->get($this->listing('free')->publicUrl('en'))->assertOk()
            ->assertDontSee('Contact the owner directly')
            ->assertDontSee('+34 600 111 222')
            ->assertDontSee('villasunset.example');
    }

    public function test_paid_plan_shows_phone_and_website(): void
    {
        $this->get($this->listing('standard')->publicUrl('en'))->assertOk()
            ->assertSee('Contact the owner directly')
            ->assertSee('href="tel:+34600111222"', false)
            ->assertSee('href="https://www.villasunset.example/" rel="sponsored nofollow noopener"', false)
            ->assertSee('Contact the owner');   // the enquiry form stays too
    }

    public function test_paid_plan_never_shows_email_address_or_exact_location(): void
    {
        $this->get($this->listing('standard')->publicUrl('en'))->assertOk()
            ->assertDontSee('private-inbox@example.com')
            ->assertDontSee('Carrer Secret 7')
            ->assertDontSee('07769')
            ->assertDontSee('39.911234')
            ->assertDontSee('4.265012');
    }

    public function test_affiliate_listings_never_show_direct_contact(): void
    {
        $affiliate = User::factory()->affiliate()->create();

        $this->get($this->listing('standard', $affiliate)->publicUrl('en'))->assertOk()
            ->assertDontSee('Contact the owner directly')
            ->assertDontSee('+34 600 111 222');
    }

    public function test_nothing_shown_when_no_phone_or_website_was_given(): void
    {
        $listing = $this->listing('standard');
        ListingPrivate::whereKey($listing->id)->update(['contact_phone' => null, 'website_url' => null]);

        $this->get($listing->publicUrl('en'))->assertOk()->assertDontSee('Contact the owner directly');
    }
}
