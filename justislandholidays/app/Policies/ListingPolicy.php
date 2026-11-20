<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /** Owner, same agency, island admin for the listing's island, or super admin. */
    public function view(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->isSuperAdmin()
            || ($user->isIslandAdmin() && $user->managesIsland($listing->island_id))
            || $this->isLister($user, $listing);
    }

    /** The account-area editor is for the lister only; admins edit in the admin area. */
    public function editAsLister(User $user, Listing $listing): bool
    {
        return $this->isLister($user, $listing);
    }

    /**
     * The full property editor in the admin area (20 Nov 2026): owner and agent properties,
     * for super admins and for island admins on their own islands. Affiliate properties are
     * edited through their own admin pages.
     */
    public function editAsAdmin(User $user, Listing $listing): bool
    {
        if ($listing->isAffiliateListing()) {
            return false;
        }

        return $user->isSuperAdmin() || ($user->isIslandAdmin() && $user->managesIsland($listing->island_id));
    }

    /** Lister sends a draft for review. */
    public function submit(User $user, Listing $listing): bool
    {
        return $this->isLister($user, $listing);
    }

    /** Owner and agent listings carry a licence; affiliate listings do not. */
    public function submitLicence(User $user, Listing $listing): bool
    {
        return $this->isLister($user, $listing) && ! $listing->isAffiliateListing();
    }

    /** Island admin for that island, or super admin. ListingWorkflow checks the licence and deeplink. */
    public function approve(User $user, Listing $listing): bool
    {
        return $user->isAdmin() && $user->managesIsland($listing->island_id);
    }

    /** Only a super admin rejects. */
    public function reject(User $user, Listing $listing): bool
    {
        return $user->isSuperAdmin();
    }

    /** An island admin recommends rejection for a super admin to decide. */
    public function recommendRejection(User $user, Listing $listing): bool
    {
        return $user->isIslandAdmin() && $user->managesIsland($listing->island_id);
    }

    public function pause(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function archive(User $user, Listing $listing): bool
    {
        return $this->update($user, $listing);
    }

    /** Admin-only fields: plan overrides, featured periods. */
    public function overridePlan(User $user, Listing $listing): bool
    {
        return $user->isSuperAdmin();
    }

    private function isLister(User $user, Listing $listing): bool
    {
        if (! $user->role?->isLister()) {
            return false;
        }

        return $listing->owner_id === $user->id
            || ($listing->agency_id !== null && $listing->agency_id === $user->agency_id);
    }
}
