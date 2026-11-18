<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

/**
 * Amenities, views and accessibility features. Loaded by this seeder until the
 * Amenities admin page exists. Spanish and German names need native-speaker review.
 * The private pool is a listing column (has_private_pool), so it is not repeated here.
 */
class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        // code, category, en, es, de, filterable
        $amenities = [
            ['wifi', 'essentials', 'Wi-Fi', 'Wi-Fi', 'WLAN', true],
            ['air_con', 'essentials', 'Air conditioning', 'Aire acondicionado', 'Klimaanlage', true],
            ['heating', 'essentials', 'Heating', 'Calefacción', 'Heizung', false],
            ['washing_machine', 'essentials', 'Washing machine', 'Lavadora', 'Waschmaschine', false],
            ['dishwasher', 'essentials', 'Dishwasher', 'Lavavajillas', 'Geschirrspüler', false],
            ['parking', 'essentials', 'Parking', 'Aparcamiento', 'Parkplatz', true],
            ['tv', 'essentials', 'TV', 'Televisión', 'Fernseher', false],

            ['shared_pool', 'outdoor', 'Shared pool', 'Piscina comunitaria', 'Gemeinschaftspool', true],
            ['heated_pool', 'outdoor', 'Heated pool', 'Piscina climatizada', 'Beheizter Pool', true],
            ['garden', 'outdoor', 'Garden', 'Jardín', 'Garten', false],
            ['terrace', 'outdoor', 'Terrace', 'Terraza', 'Terrasse', false],
            ['bbq', 'outdoor', 'Barbecue', 'Barbacoa', 'Grill', true],

            ['cot', 'family', 'Cot', 'Cuna', 'Kinderbett', true],
            ['high_chair', 'family', 'High chair', 'Trona', 'Hochstuhl', false],
            ['pool_fence', 'family', 'Pool fence', 'Valla de piscina', 'Poolzaun', true],

            ['cleaning', 'services', 'Cleaning included', 'Limpieza incluida', 'Reinigung inklusive', false],
            ['linen', 'services', 'Bed linen and towels', 'Ropa de cama y toallas', 'Bettwäsche und Handtücher', false],
            ['ev_charging', 'services', 'Electric car charging', 'Carga para coche eléctrico', 'E-Auto-Ladestation', true],

            ['sea_view', 'view', 'Sea view', 'Vistas al mar', 'Meerblick', true],
            ['mountain_view', 'view', 'Mountain view', 'Vistas a la montaña', 'Bergblick', false],
            ['countryside_view', 'view', 'Countryside view', 'Vistas al campo', 'Blick ins Grüne', false],

            ['step_free', 'accessibility', 'Step-free entrance', 'Entrada sin escalones', 'Stufenloser Eingang', true],
            ['ground_floor_bedroom', 'accessibility', 'Ground floor bedroom', 'Dormitorio en planta baja', 'Schlafzimmer im Erdgeschoss', true],
            ['wheelchair_friendly', 'accessibility', 'Wheelchair friendly', 'Apto para sillas de ruedas', 'Rollstuhlgerecht', true],
            ['walk_in_shower', 'accessibility', 'Walk-in shower', 'Ducha a ras de suelo', 'Ebenerdige Dusche', false],
        ];

        foreach ($amenities as $order => [$code, $category, $en, $es, $de, $filterable]) {
            Amenity::updateOrCreate(['code' => $code], [
                'category' => $category,
                'name' => ['en' => $en, 'es' => $es, 'de' => $de],
                'is_filterable' => $filterable,
                'sort_order' => $order + 1,
            ]);
        }
    }
}
