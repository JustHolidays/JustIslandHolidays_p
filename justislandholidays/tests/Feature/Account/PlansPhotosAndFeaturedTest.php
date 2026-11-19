<?php

namespace Tests\Feature\Account;

use App\Billing\AccountPlan;
use App\Models\FeaturedPurchase;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/**
 * Plans (19 Nov 2026): Owners and Featured Property names, card images, photo limits
 * (Free 3, paid 12), booking calendar line, yearly prices, and Featured Property bought
 * for 3, 6, 9 or 12 months with a one-off PayPal payment.
 */
class PlansPhotosAndFeaturedTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private const API = 'https://api-m.sandbox.paypal.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        config(['services.paypal' => [
            'mode' => 'sandbox', 'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'webhook_id' => 'WH-TEST', 'brand_name' => 'Just Island Holidays',
        ]]);
        Plan::where('code', 'standard')->update(['price_year_cents' => 12000, 'paypal_plan_id_year' => 'P-STD-YEAR']);
        Plan::where('code', 'featured')->update(['price_3m_cents' => 3000, 'price_6m_cents' => 5500, 'price_12m_cents' => 9900]);
    }

    private function png(int $w = 1000, int $h = 700): UploadedFile
    {
        return UploadedFile::fake()->image('photo.jpg', $w, $h);
    }

    // --- Names, card and page --------------------------------------------------------------

    public function test_plans_are_renamed_and_photo_limits_set(): void
    {
        $this->assertSame('Free Plan', Plan::where('code', 'free')->value('name'));
        $this->assertSame('Owners Plan', Plan::where('code', 'standard')->value('name'));
        $this->assertSame('Featured Plan', Plan::where('code', 'featured')->value('name'));
        $this->assertEqualsCanonicalizing(['free' => 3, 'standard' => 12, 'featured' => 12, 'agent_plus' => 12], Plan::pluck('max_photos', 'code')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(3, Plan::where('code', 'free')->first()->photoLimit());
        $this->assertSame(12, Plan::where('code', 'agent_plus')->first()->photoLimit());
    }

    public function test_the_rename_migration_only_changes_the_old_default_names(): void
    {
        Plan::where('code', 'free')->update(['name' => 'Free']);
        Plan::where('code', 'standard')->update(['name' => 'Owners']);
        Plan::where('code', 'featured')->update(['name' => 'Something we chose']);

        (require database_path('migrations/2026_11_19_000009_rename_plans.php'))->up();

        $this->assertSame('Free Plan', Plan::where('code', 'free')->value('name'));
        $this->assertSame('Owners Plan', Plan::where('code', 'standard')->value('name'));
        $this->assertSame('Something we chose', Plan::where('code', 'featured')->value('name'), 'A name chosen in admin is kept.');
        $this->get('/en/owners')->assertSee('Free Plan')->assertSee('Owners Plan');
    }

    public function test_list_your_holiday_home_page_shows_cards_with_images_photos_and_orange_prices(): void
    {
        Plan::where('code', 'standard')->update(['has_booking_calendar' => true]);

        $html = $this->get('/en/owners')->assertOk()->getContent();

        foreach (['free', 'standard', 'featured', 'agent_plus'] as $code) {
            $this->assertStringContainsString("data-plan=\"{$code}\"", $html);
        }
        $this->assertSame(4, substr_count($html, 'images/placeholders/plan-card.webp'), 'A placeholder card image until one is added.');
        $this->assertSame(4, substr_count($html, 'class="bg-orange-700 px-5 py-3 text-white" data-plan-price'));
        $this->assertStringContainsString('Up to 3 photos', $html);
        $this->assertStringContainsString('Up to 12 photos per property', $html, 'Agent Plus counts per property.');
        $this->assertStringContainsString('Booking calendar', $html);
        $this->assertStringContainsString('€120 for 1 year', $html);
        $this->assertStringContainsString('>Free</p>', $html);
        $this->assertStringContainsString('3 months: €30', $html);
        $this->assertStringContainsString('12 months: €99', $html);
        $this->assertStringNotContainsString('9 months', $html, 'A period without a price is not offered.');
        $this->assertStringNotContainsString('a month', $html);
    }

    public function test_super_admin_sets_a_card_image_photo_limit_calendar_and_period_prices(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $featured = Plan::where('code', 'featured')->sole();

        $this->actingAs($admin)->get(route('admin.plans.index'))->assertOk()->assertSee('Gift A Plan')->assertDontSee('Grant a plan');
        $this->actingAs($admin)->get(route('admin.plans.edit', $featured))->assertOk()->assertSee('3 months')->assertDontSee('Monthly price');

        $this->actingAs($admin)->put(route('admin.plans.update', $featured), [
            'name' => 'Featured Plan', 'max_photos' => 15, 'search_boost' => 20, 'is_featured' => 1, 'has_booking_calendar' => 1,
            'currency' => 'EUR', 'price_3m' => '35', 'price_6m' => '60', 'price_9m' => '80', 'price_12m' => '95', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $featured->refresh();
        $this->assertSame(15, (int) $featured->max_photos);
        $this->assertTrue($featured->has_booking_calendar);
        $this->assertSame([3 => 3500, 6 => 6000, 9 => 8000, 12 => 9500], $featured->periodPrices());

        $this->actingAs($admin)->post(route('admin.plans.header', $featured), ['header_image' => UploadedFile::fake()->image('beach.jpg', 1600, 600), 'header_image_alt' => 'Sunny beach'])->assertSessionHasNoErrors();
        $featured->refresh();
        Storage::disk('public')->assertExists($featured->header_image_path);
        $this->assertSame([800, 300], array_slice(getimagesizefromstring(Storage::disk('public')->get($featured->header_image_path)), 0, 2));
        $this->get('/en/owners')->assertSee('alt="Sunny beach"', false);
        $this->assertDatabaseHas('audit_log', ['action' => 'plan.header_update']);
    }

    // --- Photos ----------------------------------------------------------------------------

    public function test_free_plan_owners_can_have_3_photos_and_paid_12(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $listing = $this->completeListing($owner, null, ['plan_code' => 'free']);

        $add = fn () => $this->actingAs($owner)->post(route('account.listings.photos.store', $listing), [
            'extra_photo' => $this->png(), 'extra_alt' => 'Terrace', 'extra_permission' => 1,
        ]);

        $add()->assertSessionHasNoErrors();
        $add()->assertSessionHasNoErrors();
        $add()->assertSessionHasErrors('extra_photo');   // main + 2 = 3, the Free limit
        $this->assertSame(2, ListingPhoto::where('listing_id', $listing->id)->count());
        $this->assertNotNull(ListingPhoto::first()->image_path, 'Processed (the test queue runs at once).');

        $listing->forceFill(['plan_code' => 'standard'])->save();
        foreach (range(1, 9) as $i) {
            $add()->assertSessionHasNoErrors();
        }
        $add()->assertSessionHasErrors('extra_photo');   // 12 in all
        $this->assertSame(11, ListingPhoto::where('listing_id', $listing->id)->count());

        $this->actingAs($owner)->get("/account/listings/{$listing->id}/edit/photo")->assertOk()
            ->assertSee('Your plan allows 12 photos in all')->assertSee('have used them all');
    }

    public function test_the_property_page_shows_photos_within_the_plan_limit(): void
    {
        $listing = Listing::factory()->live()->titled('Gallery home')->create(['plan_code' => 'standard', 'image_path' => 'listings/1/main-1600.webp'])->load(['island', 'area']);
        foreach (range(1, 5) as $i) {
            $p = new ListingPhoto(['alt' => "Room {$i}", 'sort_order' => $i]);
            $p->listing_id = $listing->id;
            $p->forceFill(['image_path' => "listings/{$listing->id}/00000000-0000-0000-0000-00000000000{$i}-1600.webp"])->save();
        }

        $html = $this->get($listing->publicUrl('en'))->assertOk()->getContent();
        $this->assertSame(5, substr_count($html, 'data-gallery-photo'));
        $this->assertStringContainsString('alt="Room 1 (opens the large photo)"', $html);

        // On the Free plan only 3 in all show (main + 2); the rest are kept, not deleted.
        $listing->forceFill(['plan_code' => 'free'])->save();
        $this->assertSame(2, substr_count($this->get($listing->publicUrl('en'))->getContent(), 'data-gallery-photo'));
        $this->assertSame(5, ListingPhoto::where('listing_id', $listing->id)->count());
    }

    public function test_owners_remove_photos_and_affiliates_cannot_add_them(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = User::factory()->owner()->create();
        $listing = $this->completeListing($owner, null, ['plan_code' => 'standard']);
        $this->actingAs($owner)->post(route('account.listings.photos.store', $listing), ['extra_photo' => $this->png(), 'extra_alt' => 'Pool', 'extra_permission' => 1]);
        $photo = ListingPhoto::sole();

        $this->actingAs($owner)->delete(route('account.listings.photos.destroy', [$listing, $photo->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, ListingPhoto::count());
        Storage::disk('public')->assertMissing($photo->image_path);

        $affiliate = User::factory()->affiliate()->create();
        $theirs = $this->completeListing($affiliate, null, ['deeplink_url' => 'https://b.example/1']);
        $this->actingAs($affiliate)->post(route('account.listings.photos.store', $theirs), ['extra_photo' => $this->png(), 'extra_alt' => 'X', 'extra_permission' => 1])->assertNotFound();
    }

    // --- Billing ---------------------------------------------------------------------------

    public function test_billing_offers_yearly_only_and_featured_periods(): void
    {
        $html = $this->actingAs(User::factory()->owner()->create())->get('/account/billing')->assertOk()->getContent();

        $this->assertStringContainsString('€120 for 1 year', $html);
        $this->assertStringNotContainsString('a month', $html);
        $this->assertStringContainsString('3 months: €30', $html);
        $this->assertStringContainsString('6 months: €55', $html);
        $this->assertStringContainsString('One payment, starting today. It does not renew.', $html);
    }

    public function test_featured_property_is_a_one_off_payment_for_the_chosen_period(): void
    {
        Http::fake([
            self::API.'/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::API.'/v2/checkout/orders' => Http::response(['id' => 'ORDER-1', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1']]], 201),
            self::API.'/v2/checkout/orders/ORDER-1/capture' => Http::response(['id' => 'ORDER-1', 'status' => 'COMPLETED'], 201),
        ]);
        $owner = User::factory()->owner()->create();
        $listing = $this->setStatus($this->completeListing($owner), \App\Enums\ListingStatus::Live);

        $this->actingAs($owner)->post(route('account.billing.featured'), ['months' => 9])->assertSessionHasErrors('months');   // no 9-month price
        $this->actingAs($owner)->post(route('account.billing.featured'), ['months' => 6])
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

        $purchase = FeaturedPurchase::sole();
        $this->assertSame(5500, $purchase->amount_cents);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v2/checkout/orders') && $r['purchase_units'][0]['amount']['value'] === '55.00');

        $this->actingAs($owner)->get(route('account.billing.featured.return', ['token' => 'ORDER-1']))
            ->assertRedirect(route('account.billing'))->assertSessionHas('status', fn ($s) => str_contains($s, 'Featured Plan is active until'));

        $subscription = Subscription::sole();
        $this->assertSame('one_off', $subscription->billing_interval);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->ends_at->isSameDay(now()->addMonthsNoOverflow(6)));
        $this->assertSame('completed', $purchase->fresh()->status);
        $this->assertSame('featured', app(AccountPlan::class)->planFor($owner)->code);
        $this->assertNotNull($listing->fresh()->featured_until, 'Listings are featured straight away.');

        // Coming back again does not make a second period.
        $this->actingAs($owner)->get(route('account.billing.featured.return', ['token' => 'ORDER-1']))->assertRedirect(route('account.billing'));
        $this->assertSame(1, Subscription::count());

        // It ends on its end date and does not renew.
        $this->travel(7)->months();
        $this->assertSame('free', app(AccountPlan::class)->planFor($owner)->code);
    }

    public function test_someone_elses_payment_is_refused(): void
    {
        $owner = User::factory()->owner()->create();
        FeaturedPurchase::create(['user_id' => $owner->id, 'plan_id' => Plan::where('code', 'featured')->value('id'), 'months' => 3, 'amount_cents' => 3000, 'currency' => 'EUR', 'paypal_order_id' => 'ORDER-X']);

        $this->actingAs(User::factory()->owner()->create())->get(route('account.billing.featured.return', ['token' => 'ORDER-X']))
            ->assertSessionHasErrors('months');
        $this->assertSame(0, Subscription::count());
    }
}
