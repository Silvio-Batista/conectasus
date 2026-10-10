<?php

namespace Database\Factories;

use App\Models\HealthUnit;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory opcional, usada nos testes automatizados e para popular dados de
 * desenvolvimento manualmente (ex.: via tinker). Não é executada pelos seeders.
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => fake()->dateTimeBetween('-90 years', '-1 day')->format('Y-m-d'),
            'phone' => fake()->boolean(80) ? fake()->numerify('919########') : null,
            'health_unit_id' => HealthUnit::factory(),
        ];
    }
}
