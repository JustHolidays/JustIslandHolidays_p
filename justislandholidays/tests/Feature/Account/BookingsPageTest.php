<?php

namespace Tests\Feature\Account;

use App\Calendar\Availability;
use App\Enums\ListingStatus;
use App\Models\CalendarBooking;
use App\Models\CalendarFeed;
use App\Models\Enquiry;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsListings;
use Tests\TestCase;

/** The owner's Bookings page: every stay on their calendars, with the guest for stays booked here (20 Nov 2026). */
class BookingsPageTest extends TestCase
{
    use BuildsListings;
    use RefreshDatabase;

    private User $owner;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00'));
        $this->seed(\Database\Seeders\PlanSeeder::class);
        \App\Models\Plan::whereIn('code', ['standard', 'featured', 'agent_plus'])->update(['has_booking_calendar' => true]);
        $this->owner = User::factory()->owner()->create();
        $this->listing = $this->setStatus($this->completeListing($this->owner, null, ['plan_code' => 'standard']), ListingStatus::Live);
    }

    private function stay(string $in, string $out, ?CalendarFeed $feed = null, ?Listing $listing = null): CalendarBooking
    {
        $b = new CalendarBooking(['check_in' => $in, 'check_out' => $out]);
        $b->listing_id = ($listing ?? $this->listing)->id;
        $b->feed_id = $feed?->id;
        $b->save();

        return $b;
    }

    private function confirmedEnquiry(): Enquiry
    {
        $enquiry = Enquiry::factory()->create([
            'listing_id' => $this->listing->id, 'owner_id' => $this->owner->id,
            'guest_name' => 'Sarah Guest', 'check_in' => '2027-06-12', 'check_out' => '2027-06-19',
        ]);
        $this->actingAs($this->owner)->post(route('account.enquiries.confirm-booking', $enquiry))->assertSessionHasNoErrors();

        return $enquiry;
    }

    public function test_menu_link_and_stays_with_guest_and_enquiry(): void
    {
        $enquiry = $this->confirmedEnquiry();
        $feed = new CalendarFeed(['name' => 'Airbnb', 'url' => 'https://airbnb.example/cal.ics']);
        $feed->listing_id = $this->listing->id;
        $feed->save();
        $this->stay('2027-07-01', '2027-07-08', $feed);
        $this->stay('2027-05-01', '2027-05-04');

        $this->assertSame($enquiry->id, CalendarBooking::where('check_in', '2027-06-12')->sole()->enquiry_id);

        $this->actingAs($this->owner)->get(route('account.home'))->assertSee(route('account.bookings'), false);

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()
            ->assertSeeInOrder(['Sat 1 May 2027', 'Marked by you', 'Sat 12 Jun 2027', 'Sarah Guest', $enquiry->reference(), 'Booked from an enquiry', 'Thu 1 Jul 2027', 'From Airbnb'])
            ->assertSee(route('account.enquiries.show', $enquiry), false)
            ->assertSee(route('account.listings.edit', [$this->listing, 'bookings']), false);
    }

    public function test_past_stays_are_shown_on_request(): void
    {
        $this->stay('2027-01-10', '2027-01-15');
        $this->stay('2027-06-01', '2027-06-08');

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()
            ->assertSee('Tue 1 Jun 2027')->assertDontSee('Sun 10 Jan 2027');
        $this->actingAs($this->owner)->get(route('account.bookings', ['when' => 'past']))->assertOk()
            ->assertSee('Sun 10 Jan 2027')->assertDontSee('Tue 1 Jun 2027');
    }

    public function test_only_their_own_and_their_agencys_properties(): void
    {
        $other = $this->setStatus($this->completeListing(User::factory()->owner()->create(), null, ['plan_code' => 'standard']), ListingStatus::Live);
        $this->stay('2027-06-01', '2027-06-08');
        $this->stay('2027-08-01', '2027-08-08', null, $other);

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()
            ->assertSee('Tue 1 Jun 2027')->assertDontSee('Sun 1 Aug 2027');
    }

    public function test_the_guest_stays_with_both_parts_when_a_night_is_freed(): void
    {
        $enquiry = $this->confirmedEnquiry();
        Availability::freeNight($this->listing, CarbonImmutable::parse('2027-06-15'));

        $parts = CalendarBooking::orderBy('check_in')->get();
        $this->assertCount(2, $parts);
        $this->assertSame([$enquiry->id, $enquiry->id], $parts->pluck('enquiry_id')->all());
    }

    public function test_deleting_the_enquiry_keeps_the_stay_booked(): void
    {
        $enquiry = $this->confirmedEnquiry();
        $this->actingAs($this->owner)->get(route('account.home'));   // clears the "Booked" message, which names the guest
        $enquiry->delete();

        $stay = CalendarBooking::sole();
        $this->assertNull($stay->enquiry_id);
        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()->assertSee('Marked by you')->assertDontSee('Sarah Guest');
    }

    public function test_booked_dates_page_shows_the_guest(): void
    {
        $enquiry = $this->confirmedEnquiry();

        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'bookings']))->assertOk()
            ->assertSee('Guest: Sarah Guest, enquiry')->assertSee($enquiry->reference());
    }

    public function test_affiliates_and_admins_have_no_bookings_page(): void
    {
        $this->actingAs(User::factory()->affiliate()->create())->get(route('account.bookings'))->assertForbidden();
        $this->actingAs($this->superAdmin())->get(route('account.bookings'))->assertForbidden();
    }

    public function test_free_plan_explains_where_bookings_come_from(): void
    {
        $this->listing->forceFill(['plan_code' => 'free'])->save();

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()
            ->assertSee('Bookings come from the booking calendar, which is part of the paid plans.');
    }

    // --- Guest references and joining back-to-back stays ----------------------------------

    public function test_back_to_back_stays_the_owner_marked_can_be_joined(): void
    {
        // A week booked by pressing seven days, then a gap, then a separate stay.
        foreach (range(12, 18) as $day) {
            Availability::bookNight($this->listing, CarbonImmutable::parse("2027-06-{$day}"));
        }
        $this->stay('2027-06-25', '2027-06-28');
        $first = CalendarBooking::orderBy('check_in')->first();
        $first->forceFill(['guest_ref' => 'Smith family'])->save();

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()
            ->assertSee('Join 7 stays')->assertSee('Back-to-back to 19 Jun 2027')
            ->assertSee(route('account.bookings.join', $first), false);

        $this->actingAs($this->owner)->from(route('account.bookings'))->post(route('account.bookings.join', $first))
            ->assertSessionHasNoErrors()->assertRedirect(route('account.bookings'));

        $stays = CalendarBooking::orderBy('check_in')->get();
        $this->assertCount(2, $stays);
        $this->assertSame(['2027-06-12', '2027-06-19'], [$stays[0]->check_in->toDateString(), $stays[0]->check_out->toDateString()]);
        $this->assertSame('Smith family', $stays[0]->guest_ref);
        $this->assertSame('2027-06-25', $stays[1]->check_in->toDateString(), 'A stay with a gap before it is left alone.');
        $this->assertDatabaseHas('audit_log', ['action' => 'calendar.join']);

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertDontSee('Join ');
    }

    public function test_imported_and_enquiry_stays_are_not_joined_or_given_a_reference(): void
    {
        $this->confirmedEnquiry();   // 12 to 19 June, from an enquiry
        $own = $this->stay('2027-06-19', '2027-06-21');   // back-to-back, but the first is the guest's
        $enquiryStay = CalendarBooking::whereNotNull('enquiry_id')->sole();

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()->assertDontSee('Join ');
        $this->actingAs($this->owner)->post(route('account.bookings.join', $enquiryStay))->assertNotFound();
        $this->actingAs($this->owner)->put(route('account.bookings.reference', $enquiryStay), ['guest_ref' => 'X'])->assertNotFound();
        $this->actingAs($this->owner)->from(route('account.bookings'))->post(route('account.bookings.join', $own))->assertSessionHasErrors('bookings');
    }

    public function test_owner_adds_changes_and_removes_a_guest_reference(): void
    {
        $stay = $this->stay('2027-06-01', '2027-06-08');

        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()->assertSee('name="guest_ref"', false);

        $this->actingAs($this->owner)->from(route('account.bookings'))->put(route('account.bookings.reference', $stay), ['guest_ref' => '  Booking.com 4471 '])
            ->assertSessionHasNoErrors()->assertRedirect(route('account.bookings'));
        $this->assertSame('Booking.com 4471', $stay->fresh()->guest_ref);
        $this->actingAs($this->owner)->get(route('account.bookings'))->assertSee('value="Booking.com 4471"', false);
        $this->actingAs($this->owner)->get(route('account.listings.edit', [$this->listing, 'bookings']))->assertSee('Guest: Booking.com 4471');

        $this->actingAs($this->owner)->from(route('account.bookings'))->put(route('account.bookings.reference', $stay), ['guest_ref' => str_repeat('x', 101)])
            ->assertSessionHasErrorsIn('ref'.$stay->id, 'guest_ref');
        $this->actingAs($this->owner)->put(route('account.bookings.reference', $stay), ['guest_ref' => ''])->assertSessionHasNoErrors();
        $this->assertNull($stay->fresh()->guest_ref);
    }

    public function test_freeing_a_night_keeps_the_reference_on_both_parts(): void
    {
        $stay = $this->stay('2027-06-01', '2027-06-08');
        $stay->forceFill(['guest_ref' => 'Smith family'])->save();

        Availability::freeNight($this->listing, CarbonImmutable::parse('2027-06-04'));

        $this->assertSame(['Smith family', 'Smith family'], CalendarBooking::orderBy('check_in')->pluck('guest_ref')->all());
    }

    public function test_others_cannot_join_or_label_these_stays(): void
    {
        $stay = $this->stay('2027-06-01', '2027-06-08');
        $this->stay('2027-06-08', '2027-06-10');
        $other = User::factory()->owner()->create();

        $this->actingAs($other)->post(route('account.bookings.join', $stay))->assertForbidden();
        $this->actingAs($other)->put(route('account.bookings.reference', $stay), ['guest_ref' => 'Mine'])->assertForbidden();
        $this->assertSame(2, CalendarBooking::count());
        $this->assertNull($stay->fresh()->guest_ref);
    }

    public function test_references_are_cleared_24_months_after_the_stay(): void
    {
        $old = $this->stay('2027-01-01', '2027-01-08');
        $old->forceFill(['guest_ref' => 'Old guest'])->save();
        $recent = $this->stay('2027-06-01', '2027-06-08');
        $recent->forceFill(['guest_ref' => 'Recent guest'])->save();

        $this->travelTo(CarbonImmutable::parse('2029-03-01 04:00'));
        $this->artisan('data:retention')->assertSuccessful();

        $this->assertNull($old->fresh()->guest_ref);
        $this->assertNotNull($old->fresh(), 'The stay itself stays booked.');
        $this->assertSame('Recent guest', $recent->fresh()->guest_ref);
    }

    // --- Download -------------------------------------------------------------------------

    private function download(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->from(route('account.bookings'))->get(route('account.bookings.download', $query));
    }

    public function test_download_form_is_on_the_page(): void
    {
        $this->actingAs($this->owner)->get(route('account.bookings'))->assertOk()
            ->assertSee('Download your booking list')->assertSee(route('account.bookings.download'), false)
            ->assertSee('value="pdf"', false)->assertSee('value="xlsx"', false);
    }

    public function test_pdf_lists_stays_in_the_dates(): void
    {
        $enquiry = $this->confirmedEnquiry();   // 12 to 19 June
        $this->stay('2027-05-30', '2027-06-02')->forceFill(['guest_ref' => 'Smith family'])->save();   // overlaps the start
        $this->stay('2027-09-01', '2027-09-05');   // outside

        $response = $this->download(['from' => '2027-06-01', 'to' => '2027-06-30', 'format' => 'pdf'])->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('bookings-2027-06-01-to-2027-06-30.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseHas('audit_log', ['action' => 'bookings.download']);

        // The PDF is made from this page; check what goes into it.
        $html = view('account.bookings-pdf', [
            'rows' => collect([['property' => 'Villa', 'in' => CarbonImmutable::parse('2027-06-12'), 'out' => CarbonImmutable::parse('2027-06-19'), 'nights' => 7, 'guest' => 'Sarah Guest', 'enquiry' => $enquiry->reference(), 'source' => 'Booked from an enquiry']]),
            'from' => CarbonImmutable::parse('2027-06-01'), 'to' => CarbonImmutable::parse('2027-06-30'), 'who' => 'Owner', 'headings' => \App\Http\Controllers\Account\BookingsDownloadController::HEADINGS,
        ])->render();
        $this->assertStringContainsString('Sarah Guest', $html);
        $this->assertStringContainsString($enquiry->reference(), $html);
    }

    public function test_excel_has_one_row_per_stay_and_text_is_never_a_formula(): void
    {
        $enquiry = $this->confirmedEnquiry();
        $this->stay('2027-06-25', '2027-06-28')->forceFill(['guest_ref' => '=HYPERLINK("http://bad.example","x")'])->save();
        $this->stay('2027-09-01', '2027-09-05');   // outside

        $response = $this->download(['from' => '2027-06-01', 'to' => '2027-06-30', 'format' => 'xlsx'])->assertOk();
        $this->assertStringContainsString('bookings-2027-06-01-to-2027-06-30.xlsx', $response->headers->get('Content-Disposition'));

        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        $text = $sheet.$shared;

        $this->assertSame(3, substr_count($sheet, '<row '), 'Heading row plus two stays.');
        $this->assertStringContainsString('Sarah Guest', $text);
        $this->assertStringContainsString($enquiry->reference(), $text);
        $this->assertStringNotContainsString('<f>', $sheet, 'No formulas.');
        $this->assertStringContainsString('HYPERLINK', $text, 'The reference is kept, as plain text.');
    }

    public function test_only_their_own_stays_are_downloaded(): void
    {
        $other = $this->setStatus($this->completeListing(User::factory()->owner()->create(), null, ['plan_code' => 'standard']), ListingStatus::Live);
        $this->stay('2027-06-01', '2027-06-08', null, $other)->forceFill(['guest_ref' => 'Not yours'])->save();
        $this->stay('2027-06-10', '2027-06-12')->forceFill(['guest_ref' => 'Yours'])->save();

        $file = $this->download(['from' => '2027-06-01', 'to' => '2027-06-30', 'format' => 'xlsx'])->assertOk()->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $zip->open($file);
        $text = $zip->getFromName('xl/worksheets/sheet1.xml').$zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        $this->assertStringContainsString('Yours', $text);
        $this->assertStringNotContainsString('Not yours', $text);
    }

    public function test_download_choices_are_checked(): void
    {
        $this->download(['from' => '2027-06-30', 'to' => '2027-06-01', 'format' => 'pdf'])->assertSessionHasErrors('to');
        $this->download(['from' => '2027-01-01', 'to' => '2031-01-01', 'format' => 'pdf'])->assertSessionHasErrors('to');
        $this->download(['from' => '2027-06-01', 'to' => '2027-06-30', 'format' => 'csv'])->assertSessionHasErrors('format');
        $this->download([])->assertSessionHasErrors(['from', 'to', 'format']);

        $this->actingAs(User::factory()->affiliate()->create())->get(route('account.bookings.download', ['from' => '2027-06-01', 'to' => '2027-06-30', 'format' => 'pdf']))->assertForbidden();
    }
}
