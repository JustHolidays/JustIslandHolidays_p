<?php

namespace App\Http\Controllers\Account;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Listings\Rates;
use App\Models\Listing;
use App\Models\ListingRate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The rates table in the owner's editor (paid plans): periods with a nightly, weekly and
 * 14-night rate. Changes show on the property page straight away, and the From price
 * follows the lowest weekly rate still to come.
 */
class RatesController extends Controller
{
    public function store(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureRates($listing);
        if ($listing->rates()->count() >= Rates::MAX_PERIODS) {
            return back()->withErrors(['start_date' => 'You can add up to '.Rates::MAX_PERIODS.' rate periods. Remove old ones first.']);
        }

        $data = $request->validate([
            'start_date' => ['required', 'date', 'before:+3 years'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'after_or_equal:today', 'before:+4 years'],
            'nightly' => ['nullable', 'integer', 'min:1', 'max:100000', 'required_without_all:weekly,fortnight'],
            'weekly' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'fortnight' => ['nullable', 'integer', 'min:1', 'max:2000000'],
        ], [
            'start_date.required' => 'Add the first day of the period.',
            'end_date.required' => 'Add the last day of the period.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date, and not in the past.',
            'nightly.required_without_all' => 'Add at least one price: nightly, weekly or 14 nights.',
            '*.min' => 'Prices must be more than 0.',
        ]);

        $overlap = ListingRate::where('listing_id', $listing->id)
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap a period you already have. Remove that one first, or choose other dates.']);
        }

        $rate = new ListingRate([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'nightly_cents' => isset($data['nightly']) ? $data['nightly'] * 100 : null,
            'weekly_cents' => isset($data['weekly']) ? $data['weekly'] * 100 : null,
            'fortnight_cents' => isset($data['fortnight']) ? $data['fortnight'] * 100 : null,
        ]);
        $rate->listing_id = $listing->id;
        $rate->save();
        Audit::record($request->user(), 'rates.add', $listing, null, ['rate_id' => $rate->id] + $data);

        return back()->with('status', $this->savedMessage($listing, 'Rate period added.'));
    }

    public function destroy(Request $request, Listing $listing, int $rate): RedirectResponse
    {
        $this->ensureRates($listing);
        $record = ListingRate::where('listing_id', $listing->id)->whereKey($rate)->firstOrFail();
        $record->delete();
        Audit::record($request->user(), 'rates.remove', $listing, null, [
            'rate_id' => $rate,
            'start_date' => $record->start_date->toDateString(),
            'end_date' => $record->end_date->toDateString(),
        ]);

        return back()->with('status', $this->savedMessage($listing, 'Rate period removed.'));
    }

    private function savedMessage(Listing $listing, string $message): string
    {
        $listing->refresh();
        if (Rates::sync($listing)) {
            $message .= ' Your From price is now '.$listing->guidePrice().'.';
        }

        return $message;
    }

    /** Paid plans only; an archived listing cannot change. */
    private function ensureRates(Listing $listing): void
    {
        abort_unless($listing->hasRates(), 404);
        if ($listing->status === ListingStatus::Archived) {
            throw ValidationException::withMessages(['start_date' => 'This listing is archived and can no longer be edited.']);
        }
    }
}
