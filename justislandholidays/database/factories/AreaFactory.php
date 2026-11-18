<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Island;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'island_id' => Island::factory(),
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'kind' => 'beach_resort',
            'name' => ['en' => $name],
            'sort_order' => 0,
        ];
    }
}
