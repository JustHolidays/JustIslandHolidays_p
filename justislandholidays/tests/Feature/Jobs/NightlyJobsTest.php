<?php

namespace Tests\Feature\Jobs;

use App\Enums\ListingStatus as S;
use App\Models\AuditLog;
use App\Models\DeeplinkClick;
use App\Models\DeeplinkClickDaily;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\ListingPrivate;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Fetch\SafeFetcher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

class NightlyJobsTest extends TestCase
{
    use BuildsListings, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        config(['site.deeplink_check.pause_ms' => 0]);
    }

    private function click(Listing $listing, $at, bool $bot = false, bool $unique = true): void
    {
        DeeplinkClick::create([
            'click_id' => (string) Str::ulid(), 'listing_id' => $listing->id, 'affiliate_id' => $listing->owner_id,
            'island_id' => $listing->island_id, 'clicked_at' => $at, 'lang' => 'en', 'source_page' => 'listing',
            'ip_hash' => str_repeat('a', 64), 'is_bot' => $bot, 'is_unique' => $unique, 'target_url' => 'https://x.example',
        ]);
    }

    public function test_all_jobs_are_scheduled_nightly_in_uk_time(): void
    {
        $events = collect(app(Schedule::class)->events())->mapWithKeys(fn ($e) => [trim(Str::after($e->command, 'artisan')," '\"") => $e]);

        foreach (['listings:reconcile', 'clicks:rollup', 'deeplinks:check', 'data:retention'] as $command) {
            $event = $events->first(fn ($e, $k) => str_starts_with($k, $command));
            $this->assertNotNull($event, "{$command} is not scheduled");
            $this->assertSame('Europe/London', $event->timezone);
        }
    }

    public function test_rollup_counts_humans_uniques_and_bots_per_listing_and_day(): void
    {
        $listing = Listing::factory()->live()->create();
        $yesterday = now()->subDay()->setTime(10, 0);
        $this->click($listing, $yesterday);
        $this->click($listing, $yesterday, unique: false);
        $this->click($listing, $yesterday, bot: true, unique: false);
        $this->click($listing, now());

        $this->artisan('clicks:rollup')->assertSuccessful();

        $row = DeeplinkClickDaily::where('listing_id', $listing->id)->where('day', $yesterday->toDateString())->sole();
        $this->assertSame([2, 1, 1], [$row->clicks, $row->unique_clicks, $row->bot_clicks]);
        $this->assertSame(2, DeeplinkClickDaily::count());

        $this->artisan('clicks:rollup')->assertSuccessful();
        $this->assertSame(2, DeeplinkClickDaily::count(), 'Running twice does not double-count.');
    }

    public function test_link_checker_checks_live_links_and_leaves_switched_off_ones_alone(): void
    {
        $this->app->instance(SafeFetcher::class, new SafeFetcher(fn () => ['93.184.216.34']));
        Http::fake(['https://good.example/*' => Http::response('ok'), 'https://gone.example/*' => Http::response('', 404), '*' => Http::response('ok')]);
        $partner = User::factory()->affiliate()->create();   // only affiliate links are checked

        $good = Listing::factory()->live()->withDeeplink('https://good.example/1', status: 'unchecked')->create(['owner_id' => $partner->id]);
        $gone = Listing::factory()->live()->withDeeplink('https://gone.example/1', status: 'ok')->create(['owner_id' => $partner->id]);
        $off = Listing::factory()->live()->withDeeplink('https://good.example/2', status: 'broken')->create(['owner_id' => $partner->id]);
        $draft = Listing::factory()->withDeeplink('https://good.example/3', status: 'unchecked')->create(['owner_id' => $partner->id]);
        // Owners and agents look after their own links: never checked, even when live.
        $owners = Listing::factory()->live()->withDeeplink('https://gone.example/owner', status: 'unchecked')->create(['owner_id' => User::factory()->owner(), 'plan_code' => 'standard']);
        $agents = Listing::factory()->live()->withDeeplink('https://gone.example/agent', status: 'unchecked')->create(['owner_id' => User::factory()->agent(), 'plan_code' => 'standard']);

        $this->artisan('deeplinks:check')->assertSuccessful();

        $this->assertSame('ok', $good->fresh()->deeplink_status);
        $this->assertSame('broken', $gone->fresh()->deeplink_status);
        $this->assertSame('broken', $off->fresh()->deeplink_status, 'Switched off by an admin: not turned back on.');
        $this->assertSame('unchecked', $draft->fresh()->deeplink_status, 'Only live listings.');
        $this->assertFalse($gone->fresh()->showsCheckAvailability());
        $this->assertSame(2, AuditLog::where('action', 'job.deeplinks_check')->sole()->detail['checked']);
        foreach ([$owners, $agents] as $lister) {
            $this->assertSame('unchecked', $lister->fresh()->deeplink_status);
            $this->assertNull($lister->fresh()->deeplink_checked_at);
            $this->assertTrue($lister->fresh()->showsCheckAvailability());
        }
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/owner') || str_contains($request->url(), '/agent'));
    }

    public function test_link_checker_takes_the_longest_unchecked_first_and_respects_the_limit(): void
    {
        $this->app->instance(SafeFetcher::class, new SafeFetcher(fn () => ['93.184.216.34']));
        Http::fake(['*' => Http::response('ok')]);
        $partner = User::factory()->affiliate()->create();

        $recent = Listing::factory()->live()->withDeeplink('https://a.example/1')->create(['owner_id' => $partner->id]);
        $recent->forceFill(['deeplink_checked_at' => now()->subHour()])->save();
        $never = Listing::factory()->live()->withDeeplink('https://a.example/2', status: 'unchecked')->create(['owner_id' => $partner->id]);

        $this->artisan('deeplinks:check --limit=1')->assertSuccessful();

        $this->assertNotNull($never->fresh()->deeplink_checked_at);
        $this->assertTrue($recent->fresh()->deeplink_checked_at->lt(now()->subMinutes(30)));
    }

    public function test_retention_deletes_old_enquiries_only(): void
    {
        $old = Enquiry::factory()->create();
        $old->forceFill(['created_at' => now()->subMonths(25)])->save();
        $recent = Enquiry::factory()->create();
        $recent->forceFill(['created_at' => now()->subMonths(23)])->save();

        $this->artisan('data:retention --dry-run')->expectsOutputToContain('Would remove: 1 enquiries')->assertSuccessful();
        $this->assertSame(2, Enquiry::count());

        $this->artisan('data:retention')->assertSuccessful();
        $this->assertNull(Enquiry::find($old->id));
        $this->assertNotNull(Enquiry::find($recent->id));
    }

    public function test_retention_keeps_daily_totals_when_old_clicks_are_deleted(): void
    {
        $listing = Listing::factory()->live()->create();
        $oldDay = now()->subMonths(14)->setTime(12, 0);
        $this->click($listing, $oldDay);
        $this->click($listing, $oldDay, unique: false);
        $this->click($listing, now()->subMonths(2));

        $this->artisan('data:retention')->assertSuccessful();

        $this->assertSame(1, DeeplinkClick::count(), 'Only the recent raw click is left.');
        $row = DeeplinkClickDaily::where('day', $oldDay->toDateString())->sole();
        $this->assertSame([2, 1], [$row->clicks, $row->unique_clicks]);

        $this->artisan('clicks:rollup --days=500')->assertSuccessful();
        $this->assertSame(2, DeeplinkClickDaily::where('day', $oldDay->toDateString())->sole()->clicks, 'A later rebuild never wipes kept totals.');
    }

    public function test_deleted_accounts_are_anonymised_after_30_days(): void
    {
        $owner = User::factory()->owner()->create(['email' => 'gone@example.com', 'phone' => '+44 7700 900000']);
        $listing = $this->setStatus($this->completeListing($owner), S::Live);
        $owner->delete();
        $owner->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

        $recent = User::factory()->owner()->create(['email' => 'recent@example.com']);
        $recent->delete();

        $this->artisan('data:retention')->assertSuccessful();

        $purged = User::withTrashed()->find($owner->id);
        $this->assertSame('Deleted account', $purged->name);
        $this->assertSame("deleted-{$owner->id}@deleted.invalid", $purged->email);
        $this->assertNull($purged->phone);
        $this->assertNull(ListingPrivate::find($listing->id));
        $this->assertTrue(Listing::withTrashed()->find($listing->id)->trashed(), 'Their listings are removed from the site.');
        $this->assertSame('recent@example.com', User::withTrashed()->find($recent->id)->email, 'Not yet 30 days.');

        $this->artisan('data:retention')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'user.purge')->count(), 'Purged once only.');
    }

    public function test_reconcile_expires_ended_plans_and_drops_listings_to_free(): void
    {
        $owner = User::factory()->owner()->create();
        $listing = $this->setStatus($this->completeListing($owner), S::Live);
        $sub = new Subscription();
        $sub->forceFill(['user_id' => $owner->id, 'plan_id' => Plan::where('code', 'featured')->value('id'), 'billing_interval' => 'year',
            'status' => 'active', 'ends_at' => now()->addDays(2)])->save();
        app(\App\Billing\AccountPlan::class)->sync($owner);
        $this->assertSame('featured', $listing->fresh()->plan_code);

        $this->travel(3)->days();
        $this->artisan('listings:reconcile')->assertSuccessful();

        $this->assertSame('expired', $sub->fresh()->status);
        $this->assertSame('free', $listing->fresh()->plan_code);
    }

    public function test_reconcile_refreshes_rank_scores(): void
    {
        $listing = $this->setStatus($this->completeListing(), S::Live);
        $listing->forceFill(['rank_score' => 999])->saveQuietly();

        $this->artisan('listings:reconcile')->assertSuccessful();

        $this->assertNotSame(999, $listing->fresh()->rank_score);
    }

    public function test_queue_runs_from_cron_only_when_switched_on(): void
    {
        $queued = fn () => collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'queue:work'));

        $this->assertFalse($queued());
    }
}
