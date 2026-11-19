<?php

namespace Tests\Feature\Admin;

use App\Models\DeeplinkClick;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The affiliate page's listing clicks, for 30, 60 or 365 days or all time. */
class AffiliateClicksPeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(12, 0));
        $this->partner = User::factory()->affiliate()->create(['name' => 'Sunny Stays']);
        $this->listing = Listing::factory()->live()->titled('Villa Sol')->create(['owner_id' => $this->partner->id]);

        // Older days live in the daily totals (raw events are deleted after 13 months).
        $daily = fn (int $daysAgo, int $clicks) => DB::table('deeplink_clicks_daily')->insert([
            'listing_id' => $this->listing->id, 'day' => now()->subDays($daysAgo)->toDateString(), 'clicks' => $clicks, 'unique_clicks' => $clicks, 'bot_clicks' => 0,
        ]);
        $daily(10, 1);     // in 30, 60, 365, all
        $daily(45, 2);     // in 60, 365, all
        $daily(200, 4);    // in 365, all
        $daily(500, 8);    // all time only

        // Today, before the nightly rollup has run: raw events (one is a robot).
        $this->click(false);
        $this->click(false);
        $this->click(true);
    }

    private function click(bool $bot): void
    {
        DeeplinkClick::create([
            'click_id' => (string) Str::ulid(), 'listing_id' => $this->listing->id, 'affiliate_id' => $this->partner->id, 'island_id' => $this->listing->island_id,
            'clicked_at' => now()->subHour(), 'lang' => 'en', 'source_page' => 'listing', 'ip_hash' => str_repeat('a', 64),
            'is_bot' => $bot, 'is_unique' => true, 'target_url' => 'https://x.example',
        ]);
    }

    private function clicksShown(?string $period): int
    {
        $html = $this->actingAs($this->superAdmin())
            ->get(route('admin.affiliates.show', array_filter(['affiliate' => $this->partner->id, 'clicks' => $period])))
            ->assertOk()->getContent();
        preg_match('#Villa Sol</a></td><td class="p-2">[^<]*</td><td class="p-2 text-right">(\d+)</td>#', $html, $m);

        return (int) ($m[1] ?? -1);
    }

    public function test_each_period_counts_the_right_clicks(): void
    {
        $this->assertSame(1 + 2, $this->clicksShown('30'));
        $this->assertSame(1 + 2 + 2, $this->clicksShown('60'));
        $this->assertSame(1 + 2 + 4 + 2, $this->clicksShown('365'));
        $this->assertSame(1 + 2 + 4 + 8 + 2, $this->clicksShown('all'), 'All time includes days whose raw clicks were deleted.');
    }

    public function test_30_days_is_the_default_and_bad_values_fall_back_to_it(): void
    {
        $this->assertSame(3, $this->clicksShown(null));
        $this->assertSame(3, $this->clicksShown('9999'));
    }

    public function test_the_choice_is_labelled_and_kept(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.affiliates.show', ['affiliate' => $this->partner->id, 'clicks' => 'all']))->assertOk()
            ->assertSee('<label for="clicks-period"', false)
            ->assertSee('<option value="all" selected>All time</option>', false)
            ->assertSee('<option value="365" >Last 365 days</option>', false)
            ->assertSee('Clicks, all time')
            ->assertSee('17 clicks in total, all time.');
    }
}
