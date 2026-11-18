<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Placeholder plans. Final names, limits and prices are decided when the site is
 * finished and set by a super admin on the Plans page. Prices are left empty here
 * on purpose so they never live in code. Existing rows are not overwritten.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'free',       'name' => 'Free',       'audience' => 'owner', 'max_listings' => 1,  'is_featured' => false, 'search_boost' => 0,  'show_direct_contact' => false],
            ['code' => 'standard',   'name' => 'Standard',   'audience' => 'owner', 'max_listings' => 1,  'is_featured' => false, 'search_boost' => 10, 'show_direct_contact' => true],
            ['code' => 'featured',   'name' => 'Featured',   'audience' => 'owner', 'max_listings' => 1,  'is_featured' => true,  'search_boost' => 20, 'show_direct_contact' => true],
            ['code' => 'agent_plus', 'name' => 'Agent Plus', 'audience' => 'agent', 'max_listings' => 25, 'is_featured' => false, 'search_boost' => 10, 'show_direct_contact' => true],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['code' => $plan['code']], $plan + [
                'currency' => 'EUR',
                'price_month_cents' => null,
                'price_year_cents' => null,
                'is_active' => true,
            ]);
        }
    }
}
