<?php

namespace Tests\Feature\Database;

use App\Models\Amenity;
use App\Models\Area;
use App\Models\Island;
use App\Models\Plan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaAndSeedTest extends TestCase
{
    use RefreshDatabase;

    private const SPEC_TABLES = [
        'islands', 'areas',
        'agencies', 'users', 'island_admins',
        'plans', 'subscriptions', 'subscription_payments', 'paypal_events',
        'listings', 'listing_private', 'listing_translations', 'amenities', 'listing_amenities', 'listing_licences', 'admin_recommendations',
        'enquiries', 'deeplink_clicks', 'deeplink_clicks_daily', 'affiliate_commissions',
        'advertisers', 'ad_slots', 'ads', 'ad_stats_daily', 'affiliate_links',
        'pages', 'page_translations', 'audit_log', 'site_settings', 'hero_images',
    ];

    public function test_all_thirty_spec_tables_exist(): void
    {
        $this->assertCount(30, self::SPEC_TABLES);

        foreach (self::SPEC_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_listings_have_public_ref_and_nullable_pets(): void
    {
        $this->assertTrue(Schema::hasColumn('listings', 'public_ref'));
        $this->assertTrue(Schema::hasColumn('listings', 'pets_allowed'));
    }

    public function test_seeders_load_reference_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['mallorca', 'menorca'],
            Island::active()->pluck('slug')->all(),
            'Only Mallorca and Menorca are active for the MVP.'
        );
        $this->assertSame(4, Island::count());
        $this->assertGreaterThan(10, Area::count());
        $this->assertGreaterThan(10, Amenity::count());
        $this->assertSame(['agent_plus', 'featured', 'free', 'standard'], Plan::orderBy('code')->pluck('code')->all());
    }

    public function test_plan_prices_are_not_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Plan::whereNotNull('price_month_cents')->orWhereNotNull('price_year_cents')->count());
    }

    public function test_seeders_can_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Island::count());
    }
}
