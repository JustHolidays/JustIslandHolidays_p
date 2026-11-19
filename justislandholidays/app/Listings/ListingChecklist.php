<?php

namespace App\Listings;

use App\Models\Listing;

/**
 * What a listing needs before it can be sent for review, and before an admin can approve it.
 * Messages are written for the lister or admin to read.
 */
final class ListingChecklist
{
    /** @return list<string> */
    public static function missingForSubmit(Listing $listing): array
    {
        $listing->loadMissing(['translations', 'private', 'licence', 'owner']);
        $missing = [];

        $en = $listing->translations->firstWhere('lang', 'en');
        if (! $en || trim((string) $en->title) === '') {
            $missing[] = 'Add a title in English.';
        }
        if (! $en || trim((string) $en->summary) === '') {
            $missing[] = 'Add a short description in English.';
        }
        if (! $listing->image_path) {
            $missing[] = 'Add a photo.';
        } elseif (trim((string) $listing->image_alt) === '') {
            $missing[] = 'Describe the photo for people who cannot see it.';
        }
        if (! $listing->private) {
            $missing[] = 'Add the location and the email address for enquiries.';
        }

        if ($listing->isAffiliateListing()) {
            if (! $listing->deeplink_url) {
                $missing[] = 'Add the booking link for the Check availability button.';
            }
        } elseif (! $listing->licence || (blank($listing->licence->registration_number) && blank($listing->licence->national_registry_no))) {
            $missing[] = 'Add your tourist licence number.';
        }

        return $missing;
    }

    /** Everything for submit, plus the checks an admin makes. @return list<string> */
    public static function missingForApproval(Listing $listing): array
    {
        $missing = self::missingForSubmit($listing);

        if (! $listing->isAffiliateListing() && $listing->licence?->status !== 'verified') {
            $missing[] = 'The licence number has not been verified yet.';
        }
        // Affiliate links only: owners and agents are responsible for their own links.
        if ($listing->isAffiliateListing() && $listing->deeplink_url !== null && $listing->deeplink_status !== 'ok') {
            $missing[] = 'The Check availability link has not been checked yet.';
        }

        return $missing;
    }
}
