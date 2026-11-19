<?php

namespace App\Listings;

use App\Models\Listing;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * The only public read of listing_private: phone and website, for owner and agent
 * listings on a plan that shows direct contact. The phone shows only if the owner chose
 * to make it public (show_phone, 19 Nov 2026). Never the address, exact location or
 * contact email.
 */
final class DirectContact
{
    /** @return array{phone: ?string, website: ?string}|null */
    public static function for(Listing $listing): ?array
    {
        if ($listing->isAffiliateListing()) {
            return null;
        }

        $showsContact = (bool) Plan::where('code', $listing->plan_code)->value('show_direct_contact');
        if (! $showsContact) {
            return null;
        }

        $row = DB::table('listing_private')
            ->where('listing_id', $listing->id)
            ->first(['contact_phone', 'website_url', 'show_phone']);

        if (! $row) {
            return null;
        }

        $phone = (bool) $row->show_phone && filled($row->contact_phone) ? $row->contact_phone : null;
        $website = $row->website_url && str_starts_with($row->website_url, 'https://') ? $row->website_url : null;

        return $phone || $website ? ['phone' => $phone, 'website' => $website] : null;
    }
}
