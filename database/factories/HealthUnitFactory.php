<?php

namespace Database\Factories;

use App\Models\HealthUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthUnit>
 */
class HealthUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'UBS '.fake()->unique()->lastName(),
            'city' => fake()->city(),
        ];
    }
}
