<?php

namespace Tests\Feature\Listings;

use App\Calendar\Availability;
use App\Calendar\IcalParser;
use App\Enums\ListingStatus;
use App\Models\CalendarBooking;
use App\Models\CalendarFeed;
use App\Models\Listing;
use App\Models\User;
use App\Support\Fetch\SafeFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** Booking calendar for paid plans: iCal import, owner's own dates, half days on changeover. */
class BookingCalendarTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-06-01 10:00'));
        $this->seed(\Database\Seeders\PlanSeeder::class);
        \App\Models\Plan::whereIn('code', ['standard', 'featured', 'agent_plus'])->update(['has_booking_calendar' => true]);
        $this->app->instance(SafeFetcher::class, new SafeFetcher(fn () => ['93.184.216.34']));
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner, null, ['plan_code' => 'standard']), ListingStatus::Live)->load(['island', 'area']);
    }

    private function stay(string $in, string $out, ?CalendarFeed $feed = null): void
    {
        $b = new CalendarBooking(['check_in' => $in, 'check_out' => $out]);
        $b->listing_id = $this->listing->id;
        $b->feed_id = $feed?->id;
        $b->save();
    }

    /** @return array<string, array{am: bool, pm: bool}> */
    private function days(): array
    {
        $out = [];
        foreach (Availability::months($this->listing, CarbonImmutable::parse('2027-06-01'), 1)[0]['weeks'] as $week) {
            foreach ($week as $d) {
                if ($d['inMonth']) {
                    $out[$d['date']->toDateString()] = ['am' => $d['am'], 'pm' => $d['pm']];
                }
            }
        }

        return $out;
    }

    // --- Half days ------------------------------------------------------------------------

    public function test_check_in_and_check_out_days_are_half_days(): void
    {
        $this->stay('2027-06-10', '2027-06-13');   // nights of the 10th, 11th and 12th

        $d = $this->days();
        $this->assertSame(['am' => false, 'pm' => false], $d['2027-06-09']);
        $this->assertSame(['am' => false, 'pm' => true], $d['2027-06-10'], 'Arrival: booked from the afternoon.');
        $this->assertSame(['am' => true, 'pm' => true], $d['2027-06-11']);
        $this->assertSame(['am' => true, 'pm' => false], $d['2027-06-13'], 'Departure: free from the afternoon.');
        $this->assertSame(['am' => false, 'pm' => false], $d['2027-06-14']);
    }

    public function test_back_to_back_stays_fill_the_changeover_day(): void
    {
        $this->stay('2027-06-10', '2027-06-13');
        $this->stay('2027-06-13', '2027-06-15');

        $this->assertSame(['am' => true, 'pm' => true], $this->days()['2027-06-13']);
    }

    // --- iCal -----------------------------------------------------------------------------

    public function test_ical_reader_takes_only_the_dates(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a1\r\nSUMMARY:Reserved - Jane Smith\r\nDTSTART;VALUE=DATE:20270610\r\nDTEND;VALUE=DATE:20270613\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Madrid:20270620T150000\r\nDTEND;TZID=Europe/Madrid:20270622T100000\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nDTSTART;VALUE=DATE:20270625\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nSTATUS:CANCELLED\r\nDTSTART;VALUE=DATE:20270701\r\nDTEND;VALUE=DATE:20270705\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nDTSTART;VALUE=DATE:20260101\r\nDTEND;VALUE=DATE:20260105\r\nEND:VEVENT\r\n"
            ."BEGIN:VEVENT\r\nDTSTART;VALUE=DA\r\n TE:20270801\r\nDTEND;VALUE=DATE:20270803\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        $this->assertSame([
            ['check_in' => '2027-06-10', 'check_out' => '2027-06-13'],
            ['check_in' => '2027-06-20', 'check_out' => '2027-06-22'],
            ['check_in' => '2027-06-25', 'check_out' => '2027-06-26'],   // no end: one night
            ['check_in' => '2027-08-01', 'check_out' => '2027-08-03'],   // folded line read correctly
        ], IcalParser::stays($ics));   // cancelled and long-past stays are left out
    }

    public function test_owner_adds_a_feed_and_it_imports_dates_only(): void
    {
        Http::fake(['https://www.airbnb.example/*' => Http::response("BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:Guest Name\nDTSTART;VALUE=DATE:20270610\nDTEND;VALUE=DATE:20270613\nEND:VEVENT\nEND:VCALENDAR\n")]);

        $this->actingAs($this->owner)->post(route('account.listings.calendar.feeds.store', $this->listing), [
            'feed_name' => 'Airbnb', 'feed_url' => 'webcal://www.airbnb.example/calendar/ical/123.ics',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, '1 booked stay'));

        $feed = CalendarFeed::sole();
        $this->assertSame('https://www.airbnb.example/calendar/ical/123.ics', $feed->url, 'webcal:// is read over https.');
        $this->assertSame('ok', $feed->last_status);
        $this->assertSame(1, CalendarBooking::where('feed_id', $feed->id)->count());
        $this->assertSame(['check_in', 'check_out', 'created_at', 'feed_id', 'id', 'listing_id'], collect(\Illuminate\Support\Facades\Schema::getColumnListing('calendar_bookings'))->sort()->values()->all(), 'No guest details are stored.');
    }

    public function test_a_failed_sync_keeps_the_last_good_dates(): void
    {
        $feed = new CalendarFeed(['name' => 'Booking.com', 'url' => 'https://admin.booking.example/ical/1.ics']);
        $feed->listing_id = $this->listing->id;
        $feed->save();
        $this->stay('2027-06-10', '2027-06-12', $feed);
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('calendars:sync')->assertSuccessful();

        $this->assertSame('failed', $feed->fresh()->last_status);
        $this->assertSame(1, CalendarBooking::where('feed_id', $feed->id)->count());
        $this->assertDatabaseHas('audit_log', ['action' => 'job.calendars_sync']);
    }

    public function test_the_nightly_sync_replaces_a_feeds_dates(): void
    {
        $feed = new CalendarFeed(['name' => 'Vrbo', 'url' => 'https://www.vrbo.example/ical/9.ics']);
        $feed->listing_id = $this->listing->id;
        $feed->save();
        $this->stay('2027-06-01', '2027-06-03', $feed);
        Http::fake(['*' => Http::response("BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART;VALUE=DATE:20270720\nDTEND;VALUE=DATE:20270727\nEND:VEVENT\nEND:VCALENDAR\n")]);

        $this->artisan('calendars:sync')->assertSuccessful();

        $this->assertSame([['2027-07-20', '2027-07-27']], CalendarBooking::where('feed_id', $feed->id)->get()->map(fn ($b) => [$b->check_in->toDateString(), $b->check_out->toDateString()])->all());
    }

    // --- Owner's own dates ------------------------------------------------------------------

    public function test_pressing_a_day_books_or_frees_that_night(): void
    {
        $toggle = fn (string $date) => $this->actingAs($this->owner)->post(route('account.listings.calendar.toggle', $this->listing), ['date' => $date]);

        $toggle('2027-06-15')->assertSessionHas('status', fn ($s) => str_contains($s, 'marked as booked'));
        $this->assertSame(['am' => false, 'pm' => true], $this->days()['2027-06-15']);
        $this->assertSame(['am' => true, 'pm' => false], $this->days()['2027-06-16']);

        $toggle('2027-06-15')->assertSessionHas('status', fn ($s) => str_contains($s, 'free again'));
        $this->assertSame(0, CalendarBooking::count());

        $toggle('2027-05-01')->assertSessionHasErrors('date');   // in the past
    }

    public function test_freeing_a_night_in_a_longer_stay_splits_it(): void
    {
        $this->actingAs($this->owner)->post(route('account.listings.calendar.bookings.store', $this->listing), ['check_in' => '2027-06-10', 'check_out' => '2027-06-15'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('account.listings.calendar.toggle', $this->listing), ['date' => '2027-06-12']);

        $this->assertSame([['2027-06-10', '2027-06-12'], ['2027-06-13', '2027-06-15']],
            CalendarBooking::orderBy('check_in')->get()->map(fn ($b) => [$b->check_in->toDateString(), $b->check_out->toDateString()])->all());
    }

    public function test_imported_nights_cannot_be_freed_by_hand(): void
    {
        $feed = new CalendarFeed(['name' => 'Airbnb', 'url' => 'https://a.example/x.ics']);
        $feed->listing_id = $this->listing->id;
        $feed->save();
        $this->stay('2027-06-10', '2027-06-12', $feed);

        $this->actingAs($this->owner)->post(route('account.listings.calendar.toggle', $this->listing), ['date' => '2027-06-10'])->assertSessionHasErrors('date');
        $this->assertSame(1, CalendarBooking::count());
    }

    public function test_date_ranges_are_checked(): void
    {
        $post = fn (array $d) => $this->actingAs($this->owner)->post(route('account.listings.calendar.bookings.store', $this->listing), $d);

        $post(['check_in' => '2027-06-10', 'check_out' => '2027-06-10'])->assertSessionHasErrors('check_out');
        $post(['check_in' => '2027-05-10', 'check_out' => '2027-05-12'])->assertSessionHasErrors('check_in');
        $this->assertSame(0, CalendarBooking::count());
    }

    // --- Who gets it, and the property page --------------------------------------------------

    public function test_paid_plans_have_the_calendar_step_and_free_and_affiliates_do_not(): void
    {
        $this->actingAs($this->owner)->get("/account/listings/{$this->listing->id}/edit/calendar")->assertOk()
            ->assertSee('Booking calendar')->assertSee('Imported calendars')->assertSee('data-calendar', false);

        $free = $this->completeListing($freeOwner = User::factory()->owner()->create(), null, ['plan_code' => 'free']);
        $this->actingAs($freeOwner)->get("/account/listings/{$free->id}/edit/basics")->assertOk()->assertDontSee('/edit/calendar', false);
        $this->actingAs($freeOwner)->get("/account/listings/{$free->id}/edit/calendar")->assertNotFound();
        $this->actingAs($freeOwner)->post(route('account.listings.calendar.toggle', $free), ['date' => '2027-06-15'])->assertNotFound();

        // Someone else's property is refused.
        $this->actingAs($freeOwner)->post(route('account.listings.calendar.toggle', $this->listing), ['date' => '2027-06-15'])->assertForbidden();
    }

    // --- iFrame embed ------------------------------------------------------------------------

    public function test_paid_plan_calendar_can_be_embedded_in_an_iframe(): void
    {
        $this->stay('2027-06-10', '2027-06-13');
        $url = route('calendar.embed', $this->listing->public_ref);

        $response = $this->get($url)->assertOk()
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeaderMissing('Set-Cookie');
        $html = $response->getContent();
        $this->assertStringContainsString('data-calendar-embed', $html);
        $this->assertStringContainsString('data-day="2027-06-10" data-state="pm"', $html);
        $this->assertStringContainsString($this->listing->publicUrl('en'), $html, 'Links back to the listing.');
        $this->assertStringNotContainsString('<footer', $html, 'No menu or footer.');
        $this->assertStringNotContainsString('calendar-toggle', $html, 'Read only.');

        // Other pages still cannot be framed.
        $this->get($this->listing->publicUrl('en'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_owner_gets_the_iframe_code(): void
    {
        $this->actingAs($this->owner)->get("/account/listings/{$this->listing->id}/edit/calendar")->assertOk()
            ->assertSee('Show this calendar on your own website')
            ->assertSee('&lt;iframe src=&quot;'.route('calendar.embed', $this->listing->public_ref).'&quot;', false);
    }

    public function test_no_embed_for_free_plans_affiliates_or_listings_that_are_not_live(): void
    {
        $free = Listing::factory()->live()->create(['plan_code' => 'free']);
        $affiliate = Listing::factory()->live()->create(['plan_code' => 'standard', 'owner_id' => User::factory()->affiliate()]);
        $draft = Listing::factory()->create(['plan_code' => 'standard']);

        foreach ([$free, $affiliate, $draft] as $listing) {
            $this->get(route('calendar.embed', $listing->public_ref))->assertNotFound();
        }
        $this->get('/robots.txt')->assertSee('Disallow: /embed/');
    }

    public function test_the_property_page_shows_the_calendar_with_half_days(): void
    {
        $this->stay('2027-06-10', '2027-06-13');

        $html = $this->get($this->listing->publicUrl('en'))->assertOk()->getContent();
        $this->assertStringContainsString('id="availability"', $html);
        $this->assertStringContainsString('data-day="2027-06-10" data-state="pm"', $html);
        $this->assertStringContainsString('data-day="2027-06-11" data-state="booked"', $html);
        $this->assertStringContainsString('data-day="2027-06-13" data-state="am"', $html);
        $this->assertStringContainsString('13 June: booked in the morning (guests leave), free from the afternoon', $html);
        $this->assertStringContainsString('Later months', $html);
        $this->assertStringNotContainsString('calendar-toggle', $html, 'Visitors cannot change it.');

        $free = Listing::factory()->live()->titled('Free home')->create(['plan_code' => 'free'])->load(['island', 'area']);
        $this->get($free->publicUrl('en'))->assertOk()->assertDontSee('id="availability"', false);
    }
}
