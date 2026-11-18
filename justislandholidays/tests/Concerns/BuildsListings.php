<?php

namespace Tests\Concerns;

use App\Enums\ListingStatus;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\Plan;
use App\Models\User;

trait BuildsListings
{
    protected function seedPlans(): void
    {
        Plan::create(['code' => 'free', 'name' => 'Free', 'audience' => 'owner', 'max_listings' => 1, 'search_boost' => 0]);
        Plan::create(['code' => 'standard', 'name' => 'Standard', 'audience' => 'owner', 'max_listings' => 1, 'search_boost' => 10, 'show_direct_contact' => true,
            'price_year_cents' => 12000, 'price_month_cents' => 1200, 'paypal_plan_id_year' => 'P-STD-YEAR', 'paypal_plan_id_month' => 'P-STD-MONTH']);
        Plan::create(['code' => 'featured', 'name' => 'Featured', 'audience' => 'owner', 'max_listings' => 3, 'search_boost' => 20, 'is_featured' => true,
            'price_year_cents' => 30000, 'paypal_plan_id_year' => 'P-FEAT-YEAR']);
        Plan::create(['code' => 'agent_plus', 'name' => 'Agent Plus', 'audience' => 'agent', 'max_listings' => 25, 'search_boost' => 10,
            'price_year_cents' => 90000, 'paypal_plan_id_year' => 'P-AGENT-YEAR']);
        \App\Listings\RankScore::flush();
    }

    /** A listing with everything needed to send for review (and, once verified, approve). */
    protected function completeListing(?User $owner = null, ?Island $island = null, array $attributes = [], bool $licenceVerified = false): Listing
    {
        $owner ??= User::factory()->owner()->create();
        $factory = Listing::factory()->titled('Villa Test', 'A lovely home')->withPrivate($owner->email);
        if ($island) {
            $factory = $factory->onIsland($island);
        }

        $listing = $factory->create($attributes + ['owner_id' => $owner->id, 'image_path' => 'listings/1/x-1600.webp', 'image_alt' => 'White villa']);

        if (! $owner->isAffiliate()) {
            $licence = new ListingLicence(['registration_number' => 'ETV/1234']);
            $licence->listing_id = $listing->id;
            $licence->forceFill(['status' => $licenceVerified ? 'verified' : 'pending'])->save();
        }

        return $listing->fresh();
    }

    protected function setStatus(Listing $listing, ListingStatus $status): Listing
    {
        $listing->forceFill(['status' => $status, 'published_at' => $status === ListingStatus::Live ? now() : null])->save();

        return $listing->fresh();
    }
}
