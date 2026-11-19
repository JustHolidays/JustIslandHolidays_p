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
            IslandIntroSeeder::class,   // first-draft island intros; never overwrites edited text
            AreaSeeder::class,
            AmenitySeeder::class,
            PlanSeeder::class,
            AdSlotSeeder::class,       // the four island page advert spaces
            LegalPagesSeeder::class,   // drafts; never overwrites pages edited in the admin
        ]);
    }
}
