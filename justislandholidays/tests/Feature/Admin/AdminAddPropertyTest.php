<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Admins add a property for an owner or agent; it goes live when they confirm the licence check. */
class AdminAddPropertyTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private Island $menorca;

    private Area $resort;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->menorca = Island::factory()->create(['slug' => 'menorca', 'is_active' => true]);
        $this->resort = Area::factory()->create(['island_id' => $this->menorca->id]);
        $this->owner = User::factory()->owner()->create(['email' => 'olive@example.com', 'name' => 'Olive Owner']);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('villa.jpg', 1200, 800);
    }

    private function form(array $o = []): array
    {
        return $o + [
            'lister_email' => 'olive@example.com',
            'area_id' => $this->resort->id,
            'title' => 'Villa Blanca',
            'summary' => 'A white villa with a pool, ten minutes from the beach.',
            'property_type' => 'villa',
            'bedrooms' => 3, 'bathrooms' => 2, 'sleeps' => 6,
            'pets' => 'no', 'pool' => 'private',
            'price' => 1500, 'currency' => 'EUR',
            'coordinates' => '39.9112, 4.2650',
            'photo' => $this->photo(),
            'image_alt' => 'White villa with a pool',
            'photo_permission' => 1,
            'registration_number' => 'ETV/1234',
        ];
    }

    public function test_with_the_licence_checked_it_goes_live_for_the_owner(): void
    {
        $admin = $this->islandAdmin($this->menorca);
        $this->actingAs($admin)->get(route('admin.listings.create'))->assertOk()->assertSee('Add a property');

        $this->actingAs($admin)->post(route('admin.listings.store'), $this->form(['licence_checked' => 1]))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'live now'));

        $listing = Listing::with(['licence', 'private', 'translations'])->sole();
        $this->assertSame($this->owner->id, $listing->owner_id);
        $this->assertSame(ListingStatus::Live, $listing->status);
        $this->assertSame('verified', $listing->licence->status);
        $this->assertSame($admin->id, $listing->licence->verified_by);
        $this->assertNotNull($listing->image_path, 'The photo is processed straight away.');
        $this->assertSame('Villa Blanca', $listing->title('en'));
        $this->assertEqualsWithDelta(39.9112, (float) $listing->private->lat, 0.0001);
        $this->assertSame('olive@example.com', $listing->private->contact_email, 'Enquiries go to the owner by default.');
        $this->assertNotEquals((float) $listing->private->lat, (float) $listing->display_lat, 'The public point is moved.');
        $this->assertSame(150000, $listing->min_price_week_cents);
        $this->assertDatabaseHas('audit_log', ['action' => 'listing.admin_create']);
        $this->assertDatabaseHas('audit_log', ['action' => 'licence.verify']);

        Notification::assertSentTo($this->owner, ListingStatusChanged::class);
        $this->get($listing->fresh(['island', 'area'])->publicUrl('en'))->assertOk()->assertSee('Villa Blanca');

        // The owner can edit it in their account.
        $this->actingAs($this->owner)->get("/account/listings/{$listing->id}/edit/basics")->assertOk();
    }

    public function test_without_the_licence_checked_it_waits_for_review(): void
    {
        $this->actingAs($this->superAdmin())->post(route('admin.listings.store'), $this->form())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'waiting for review'));

        $listing = Listing::with('licence')->sole();
        $this->assertSame(ListingStatus::PendingReview, $listing->status);
        $this->assertSame('pending', $listing->licence->status);
        Notification::assertNotSentTo($this->owner, ListingStatusChanged::class);
    }

    public function test_pool_is_private_shared_or_none(): void
    {
        $shared = \App\Models\Amenity::create(['code' => 'shared_pool', 'category' => 'outdoor', 'name' => ['en' => 'Shared pool'], 'is_filterable' => true, 'sort_order' => 1]);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.listings.create'))->assertOk()
            ->assertSee('<option value="private" >Private pool</option>', false)
            ->assertSee('<option value="shared" >Shared pool</option>', false)
            ->assertSee('<option value="none" selected>No pool</option>', false);

        foreach (['private' => [true, false], 'shared' => [false, true], 'none' => [false, false]] as $pool => [$private, $hasShared]) {
            $this->actingAs($admin)->post(route('admin.listings.store'), $this->form(['pool' => $pool, 'title' => "Pool {$pool}", 'photo' => $this->photo()]))->assertSessionHasNoErrors();
            $listing = Listing::whereHas('translations', fn ($q) => $q->where('title', "Pool {$pool}"))->sole();

            $this->assertSame($private, $listing->has_private_pool, "{$pool}: private pool");
            $this->assertSame($hasShared, $listing->amenities()->whereKey($shared->id)->exists(), "{$pool}: shared pool facility");
        }

        $this->actingAs($admin)->post(route('admin.listings.store'), $this->form(['pool' => 'lake']))->assertSessionHasErrors('pool');
    }

    public function test_it_can_be_added_for_an_agent_and_their_agency(): void
    {
        $agency = Agency::factory()->create();
        $agent = User::factory()->agent()->create(['email' => 'agent@example.com', 'agency_id' => $agency->id]);

        $this->actingAs($this->superAdmin())->post(route('admin.listings.store'), $this->form(['lister_email' => 'AGENT@example.com', 'licence_checked' => 1]))
            ->assertSessionHasNoErrors();

        $listing = Listing::sole();
        $this->assertSame($agent->id, $listing->owner_id);
        $this->assertSame($agency->id, $listing->agency_id);
    }

    public function test_the_lister_must_be_an_existing_active_owner_or_agent(): void
    {
        $admin = $this->superAdmin();
        User::factory()->affiliate()->create(['email' => 'partner@example.com']);
        User::factory()->owner()->create(['email' => 'gone@example.com', 'deactivated_at' => now()]);

        foreach (['nobody@example.com', 'partner@example.com', 'gone@example.com'] as $email) {
            $this->actingAs($admin)->post(route('admin.listings.store'), $this->form(['lister_email' => $email]))->assertSessionHasErrors('lister_email');
        }
        $this->assertSame(0, Listing::count());
    }

    public function test_required_details_are_checked(): void
    {
        $admin = $this->superAdmin();
        $post = fn (array $o) => $this->actingAs($admin)->post(route('admin.listings.store'), $this->form($o));

        $post(['registration_number' => '', 'national_registry_no' => ''])->assertSessionHasErrors('registration_number');
        $post(['coordinates' => '51.5, -0.1'])->assertSessionHasErrors('coordinates');
        $post(['photo_permission' => null])->assertSessionHasErrors('photo_permission');
        $post(['photo' => UploadedFile::fake()->image('small.jpg', 400, 300)])->assertSessionHasErrors('photo');
        $post(['image_alt' => ''])->assertSessionHasErrors('image_alt');
        $post(['deeplink_url' => 'http://insecure.example'])->assertSessionHasErrors('deeplink_url');

        $this->assertSame(0, Listing::count());
    }

    public function test_island_admins_add_properties_on_their_own_islands_only(): void
    {
        $mallorca = Island::factory()->create(['is_active' => true]);
        $otherResort = Area::factory()->create(['island_id' => $mallorca->id]);
        $admin = $this->islandAdmin($this->menorca);

        $this->actingAs($admin)->get(route('admin.listings.create'))->assertOk()
            ->assertSee($this->resort->translate('name', 'en'))
            ->assertDontSee($otherResort->translate('name', 'en'));

        $this->actingAs($admin)->post(route('admin.listings.store'), $this->form(['area_id' => $otherResort->id]))->assertForbidden();
        $this->actingAs($this->owner)->get(route('admin.listings.create'))->assertForbidden();
        $this->assertSame(0, Listing::count());
    }

    public function test_links_lead_to_the_page_with_the_lister_filled_in(): void
    {
        $admin = $this->superAdmin();
        $agent = User::factory()->agent()->create(['email' => 'arthur@example.com']);

        $this->actingAs($admin)->get(route('admin.listings.index'))->assertSee(route('admin.listings.create'), false);
        $this->actingAs($admin)->get(route('admin.users.show', $this->owner))->assertSee(route('admin.listings.create', ['lister' => 'olive@example.com']), false);
        $this->actingAs($admin)->get(route('admin.agents.show', $agent))->assertSee(route('admin.listings.create', ['lister' => 'arthur@example.com']), false);
        $this->actingAs($admin)->get(route('admin.listings.create', ['lister' => 'olive@example.com']))->assertSee('value="olive@example.com"', false);
    }
}
