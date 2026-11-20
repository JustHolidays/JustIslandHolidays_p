<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\ListingRate;
use Illuminate\Support\Collection;

/**
 * The rates table (paid plans). While a property has a weekly rate still to come, its
 * From price is the lowest of them, so the two never disagree. Without one, the From price
 * the owner typed stays as it is.
 */
class Rates
{
    public const MAX_PERIODS = 60;

    /** Lowest weekly rate in a period that has not ended yet, in cents, or null. */
    public static function fromPrice(Listing $listing): ?int
    {
        if (! $listing->exists) {
            return null;
        }

        $lowest = ListingRate::where('listing_id', $listing->id)
            ->where('end_date', '>=', today()->toDateString())
            ->whereNotNull('weekly_cents')
            ->min('weekly_cents');

        if ($lowest === null || ! $listing->hasRates()) {
            return null;
        }

        return (int) $lowest;
    }

    /** Puts the From price in step with the rates. Returns true when it changed. */
    public static function sync(Listing $listing): bool
    {
        $price = self::fromPrice($listing);
        if ($price === null || $price === $listing->min_price_week_cents) {
            return false;
        }

        $listing->forceFill(['min_price_week_cents' => $price])->saveQuietly();

        return true;
    }

    /** Periods visitors see: not ended yet, in date order. */
    public static function upcoming(Listing $listing): Collection
    {
        return ListingRate::where('listing_id', $listing->id)
            ->where('end_date', '>=', today()->toDateString())
            ->orderBy('start_date')->orderBy('id')
            ->get();
    }

    /** "€1,200", or a dash when the price is empty. */
    public static function money(?int $cents, ?string $currency): string
    {
        if ($cents === null) {
            return '–';
        }
        $symbol = ($currency ?? 'EUR') === 'GBP' ? '£' : '€';

        return $symbol.number_format($cents / 100);
    }
}
