<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/**
 * Owner and agent links are for paid plans, and can open a booking page (Check availability),
 * their website (Visit Our Website) or their Facebook page (Visit Our Facebook). 20 Nov 2026.
 */
class LinkTypeTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner, null, ['plan_code' => 'standard']), ListingStatus::Live)->load(['island', 'area']);
    }

    private function saveLink(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->put(route('account.listings.link', $this->listing), $data);
    }

    private function button(): string
    {
        $html = $this->get($this->listing->publicUrl('en'))->assertOk()->getContent();
        preg_match('#<a href="[^"]*/go/'.$this->listing->public_ref.'[^"]*"[^>]*>([^<]*)</a>#', $html, $m);

        return trim($m[1] ?? '');
    }

    public function test_each_type_shows_its_own_button_text(): void
    {
        $this->saveLink(['link_type' => 'website', 'deeplink_url' => 'https://villa-example.com'])->assertSessionHasNoErrors();
        $this->assertSame('Visit Our Website', $this->button());

        $this->saveLink(['link_type' => 'facebook', 'deeplink_url' => 'https://www.facebook.com/villaexample'])->assertSessionHasNoErrors();
        $this->assertSame('Visit Our Facebook', $this->button());

        $this->saveLink(['link_type' => 'booking', 'deeplink_url' => 'https://booking.example.com/villa'])->assertSessionHasNoErrors();
        $this->assertSame('Check availability', $this->button());

        $this->assertSame('booking', $this->listing->fresh()->link_type);
        $this->assertSame(ListingStatus::Live, $this->listing->fresh()->status, 'Owners look after their own links.');
    }

    public function test_facebook_type_needs_a_facebook_address_and_the_other_types_do_not_take_one(): void
    {
        $this->saveLink(['link_type' => 'facebook', 'deeplink_url' => 'https://villa-example.com'])->assertSessionHasErrors('deeplink_url');
        $this->saveLink(['link_type' => 'website', 'deeplink_url' => 'https://m.facebook.com/villaexample'])->assertSessionHasErrors('deeplink_url');
        $this->saveLink(['link_type' => 'facebook', 'deeplink_url' => 'https://notfacebook.com/x'])->assertSessionHasErrors('deeplink_url');
        $this->saveLink(['link_type' => 'other', 'deeplink_url' => 'https://villa-example.com'])->assertSessionHasErrors('link_type');
        $this->saveLink(['link_type' => 'facebook', 'deeplink_url' => 'https://fb.me/villaexample'])->assertSessionHasNoErrors();
    }

    public function test_editor_offers_the_three_types_on_paid_plans(): void
    {
        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'link']))->assertOk()
            ->assertSee('What does the link open?')
            ->assertSee('Visit Our Website')
            ->assertSee('Visit Our Facebook')
            ->assertSee('name="deeplink_url"', false);
    }

    public function test_free_plan_has_no_link_and_keeps_an_old_one_hidden(): void
    {
        $this->saveLink(['link_type' => 'website', 'deeplink_url' => 'https://villa-example.com']);
        $this->listing->forceFill(['plan_code' => 'free'])->save();

        $this->assertNull($this->button() ?: null);
        $this->get(route('go', ['ref' => $this->listing->public_ref]))->assertRedirect($this->listing->publicUrl('en'));

        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'link']))->assertOk()
            ->assertSee('kept but not shown while you are on the Free Plan')
            ->assertDontSee('name="deeplink_url"', false);

        // Saving on Free changes nothing; the link is still there for an upgrade.
        $this->saveLink(['link_type' => 'booking', 'deeplink_url' => 'https://other.example.com'])->assertSessionHasNoErrors();
        $this->assertSame('https://villa-example.com', $this->listing->fresh()->deeplink_url);

        $this->listing->forceFill(['plan_code' => 'standard'])->save();
        $this->assertSame('Visit Our Website', $this->button());
    }

    public function test_affiliates_always_have_check_availability(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->setStatus($this->completeListing($affiliate, null, ['deeplink_url' => 'https://partner.example/1', 'deeplink_status' => 'ok', 'link_type' => 'website']), ListingStatus::Live);

        $this->assertSame('Check availability', $listing->linkLabel());
        $this->actingAs($affiliate)->get(route('account.listings.edit', [$listing, 'link']))->assertOk()->assertDontSee('What does the link open?');
    }

    public function test_admin_adds_a_property_with_a_facebook_link(): void
    {
        $this->assertTrue(Listing::isFacebookUrl('https://www.facebook.com/x'));
        $this->assertTrue(Listing::isFacebookUrl('https://facebook.com/x'));
        $this->assertFalse(Listing::isFacebookUrl('https://facebook.com.evil.example/x'));
        $this->assertFalse(Listing::isFacebookUrl(null));

        $this->actingAs($this->superAdmin())->get(route('admin.listings.create'))->assertOk()
            ->assertSee('What does the link open?')->assertSee('Visit Our Facebook');
    }
}
