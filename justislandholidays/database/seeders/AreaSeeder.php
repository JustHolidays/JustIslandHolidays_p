<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Island;
use Illuminate\Database\Seeder;

/**
 * Starter resorts and towns for the two MVP islands. Map centres and intros are
 * left empty for admins to set in Islands and resorts.
 */
class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            'menorca' => [
                ['cala-en-porter', 'Cala en Porter', 'beach_resort'],
                ['son-bou', 'Son Bou', 'beach_resort'],
                ['cala-galdana', 'Cala Galdana', 'beach_resort'],
                ['binibeca', 'Binibeca', 'coastal_resort'],
                ['punta-prima', 'Punta Prima', 'beach_resort'],
                ['calan-bosch', "Cala'n Bosch", 'beach_resort'],
                ['cala-blanca', 'Cala Blanca', 'beach_resort'],
                ['arenal-den-castell', "Arenal d'en Castell", 'beach_resort'],
                ['son-parc', 'Son Parc', 'beach_resort'],
                ['fornells', 'Fornells', 'coastal_resort'],
                ['es-castell', 'Es Castell', 'town'],
                ['mahon', 'Mahón', 'town'],
                ['ciutadella', 'Ciutadella', 'town'],
                ['sant-lluis', 'Sant Lluís', 'town'],
                ['alaior', 'Alaior', 'town'],
                ['es-mercadal', 'Es Mercadal', 'town'],
            ],
            'mallorca' => [
                ['puerto-pollensa', 'Puerto Pollensa', 'beach_resort'],
                ['alcudia', 'Alcúdia', 'beach_resort'],
                ['can-picafort', 'Can Picafort', 'beach_resort'],
                ['cala-dor', "Cala d'Or", 'beach_resort'],
                ['cala-millor', 'Cala Millor', 'beach_resort'],
                ['cala-ratjada', 'Cala Ratjada', 'beach_resort'],
                ['portocolom', 'Portocolom', 'coastal_resort'],
                ['colonia-de-sant-jordi', 'Colònia de Sant Jordi', 'beach_resort'],
                ['santa-ponsa', 'Santa Ponsa', 'beach_resort'],
                ['port-dandratx', "Port d'Andratx", 'coastal_resort'],
                ['puerto-de-soller', 'Puerto de Sóller', 'coastal_resort'],
                ['pollenca', 'Pollença', 'town'],
                ['soller', 'Sóller', 'town'],
                ['deia', 'Deià', 'town'],
                ['valldemossa', 'Valldemossa', 'town'],
                ['palma', 'Palma', 'town'],
                ['santanyi', 'Santanyí', 'town'],
                ['arta', 'Artà', 'town'],
            ],
        ];

        foreach ($areas as $islandSlug => $rows) {
            $island = Island::where('slug', $islandSlug)->firstOrFail();

            foreach ($rows as $order => [$slug, $name, $kind]) {
                Area::updateOrCreate(
                    ['island_id' => $island->id, 'slug' => $slug],
                    [
                        'name' => ['en' => $name, 'es' => $name, 'de' => $name],
                        'kind' => $kind,
                        'sort_order' => $order + 1,
                    ],
                );
            }
        }
    }
}
