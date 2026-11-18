<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data only. No user accounts are seeded: create the first
     * super admin with `php artisan admin:create-super-admin`.
     */
    public function run(): void
    {
        $this->call([
            IslandSeeder::class,
            AreaSeeder::class,
            AmenitySeeder::class,
            PlanSeeder::class,
            LegalPagesSeeder::class,   // drafts; never overwrites pages edited in the admin
        ]);
    }
}
