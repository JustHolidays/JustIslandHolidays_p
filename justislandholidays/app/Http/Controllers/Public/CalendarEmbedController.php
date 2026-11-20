<?php

namespace App\Http\Controllers\Public;

use App\Calendar\Availability;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * /embed/calendar/{ref}: a property's booking calendar on its own, for owners on paid plans
 * to show on their own website in an iframe (19 Nov 2026). Stateless (no session or cookies),
 * not indexed, and the only page other sites may frame. Only dates are shown.
 */
class CalendarEmbedController extends Controller
{
    public function __invoke(string $ref): Response
    {
        $listing = Listing::where('public_ref', $ref)->with(['island', 'area', 'translations', 'owner'])->first();

        abort_unless(
            $listing && $listing->status === ListingStatus::Live && $listing->island->is_active && $listing->hasCalendar(),
            404,
        );

        $offset = max(0, min(9, (int) request()->query('months', 0)));
        $count = max(1, min(3, (int) request()->query('show', 3)));

        return response()
            ->view('public.calendar-embed', [
                'listing' => $listing,
                'months' => Availability::months($listing, CarbonImmutable::today()->startOfMonth()->addMonths($offset), $count),
                'offset' => $offset,
                'count' => $count,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /** The address owners put in their iframe. */
    public static function url(Listing $listing): string
    {
        return route('calendar.embed', $listing->public_ref);
    }

    /** Ready-to-paste iframe code. */
    public static function snippet(Listing $listing): string
    {
        $title = 'Availability: '.$listing->title('en');

        return '<iframe src="'.e(self::url($listing)).'" title="'.e($title).'" width="100%" height="440" style="border:0" loading="lazy"></iframe>';
    }
}
