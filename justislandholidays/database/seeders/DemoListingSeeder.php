<?php

namespace Database\Seeders;

use App\Enums\ListingStatus;
use App\Enums\Role;
use App\Models\Amenity;
use App\Models\Area;
use App\Models\Island;
use App\Models\Listing;
use App\Models\ListingLicence;
use App\Models\ListingPrivate;
use App\Models\ListingTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Made-up demo listings for local development only:
 *   php artisan db:seed --class=DemoListingSeeder
 * Demo accounts use @example.com addresses and random passwords nobody knows.
 */
class DemoListingSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must never be seeded in production.');
        }

        mt_srand(42);

        $owners = collect(range(1, 3))->map(fn ($i) => $this->user("demo-owner{$i}@example.com", "Demo Owner {$i}", Role::Owner));
        $affiliate = $this->user('demo-affiliate@example.com', 'Demo Booking Partner', Role::Affiliate);
        $amenityIds = Amenity::pluck('id')->all();

        $names = ['Sa Punta', 'Es Pinar', 'Cala Blava', 'Son Moll', 'Ca na Maria', 'Sa Tanca', 'Es Molí', 'Can Bou', 'Villa Mar', 'Casa Sol', 'Sa Platja', 'Es Racó'];
        $types = ['villa', 'apartment', 'townhouse', 'finca', 'bungalow', 'studio'];
        $summaries = [
            'A quiet home a short walk from the beach, with a shaded terrace for long lunches.',
            'Bright and airy, with sea views from the living room and a garden full of olive trees.',
            'A traditional stone house restored with care, with a pool and space for the whole family.',
            'Modern and simple, close to cafés and the harbour. Ideal for couples.',
        ];

        $n = 0;
        foreach (Island::active()->with('areas')->get() as $island) {
            foreach ($island->areas->take(6) as $area) {
                foreach (range(1, 2) as $k) {
                    $n++;
                    $isAffiliate = $n % 5 === 0;
                    $owner = $isAffiliate ? $affiliate : $owners[$n % 3];
                    $type = $types[$n % count($types)];
                    $name = ucfirst($type).' '.$names[$n % count($names)];

                    $this->listing($island, $area, $owner, [
                        'slug' => Str::slug($name),
                        'title' => $name,
                        'summary' => $summaries[$n % count($summaries)],
                        'property_type' => $type,
                        'bedrooms' => $type === 'studio' ? 1 : 1 + ($n % 5),
                        'sleeps' => $type === 'studio' ? 2 : 2 + 2 * ($n % 5),
                        'has_private_pool' => in_array($type, ['villa', 'finca'], true),
                        'pets_allowed' => [null, true, false][$n % 3],
                        'distance_to_beach_m' => [150, 400, 900, 2500][$n % 4],
                        'min_price_week_cents' => $n % 6 === 0 ? null : (600 + 150 * ($n % 9)) * 100,
                        'featured' => $n % 7 === 0,
                        'deeplink' => $isAffiliate || $n % 4 === 0,
                        'amenities' => array_slice($amenityIds, $n % 5, 4 + $n % 4),
                    ], $isAffiliate);
                }
            }
        }
    }

    private function user(string $email, string $name, Role $role): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'role' => $role,
            'email_verified_at' => now(),
            'password' => Str::password(32),
        ])->save();

        return $user;
    }

    private function listing(Island $island, Area $area, User $owner, array $d, bool $isAffiliate): void
    {
        $slug = $d['slug'];
        $i = 2;
        while (Listing::withTrashed()->where('island_id', $island->id)->where('slug', $slug)->exists()) {
            $slug = $d['slug'].'-'.$i++;
        }

        $listing = new Listing([
            'island_id' => $island->id,
            'area_id' => $area->id,
            'slug' => $slug,
            'property_type' => $d['property_type'],
            'bedrooms' => $d['bedrooms'],
            'bathrooms' => max(1, intdiv($d['bedrooms'] + 1, 2)),
            'sleeps' => $d['sleeps'],
            'has_private_pool' => $d['has_private_pool'],
            'pets_allowed' => $isAffiliate ? null : $d['pets_allowed'],
            'distance_to_beach_m' => $d['distance_to_beach_m'],
            'display_lat' => (float) ($island->centre_lat ?? 39.7) + mt_rand(-200, 200) / 1000,
            'display_lng' => (float) ($island->centre_lng ?? 3.0) + mt_rand(-200, 200) / 1000,
            'currency' => 'EUR',
            'min_price_week_cents' => $d['min_price_week_cents'],
            'deeplink_url' => $d['deeplink'] ? 'https://booking.example.com/property/'.$slug.'?partner=demo' : null,
            'deeplink_subid_param' => $d['deeplink'] ? 'subid' : null,
        ]);
        $listing->owner_id = $owner->id;
        $listing->forceFill([
            'status' => ListingStatus::Live,
            'published_at' => now()->subDays(mt_rand(0, 60)),
            'rank_score' => mt_rand(0, 50),
            'featured_until' => $d['featured'] ? now()->addMonths(3) : null,
            'deeplink_status' => $d['deeplink'] ? 'ok' : 'unchecked',
        ])->save();

        ListingTranslation::create(['listing_id' => $listing->id, 'lang' => 'en', 'title' => $d['title'], 'summary' => $d['summary']]);

        $private = new ListingPrivate([
            'lat' => $listing->display_lat,
            'lng' => $listing->display_lng,
            'contact_email' => $owner->email,
        ]);
        $private->listing_id = $listing->id;
        $private->save();

        if (! $isAffiliate) {
            $licence = new ListingLicence(['registration_number' => 'ETV/'.mt_rand(1000, 9999)]);
            $licence->listing_id = $listing->id;
            $licence->forceFill(['status' => 'verified', 'verified_at' => now()])->save();
        }

        $listing->amenities()->sync($d['amenities']);
    }
}
