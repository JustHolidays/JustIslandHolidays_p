<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CalendarBooking;
use App\Models\Listing;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Bookings (20 Nov 2026): every stay booked on the calendars of the owner's or agency's
 * properties. Stays booked here with Confirm Booking show the guest's name and the enquiry
 * reference; the others show where they came from. Upcoming by default, past on request.
 *
 * Stays the owner marked themselves can carry a short guest reference, and back-to-back
 * ones (one leaves the morning the next arrives, at the same property) can be joined into
 * one stay, for example after booking a week by pressing seven days on the calendar.
 */
class BookingsController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $past = $request->query('when') === 'past';
        $today = today()->toDateString();
        $listingIds = $this->listingIds($request);

        $bookings = CalendarBooking::whereIn('listing_id', $listingIds)
            ->when($past, fn ($q) => $q->where('check_out', '<', $today)->orderByDesc('check_in'),
                fn ($q) => $q->where('check_out', '>=', $today)->orderBy('check_in'))
            ->orderBy('id')
            ->with(['listing.translations', 'feed', 'enquiry'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Back-to-back stays the owner marked, upcoming only: the first of each run offers Join.
        $runs = $past ? [] : self::runs(CalendarBooking::whereIn('listing_id', $listingIds)
            ->whereNull('feed_id')->whereNull('enquiry_id')
            ->where('check_out', '>=', $today)
            ->orderBy('check_in')->get());

        return view('account.bookings', [
            'bookings' => $bookings,
            'past' => $past,
            'runs' => $runs,
            'hasCalendar' => Listing::whereIn('id', $listingIds)->get()->contains(fn (Listing $l) => $l->hasCalendar()),
        ]);
    }

    /** Joins a stay with the back-to-back stays that follow it into one stay. */
    public function join(Request $request, CalendarBooking $booking): RedirectResponse
    {
        $this->ensureOwnMarked($booking);

        $run = self::runs(CalendarBooking::where('listing_id', $booking->listing_id)
            ->whereNull('feed_id')->whereNull('enquiry_id')
            ->where('check_out', '>=', $booking->check_in->toDateString())
            ->orderBy('check_in')->get())[$booking->id] ?? null;
        if (! $run) {
            return back()->withErrors(['bookings' => 'There are no back-to-back stays to join with this one any more.']);
        }

        $stays = CalendarBooking::whereKey($run)->orderBy('check_in')->get();
        $first = $stays->first();
        $last = $stays->last();
        $refs = $stays->pluck('guest_ref')->filter()->unique()->implode(' / ');

        DB::transaction(function () use ($stays, $first, $last, $refs) {
            $first->forceFill([
                'check_out' => $last->check_out->toDateString(),
                'guest_ref' => $refs !== '' ? mb_substr($refs, 0, 100) : null,
            ])->save();
            CalendarBooking::whereKey($stays->skip(1)->pluck('id'))->delete();
        });
        Audit::record($request->user(), 'calendar.join', $first->listing, null, [
            'joined' => $stays->map(fn ($s) => $s->check_in->toDateString().'/'.$s->check_out->toDateString())->all(),
        ]);

        return back()->with('status', $stays->count().' stays joined: '.$first->check_in->format('j M Y').' to '.$last->check_out->format('j M Y').'.');
    }

    /** The owner's own short note on a stay they marked, shown in the Guest column. */
    public function reference(Request $request, CalendarBooking $booking): RedirectResponse
    {
        $this->ensureOwnMarked($booking);
        $errorBag = 'ref'.$booking->id;
        $validator = validator($request->all(), ['guest_ref' => ['nullable', 'string', 'max:100']], [
            'guest_ref.max' => 'Keep the guest reference to 100 characters.',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator, $errorBag)->withInput();
        }

        $ref = trim((string) $request->input('guest_ref')) ?: null;
        $booking->forceFill(['guest_ref' => $ref])->save();
        Audit::record($request->user(), 'calendar.guest_ref', $booking->listing, null, ['booking_id' => $booking->id, 'set' => $ref !== null]);

        return back()->with('status', $ref ? 'Guest reference saved.' : 'Guest reference removed.');
    }

    /**
     * Runs of back-to-back stays, keyed by the first stay's id: [first id => [ids in order]].
     * Only runs of two or more. Expects stays the owner marked, sorted by arrival.
     *
     * @return array<int, list<int>>
     */
    public static function runs(Collection $stays): array
    {
        $runs = [];
        foreach ($stays->groupBy('listing_id') as $forListing) {
            $current = [];
            $previous = null;
            foreach ($forListing->sortBy('check_in') as $stay) {
                if ($previous && $previous->check_out->toDateString() === $stay->check_in->toDateString()) {
                    $current[] = $stay->id;
                } else {
                    if (count($current) > 1) {
                        $runs[$current[0]] = $current;
                    }
                    $current = [$stay->id];
                }
                $previous = $stay;
            }
            if (count($current) > 1) {
                $runs[$current[0]] = $current;
            }
        }

        return $runs;
    }

    /** The same properties the lister can edit: their own, and their agency's. */
    private function listingIds(Request $request): Collection
    {
        $user = $request->user();

        return Listing::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)
                ->when($user->agency_id, fn ($q) => $q->orWhere('agency_id', $user->agency_id)))
            ->pluck('id');
    }

    /** A stay the owner marked, on a property this lister can edit. Imported and enquiry stays are not changed here. */
    private function ensureOwnMarked(CalendarBooking $booking): void
    {
        abort_unless($booking->listing && request()->user()->can('editAsLister', $booking->listing), 403);
        abort_if($booking->feed_id !== null || $booking->enquiry_id !== null, 404);
    }
}
