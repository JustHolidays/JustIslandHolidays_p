<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'owner_id' => fn (array $attributes) => Listing::find($attributes['listing_id'])->owner_id,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'message' => fake()->sentence(12),
            'consent_given_at' => now(),
        ];
    }
}
