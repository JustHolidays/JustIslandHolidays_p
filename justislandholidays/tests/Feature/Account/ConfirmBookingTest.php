<?php

namespace Tests\Feature\Account;

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

/** Confirm Booking on an enquiry: books the requested dates on the property's calendar (20 Nov 2026). */
class ConfirmBookingTest extends TestCase
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

    private function enquiry(array $attributes = []): Enquiry
    {
        return Enquiry::factory()->create($attributes + [
            'listing_id' => $this->listing->id,
            'owner_id' => $this->owner->id,
            'guest_name' => 'Sarah Guest',
            'check_in' => '2027-06-12',
            'check_out' => '2027-06-19',
        ]);
    }

    private function confirm(Enquiry $enquiry, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->owner)
            ->from(route('account.enquiries.show', $enquiry))
            ->post(route('account.enquiries.confirm-booking', $enquiry));
    }

    public function test_confirm_booking_books_the_requested_dates(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->owner)->get(route('account.enquiries.show', $enquiry))->assertOk()
            ->assertSee('Confirm Booking')->assertSee('12 Jun 2027 to 19 Jun 2027 (7 nights)')
            ->assertSee('onsubmit="return confirm(', false)->assertSee('will be closed', false);

        $this->confirm($enquiry)->assertSessionHasNoErrors()->assertRedirect(route('account.enquiries.show', $enquiry))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'is now on your calendar'));

        $booking = CalendarBooking::sole();
        $this->assertSame($this->listing->id, $booking->listing_id);
        $this->assertNull($booking->feed_id, 'Booked as a stay the owner marked, so they can change or free it.');
        $this->assertSame(['2027-06-12', '2027-06-19'], [$booking->check_in->toDateString(), $booking->check_out->toDateString()]);
        $this->assertDatabaseHas('audit_log', ['action' => 'calendar.book_from_enquiry', 'actor_id' => $this->owner->id]);
        $this->assertSame('closed', $enquiry->fresh()->status, 'A confirmed booking closes the enquiry.');

        // Afterwards the page says so, and pressing again books nothing more.
        $this->actingAs($this->owner)->get(route('account.enquiries.show', $enquiry))->assertOk()
            ->assertSee('These dates are booked on your calendar.')->assertDontSee('>Confirm Booking<', false);
        $this->confirm($enquiry)->assertSessionHasErrors('booking');
        $this->assertSame(1, CalendarBooking::count());
    }

    public function test_the_alert_is_safe_with_any_guest_name(): void
    {
        $enquiry = $this->enquiry(['guest_name' => "Sinéad O'Brien \"Shiv\" </script>"]);

        $html = $this->actingAs($this->owner)->get(route('account.enquiries.show', $enquiry))->assertOk()->getContent();
        preg_match('/onsubmit="(return confirm\([^"]*\))"/', $html, $m);
        $this->assertNotEmpty($m, 'The alert attribute is intact.');
        $this->assertStringNotContainsString("O'Brien", $m[1]);
        $this->assertStringNotContainsString('</script>', $m[1]);
    }

    public function test_a_refused_booking_leaves_the_enquiry_open(): void
    {
        $enquiry = $this->enquiry(['check_in' => '2027-02-01', 'check_out' => '2027-02-08']);

        $this->confirm($enquiry)->assertSessionHasErrors('booking');
        $this->assertSame('new', $enquiry->fresh()->status);
    }

    public function test_dates_that_clash_are_refused(): void
    {
        $feed = new CalendarFeed(['name' => 'Airbnb', 'url' => 'https://airbnb.example/cal.ics']);
        $feed->listing_id = $this->listing->id;
        $feed->save();
        $taken = new CalendarBooking(['check_in' => '2027-06-15', 'check_out' => '2027-06-17']);
        $taken->listing_id = $this->listing->id;
        $taken->feed_id = $feed->id;
        $taken->save();
        $enquiry = $this->enquiry();

        $this->actingAs($this->owner)->get(route('account.enquiries.show', $enquiry))->assertOk()
            ->assertSee('Some of these nights are already booked (15 Jun, 16 Jun)')->assertDontSee('>Confirm Booking<', false);
        $this->confirm($enquiry)->assertSessionHasErrors('booking');
        $this->assertSame(1, CalendarBooking::count());
    }

    public function test_no_button_without_dates_in_the_past_or_on_the_free_plan(): void
    {
        $noDates = $this->enquiry(['check_in' => null, 'check_out' => null]);
        $past = $this->enquiry(['check_in' => '2027-02-01', 'check_out' => '2027-02-08']);

        $this->actingAs($this->owner)->get(route('account.enquiries.show', $noDates))->assertSee('did not give both an arrival and a departure date');
        $this->confirm($noDates)->assertSessionHasErrors('booking');
        $this->actingAs($this->owner)->get(route('account.enquiries.show', $past))->assertSee('The arrival date has passed.');
        $this->confirm($past)->assertSessionHasErrors('booking');

        $this->listing->forceFill(['plan_code' => 'free'])->save();
        $ok = $this->enquiry();
        $this->actingAs($this->owner)->get(route('account.enquiries.show', $ok))->assertSee('The booking calendar comes with the paid plans')->assertDontSee('>Confirm Booking<', false);
        $this->confirm($ok)->assertSessionHasErrors('booking');

        $this->assertSame(0, CalendarBooking::count());
    }

    public function test_only_the_recipient_can_confirm(): void
    {
        $enquiry = $this->enquiry();

        $this->confirm($enquiry, User::factory()->owner()->create())->assertForbidden();
        $this->confirm($enquiry, $this->superAdmin())->assertForbidden();
        $this->assertSame(0, CalendarBooking::count());
    }
}
