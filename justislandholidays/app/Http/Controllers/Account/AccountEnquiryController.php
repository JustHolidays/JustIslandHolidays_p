<?php

namespace App\Http\Controllers\Account;

use App\Calendar\Availability;
use App\Http\Controllers\Controller;
use App\Models\CalendarBooking;
use App\Models\Enquiry;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The lister's enquiry inbox. Replies happen by email; here owners track status. */
class AccountEnquiryController extends Controller
{
    private const STATUSES = ['new' => 'New', 'replied' => 'Replied', 'closed' => 'Closed', 'spam' => 'Spam'];

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = array_key_exists($status, self::STATUSES) ? $status : null;

        $enquiries = Enquiry::where('owner_id', $request->user()->id)
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'spam'))
            ->with('listing.translations')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('account.enquiries.index', [
            'enquiries' => $enquiries,
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Enquiry $enquiry): View
    {
        $enquiry->load('listing.translations');

        return view('account.enquiries.show', [
            'enquiry' => $enquiry,
            'statuses' => self::STATUSES,
            'booking' => $this->bookingState($enquiry),
        ]);
    }

    /**
     * Confirm Booking (20 Nov 2026): books the dates the guest asked for on the property's
     * calendar, as a stay the owner marked. Refused if any of those nights is already booked.
     */
    public function confirmBooking(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $state = $this->bookingState($enquiry);
        if ($state['state'] !== 'ready') {
            return back()->withErrors(['booking' => $state['message']]);
        }

        $listing = $enquiry->listing;
        $statusBefore = $enquiry->status;
        \Illuminate\Support\Facades\DB::transaction(function () use ($listing, $enquiry) {
            Availability::bookRange($listing, $enquiry->check_in->toImmutable(), $enquiry->check_out->toImmutable(), $enquiry->id);
            // A confirmed booking closes the enquiry (20 Nov 2026).
            $enquiry->forceFill(['status' => 'closed'])->save();
        });
        Audit::record($request->user(), 'calendar.book_from_enquiry', $listing, null, [
            'enquiry' => $enquiry->reference(),
            'check_in' => $enquiry->check_in->toDateString(),
            'check_out' => $enquiry->check_out->toDateString(),
            'enquiry_status' => ['before' => $statusBefore, 'after' => 'closed'],
        ]);

        return back()->with('status', 'Booked: '.$enquiry->check_in->format('j M Y').' to '.$enquiry->check_out->format('j M Y').' is now on your calendar, and this enquiry is closed. Remember to reply to '.$enquiry->guest_name.' to confirm.');
    }

    /**
     * Whether Confirm Booking can be used for this enquiry, and if not, why.
     *
     * @return array{state: string, message: ?string}
     */
    private function bookingState(Enquiry $enquiry): array
    {
        $listing = $enquiry->listing;
        $in = $enquiry->check_in?->toImmutable();
        $out = $enquiry->check_out?->toImmutable();

        return match (true) {
            ! $listing || $listing->trashed() => ['state' => 'gone', 'message' => 'This property is no longer listed.'],
            ! $listing->hasCalendar() => ['state' => 'no-calendar', 'message' => 'The booking calendar comes with the paid plans, so these dates cannot be booked here.'],
            ! $in || ! $out || $out->lte($in) => ['state' => 'no-dates', 'message' => 'The guest did not give both an arrival and a departure date, so there is nothing to book.'],
            $in->lt(CarbonImmutable::today()) => ['state' => 'past', 'message' => 'The arrival date has passed.'],
            CalendarBooking::where('listing_id', $listing->id)->whereNull('feed_id')
                ->whereDate('check_in', $in->toDateString())->whereDate('check_out', $out->toDateString())->exists()
                => ['state' => 'booked', 'message' => 'These dates are booked on your calendar.'],
            ($clash = array_keys(Availability::bookedNights($listing, $in, $out))) !== []
                => ['state' => 'clash', 'message' => 'Some of these nights are already booked ('.collect($clash)->map(fn ($d) => CarbonImmutable::parse($d)->format('j M'))->implode(', ').'), so the stay cannot be booked as asked.'],
            default => ['state' => 'ready', 'message' => null],
        };
    }

    public function updateStatus(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUSES))]]);

        $enquiry->forceFill(['status' => $data['status']])->save();

        return back()->with('status', 'Enquiry marked as '.strtolower(self::STATUSES[$data['status']]).'.');
    }
}
