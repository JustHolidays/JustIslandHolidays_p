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

    private function listing(string $planCode, ?User $owner = null, bool $showPhone = true): Listing
    {
        $listing = Listing::factory()->live()->titled('Villa Sunset')->create(['plan_code' => $planCode] + ($owner ? ['owner_id' => $owner->id] : []));

        $private = new ListingPrivate([
            'lat' => 39.911234, 'lng' => 4.265012,
            'address_line' => 'Carrer Secret 7', 'postcode' => '07769',
            'contact_email' => 'private-inbox@example.com',
            'contact_phone' => '+34 600 111 222',
            'website_url' => 'https://www.villasunset.example/',
            'show_phone' => $showPhone,
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

    public function test_paid_plan_hides_the_phone_when_the_owner_chose_hidden(): void
    {
        $this->get($this->listing('standard', null, false)->publicUrl('en'))->assertOk()
            ->assertSee('Contact the owner directly')   // the website still shows
            ->assertDontSee('+34 600 111 222')
            ->assertDontSee('href="tel:', false);
    }

    public function test_the_phone_is_hidden_until_the_owner_chooses(): void
    {
        $listing = Listing::factory()->live()->titled('New home')->create(['plan_code' => 'standard']);
        $private = new ListingPrivate(['lat' => 39.9, 'lng' => 4.1, 'contact_email' => 'x@example.com', 'contact_phone' => '+34 600 999 888']);
        $private->listing_id = $listing->id;
        $private->save();

        $this->assertFalse($private->fresh()->show_phone, 'Hidden by default.');
        $this->get($listing->load(['island', 'area'])->publicUrl('en'))->assertOk()->assertDontSee('+34 600 999 888');
    }

    public function test_owners_choose_in_the_location_step(): void
    {
        $listing = $this->listing('standard', null, false);
        $owner = $listing->owner;

        $this->actingAs($owner)->get("/account/listings/{$listing->id}/edit/location")->assertOk()
            ->assertSee('Show your phone number on your listing page?')
            ->assertSee('id="show-phone-no" type="radio" name="show_phone" value="0" class="h-5 w-5" checked', false);

        $this->actingAs($owner)->put("/account/listings/{$listing->id}/location", [
            'coordinates' => '39.911234, 4.265012', 'contact_email' => 'private-inbox@example.com',
            'contact_phone' => '+34 600 111 222', 'website_url' => 'https://www.villasunset.example/', 'show_phone' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($listing->private->fresh()->show_phone);
        $this->get($listing->publicUrl('en'))->assertSee('href="tel:+34600111222"', false);
    }

    public function test_affiliates_are_not_asked(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->listing('free', $affiliate);

        $this->actingAs($affiliate)->get("/account/listings/{$listing->id}/edit/location")->assertOk()
            ->assertDontSee('Show your phone number on your listing page?');
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
