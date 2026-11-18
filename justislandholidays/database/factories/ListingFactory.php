<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingPrivate;
use App\Models\ListingTranslation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->owner(),
            'island_id' => Island::factory(),
            'area_id' => fn (array $attributes) => Area::factory()->create(['island_id' => $attributes['island_id']])->id,
            'slug' => fake()->unique()->slug(3),
            'property_type' => 'villa',
            'status' => ListingStatus::Draft,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'sleeps' => 6,
            'display_lat' => 39.9,
            'display_lng' => 4.1,
        ];
    }

    /** Puts the listing on an existing island (with a new area on it). */
    public function onIsland(Island $island): static
    {
        return $this->state(fn () => ['island_id' => $island->id]);
    }

    public function status(ListingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function live(): static
    {
        return $this->status(ListingStatus::Live)->state(fn () => ['published_at' => now()]);
    }

    /** English title and summary. */
    public function titled(string $title, ?string $summary = null): static
    {
        return $this->afterCreating(function (Listing $listing) use ($title, $summary) {
            ListingTranslation::create(['listing_id' => $listing->id, 'lang' => 'en', 'title' => $title, 'summary' => $summary]);
        });
    }

    public function withDeeplink(string $url = 'https://booking.example.com/home/1', ?string $subIdParam = null, string $status = 'ok'): static
    {
        return $this->state(fn () => [
            'deeplink_url' => $url,
            'deeplink_subid_param' => $subIdParam,
            'deeplink_status' => $status,
        ]);
    }

    /** Owner-private contact details; enquiries are emailed to contact_email. */
    public function withPrivate(string $contactEmail = 'owner-inbox@example.com'): static
    {
        return $this->afterCreating(function (Listing $listing) use ($contactEmail) {
            $private = new ListingPrivate(['lat' => 39.9, 'lng' => 4.1, 'contact_email' => $contactEmail, 'contact_phone' => '+34 600 000 000']);
            $private->listing_id = $listing->id;
            $private->save();
        });
    }

    public function featured(): static
    {
        return $this->state(fn () => ['featured_until' => now()->addMonth()]);
    }
}
