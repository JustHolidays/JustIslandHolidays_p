<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Property page views, counted per day with nothing about the visitor (20 Nov 2026). */
class PageViewsTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-03-10 12:00'));
        $this->seedPlans();
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner), ListingStatus::Live)->load(['island', 'area']);
    }

    private function visit(string $ip = '203.0.113.5', string $agent = 'Mozilla/5.0 (Windows NT 10.0) Firefox/130.0', ?User $as = null): void
    {
        $request = $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $agent);
        ($as ? $request->actingAs($as) : $request)->get($this->listing->publicUrl('en'))->assertOk();
        if ($as) {
            auth()->logout();
        }
    }

    private function views(): int
    {
        return (int) DB::table('listing_views_daily')->where('listing_id', $this->listing->id)->sum('views');
    }

    public function test_a_visit_is_counted_once_in_30_minutes(): void
    {
        $this->visit();
        $this->visit();
        $this->assertSame(1, $this->views());

        $this->travel(31)->minutes();
        $this->visit();
        $this->assertSame(2, $this->views());

        $this->visit('198.51.100.7');   // another visitor
        $this->assertSame(3, $this->views());
    }

    public function test_nothing_about_the_visitor_is_stored(): void
    {
        $this->visit();

        $this->assertSame(['day', 'listing_id', 'views'], collect(\Illuminate\Support\Facades\Schema::getColumnListing('listing_views_daily'))->sort()->values()->all());
        $this->assertSame('2027-03-10', substr((string) DB::table('listing_views_daily')->value('day'), 0, 10));
    }

    public function test_bots_the_lister_and_admins_are_not_counted(): void
    {
        $this->visit(agent: 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->visit('198.51.100.8', as: $this->owner);
        $this->visit('198.51.100.9', as: $this->superAdmin());

        $this->assertSame(0, $this->views());
    }

    public function test_owner_sees_views_and_clicks_for_each_period(): void
    {
        DB::table('listing_views_daily')->insert([
            ['listing_id' => $this->listing->id, 'day' => '2027-03-09', 'views' => 5],
            ['listing_id' => $this->listing->id, 'day' => '2027-01-20', 'views' => 7],   // 49 days ago
            ['listing_id' => $this->listing->id, 'day' => '2025-12-01', 'views' => 11],  // over a year ago
        ]);

        $this->actingAs($this->owner)->get(route('account.home'))->assertSee('Views and clicks');
        $this->actingAs($this->owner)->get(route('account.clicks'))->assertOk()
            ->assertSee('Page views')->assertSeeInOrder(['data-views>', '5'], false);
        $this->actingAs($this->owner)->get(route('account.clicks', ['period' => '60']))->assertSee('data-views>12<', false);
        $this->actingAs($this->owner)->get(route('account.clicks', ['period' => 'all']))->assertSee('data-views>23<', false);

        // Another owner sees none of it.
        $this->actingAs(User::factory()->owner()->create())->get(route('account.clicks'))->assertDontSee('data-listing="'.$this->listing->id.'"', false);
    }

    public function test_admin_sees_views_on_the_property_page(): void
    {
        DB::table('listing_views_daily')->insert([
            ['listing_id' => $this->listing->id, 'day' => '2027-03-09', 'views' => 5],
            ['listing_id' => $this->listing->id, 'day' => '2026-06-01', 'views' => 7],
            ['listing_id' => $this->listing->id, 'day' => '2025-12-01', 'views' => 11],
        ]);

        $this->actingAs($this->superAdmin())->get(route('admin.listings.show', $this->listing))->assertOk()
            ->assertSee('data-page-views', false)
            ->assertSeeInOrder(['Last 30 days', '5', 'Last 365 days', '12', 'All time', '23']);
    }
}
