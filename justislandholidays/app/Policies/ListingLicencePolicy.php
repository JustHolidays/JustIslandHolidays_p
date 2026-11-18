<?php

namespace App\Policies;

use App\Models\ListingLicence;
use App\Models\User;

/** Licence checks are manual lookups by an admin for the listing's island. */
class ListingLicencePolicy
{
    public function verify(User $user, ListingLicence $licence): bool
    {
        return $this->coversIsland($user, $licence);
    }

    /** Rejecting a licence (with a note) keeps the listing from going live; it is not a listing rejection. */
    public function reject(User $user, ListingLicence $licence): bool
    {
        return $this->coversIsland($user, $licence);
    }

    /** The uploaded document is in private storage. */
    public function viewDocument(User $user, ListingLicence $licence): bool
    {
        return $this->coversIsland($user, $licence)
            || $licence->listing?->owner_id === $user->id;
    }

    private function coversIsland(User $user, ListingLicence $licence): bool
    {
        $listing = $licence->listing;

        return $listing !== null
            && $user->isAdmin()
            && $user->managesIsland($listing->island_id);
    }
}
