<?php

namespace Database\Factories;

use App\Models\Island;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Island>
 */
class IslandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => ['en' => ucfirst($name), 'es' => ucfirst($name), 'de' => ucfirst($name)],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
