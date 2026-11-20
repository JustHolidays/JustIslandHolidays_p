<?php

namespace Database\Seeders;

use App\Models\Island;
use Illuminate\Database\Seeder;

/**
 * The four Balearic islands. Mallorca and Menorca are active for the MVP;
 * Ibiza and Formentera are switched on in phase 2. Intros are added in the admin.
 */
class IslandSeeder extends Seeder
{
    public function run(): void
    {
        $islands = [
            ['slug' => 'mallorca',   'name' => 'Mallorca',   'lat' => 39.6953, 'lng' => 3.0176, 'active' => true,  'sort' => 1],
            ['slug' => 'menorca',    'name' => 'Menorca',    'lat' => 39.9496, 'lng' => 4.1104, 'active' => true,  'sort' => 2],
            ['slug' => 'ibiza',      'name' => 'Ibiza',      'lat' => 38.9784, 'lng' => 1.4321, 'active' => false, 'sort' => 3],
            ['slug' => 'formentera', 'name' => 'Formentera', 'lat' => 38.7050, 'lng' => 1.4550, 'active' => false, 'sort' => 4],
        ];

        foreach ($islands as $i) {
            // Add missing islands only: names, switching on and order are edited in the admin
            // and must survive every deploy (deploy.sh runs the seeders).
            Island::firstOrCreate(['slug' => $i['slug']], [
                'name' => ['en' => $i['name'], 'es' => $i['name'], 'de' => $i['name']],
                'centre_lat' => $i['lat'],
                'centre_lng' => $i['lng'],
                'is_active' => $i['active'],
                'sort_order' => $i['sort'],
            ]);
        }
    }
}
