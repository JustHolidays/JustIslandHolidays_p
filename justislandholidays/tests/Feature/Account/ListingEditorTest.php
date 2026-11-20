<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus as S;
use App\Jobs\ProcessListingImage;
use App\Listings\DisplayLocation;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class ListingEditorTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    private Island $island;

    private Area $area;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->island = Island::factory()->create(['centre_lat' => 39.95, 'centre_lng' => 4.1]);
        $this->area = Area::factory()->create(['island_id' => $this->island->id]);
        $this->owner = User::factory()->owner()->create();
    }

    private function basics(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Villa Sunset',
            'summary' => 'A lovely home with sea views.',
            'island_id' => $this->island->id,
            'area_id' => $this->area->id,
            'property_type' => 'villa',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'sleeps' => 6,
            'has_private_pool' => 1,
            'pets' => 'not_stated',
            'price' => 1200,
            'currency' => 'EUR',
        ];
    }

    private function createListing(): Listing
    {
        $this->actingAs($this->owner)->post('/account/listings', $this->basics())->assertRedirect();

        return Listing::where('owner_id', $this->owner->id)->latest('id')->firstOrFail();
    }

    public function test_pages_open_for_listers_only(): void
    {
        $this->actingAs($this->owner)->get('/account/listings')->assertOk();
        $this->actingAs($this->owner)->get('/account/listings/create')->assertOk()->assertSee('Basics');

        $this->actingAs($this->superAdmin())->get('/account/listings')->assertForbidden();
        $this->actingAs(User::factory()->owner()->unverified()->create())->get('/account/listings')->assertRedirect('/account/email/verify');
    }

    public function test_owner_creates_a_draft_with_english_text(): void
    {
        $listing = $this->createListing();

        $this->assertSame(S::Draft, $listing->status);
        $this->assertSame('Villa Sunset', $listing->title('en'));
        $this->assertSame('villa-sunset', $listing->slug);
        $this->assertSame(120000, $listing->min_price_week_cents);
        $this->assertNull($listing->pets_allowed, 'Not stated is stored as NULL.');
        $this->assertTrue($listing->has_private_pool);
        $this->assertSame('free', $listing->plan_code);
    }

    public function test_slug_is_unique_per_island(): void
    {
        \App\Models\Plan::where('code', 'free')->update(['max_listings' => null]);   // room for two listings
        $first = $this->createListing();
        $second = $this->createListing();

        $this->assertSame('villa-sunset', $first->slug);
        $this->assertSame('villa-sunset-2', $second->slug);
    }

    public function test_resort_must_be_on_the_chosen_island(): void
    {
        $otherArea = Area::factory()->create();

        $this->actingAs($this->owner)->post('/account/listings', $this->basics(['area_id' => $otherArea->id]))
            ->assertSessionHasErrors('area_id');
    }

    public function test_admin_only_fields_are_ignored(): void
    {
        $this->actingAs($this->owner)->post('/account/listings', $this->basics([
            'status' => 'live', 'plan_code' => 'featured', 'rank_score' => 999, 'owner_id' => 999, 'deeplink_status' => 'ok',
        ]));

        $listing = Listing::sole();
        $this->assertSame(S::Draft, $listing->status);
        $this->assertSame('free', $listing->plan_code);
        $this->assertSame($this->owner->id, $listing->owner_id);
        $this->assertSame('unchecked', $listing->deeplink_status);
    }

    public function test_other_owners_cannot_open_or_change_the_listing(): void
    {
        $listing = $this->createListing();
        $stranger = User::factory()->owner()->create();

        $this->actingAs($stranger)->get("/account/listings/{$listing->id}/edit/basics")->assertForbidden();
        $this->actingAs($stranger)->put("/account/listings/{$listing->id}/basics", $this->basics(['title' => 'Hacked']))->assertForbidden();
        $this->actingAs($stranger)->post("/account/listings/{$listing->id}/status/submit")->assertForbidden();
        $this->assertSame('Villa Sunset', $listing->fresh()->title('en'));
    }

    public function test_location_is_private_and_the_public_pin_is_offset(): void
    {
        $listing = $this->createListing();

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/location", [
            'coordinates' => '39.9112, 4.2650',
            'contact_email' => 'inbox@example.com',
            'website_url' => 'https://myvilla.example',
        ])->assertRedirect(route('account.listings.edit', [$listing, 'photo']));

        $listing->refresh()->load('private');
        $this->assertEqualsWithDelta(39.9112, (float) $listing->private->lat, 0.000001);
        $this->assertEqualsWithDelta(4.2650, (float) $listing->private->lng, 0.000001);
        $metres = DisplayLocation::metresBetween(39.9112, 4.2650, (float) $listing->display_lat, (float) $listing->display_lng);
        $this->assertGreaterThanOrEqual(240, $metres);
        $this->assertLessThanOrEqual(360, $metres);
    }

    public function test_coordinates_off_the_islands_are_refused(): void
    {
        $listing = $this->createListing();

        foreach (['51.5, -0.12', 'not a place', '4.26, 39.91'] as $bad) {
            $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/location", ['coordinates' => $bad, 'contact_email' => 'a@b.example'])
                ->assertSessionHasErrors('coordinates');
        }
        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/location", ['coordinates' => '39.9,4.1', 'contact_email' => 'a@b.example', 'website_url' => 'http://insecure.example'])
            ->assertSessionHasErrors('website_url');
    }

    public function test_photo_upload_needs_alt_text_and_permission_then_is_resized(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $listing = $this->createListing();
        $photo = UploadedFile::fake()->image('villa.jpg', 2000, 1500);

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/photo", ['photo' => $photo, 'image_alt' => 'White villa'])
            ->assertSessionHasErrors('photo_permission');

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/photo", ['photo' => $photo, 'image_alt' => '', 'photo_permission' => '1'])
            ->assertSessionHasErrors('image_alt');

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/photo", [
            'photo' => $photo, 'image_alt' => 'White villa with a pool', 'photo_permission' => '1',
        ])->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('White villa with a pool', $listing->image_alt);
        $this->assertMatchesRegularExpression('/^listings\/\d+\/[0-9a-f-]+-1600\.webp$/', $listing->image_path);
        foreach (ProcessListingImage::WIDTHS as $w) {
            Storage::disk('public')->assertExists(str_replace('-1600.webp', "-{$w}.webp", $listing->image_path));
        }
        [$width] = getimagesizefromstring(Storage::disk('public')->get(str_replace('-1600.webp', '-400.webp', $listing->image_path)));
        $this->assertSame(400, $width);
    }

    public function test_small_or_non_image_files_are_refused(): void
    {
        Queue::fake();
        $listing = $this->createListing();

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/photo", [
            'photo' => UploadedFile::fake()->image('tiny.jpg', 300, 200), 'image_alt' => 'x', 'photo_permission' => '1',
        ])->assertSessionHasErrors('photo');

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/photo", [
            'photo' => UploadedFile::fake()->create('virus.exe', 100), 'image_alt' => 'x', 'photo_permission' => '1',
        ])->assertSessionHasErrors('photo');

        Queue::assertNothingPushed();
    }

    public function test_an_owner_or_agent_changing_their_link_stays_live(): void
    {
        $agent = User::factory()->agent()->create();
        foreach ([$this->owner, $agent] as $lister) {
            $listing = $this->setStatus($this->completeListing($lister, null, ['plan_code' => 'standard']), S::Live);   // links are for paid plans

            $this->actingAs($lister)->get("/account/listings/{$listing->id}/edit/link")->assertOk()
                ->assertDontSee('sends your listing back');
            $this->actingAs($lister)->put("/account/listings/{$listing->id}/link", ['deeplink_url' => 'https://booking.example.com/new'])
                ->assertSessionHasNoErrors();

            $fresh = $listing->fresh();
            $this->assertSame(S::Live, $fresh->status, 'Owners and agents look after their own links.');
            $this->assertSame('https://booking.example.com/new', $fresh->deeplink_url);
            $this->assertTrue($fresh->showsCheckAvailability());
        }
    }

    public function test_an_affiliate_changing_their_link_goes_back_for_review(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->setStatus($this->completeListing($affiliate, null, ['deeplink_url' => 'https://partner.example/1', 'deeplink_status' => 'ok']), S::Live);

        $this->actingAs($affiliate)->get("/account/listings/{$listing->id}/edit/link")->assertOk()
            ->assertSee('Changing the link sends your listing back for a quick check');
        $this->actingAs($affiliate)->put("/account/listings/{$listing->id}/link", ['deeplink_url' => 'https://partner.example/2'])
            ->assertSessionHasNoErrors();

        $fresh = $listing->fresh();
        $this->assertSame(S::PendingReview, $fresh->status);
        $this->assertSame('unchecked', $fresh->deeplink_status);
    }

    public function test_link_must_be_https_and_owners_cannot_set_a_sub_id(): void
    {
        $listing = $this->createListing();
        $listing->forceFill(['plan_code' => 'standard'])->save();   // links are for paid plans

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/link", ['deeplink_url' => 'http://booking.example.com'])
            ->assertSessionHasErrors('deeplink_url');
        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/link", ['deeplink_url' => 'https://booking.example.com', 'deeplink_subid_param' => 'subid'])
            ->assertSessionHasErrors('deeplink_subid_param');
    }

    public function test_licence_submission_sets_pending_and_stores_document_privately(): void
    {
        Storage::fake('local');
        $listing = $this->createListing();

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/licence", ['registration_number' => 'ETV/1'])
            ->assertSessionHasErrors('responsibility');

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/licence", [
            'registration_number' => 'ETV/1234',
            'document' => UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf'),
            'responsibility' => '1',
        ])->assertSessionHasNoErrors();

        $licence = $listing->fresh()->licence;
        $this->assertSame('pending', $licence->status);
        Storage::disk('local')->assertExists($licence->document_path);

        $this->actingAs($this->owner)->get("/account/listings/{$listing->id}/licence-document")->assertOk();
        $this->actingAs(User::factory()->owner()->create())->get("/account/listings/{$listing->id}/licence-document")->assertForbidden();
    }

    public function test_changing_a_verified_licence_on_a_live_listing_needs_a_new_check(): void
    {
        $listing = $this->setStatus($this->completeListing($this->owner, licenceVerified: true), S::Live);

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/licence", ['registration_number' => 'ETV/9999', 'responsibility' => '1']);

        $fresh = $listing->fresh();
        $this->assertSame(S::PendingReview, $fresh->status);
        $this->assertSame('pending', $fresh->licence->status);
    }

    public function test_owners_are_told_they_look_after_their_own_link(): void
    {
        $listing = $this->completeListing($this->owner, null, ['plan_code' => 'standard']);
        $this->actingAs($this->owner)->get("/account/listings/{$listing->id}/edit/link")->assertOk()
            ->assertSee('You look after this link.')
            ->assertSee('We do not check whether it works.');

        $affiliate = User::factory()->affiliate()->create();
        $theirs = $this->completeListing($affiliate);
        $this->actingAs($affiliate)->get("/account/listings/{$theirs->id}/edit/link")->assertOk()
            ->assertDontSee('You look after this link.');
    }

    public function test_affiliates_have_no_licence_step(): void
    {
        $affiliate = User::factory()->affiliate()->create();
        $listing = $this->completeListing($affiliate);

        $this->actingAs($affiliate)->get("/account/listings/{$listing->id}/edit/licence")->assertNotFound();
        $this->actingAs($affiliate)->put("/account/listings/{$listing->id}/licence", ['registration_number' => 'X', 'responsibility' => '1'])->assertNotFound();
    }

    public function test_full_flow_to_pending_review(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $listing = $this->createListing();
        $base = "/account/listings/{$listing->id}";

        $this->actingAs($this->owner)->put("{$base}/location", ['coordinates' => '39.9112, 4.2650', 'contact_email' => 'inbox@example.com']);
        $this->actingAs($this->owner)->put("{$base}/photo", ['photo' => UploadedFile::fake()->image('v.jpg', 1200, 900), 'image_alt' => 'Villa', 'photo_permission' => '1']);
        $this->actingAs($this->owner)->put("{$base}/link", ['deeplink_url' => '']);
        $this->actingAs($this->owner)->put("{$base}/licence", ['registration_number' => 'ETV/1234', 'responsibility' => '1']);
        $this->actingAs($this->owner)->get("{$base}/edit/review")->assertOk()->assertSee('Everything is in place');

        $this->actingAs($this->owner)->post("{$base}/status/submit")->assertRedirect('/account/listings');

        $this->assertSame(S::PendingReview, $listing->fresh()->status);
    }

    public function test_listing_waiting_for_review_is_locked(): void
    {
        $listing = $this->setStatus($this->completeListing($this->owner), S::PendingReview);

        $this->actingAs($this->owner)->put("/account/listings/{$listing->id}/basics", $this->basics(['title' => 'Changed']))
            ->assertSessionHasErrors('status');
        $this->assertSame('Villa Test', $listing->fresh()->title('en'));
    }

    public function test_submit_errors_are_shown_to_the_owner(): void
    {
        $listing = $this->createListing();

        $this->actingAs($this->owner)->from("/account/listings/{$listing->id}/edit/review")
            ->post("/account/listings/{$listing->id}/status/submit")
            ->assertSessionHasErrors();
        $this->assertSame(S::Draft, $listing->fresh()->status);
    }
}
