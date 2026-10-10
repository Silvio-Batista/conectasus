<?php

namespace Database\Seeders;

use App\Models\HealthUnit;
use Illuminate\Database\Seeder;

class HealthUnitSeeder extends Seeder
{
    /**
     * Unidades de saúde iniciais. firstOrCreate evita duplicação em execuções repetidas.
     */
    public function run(): void
    {
        $units = [
            ['name' => 'UBS PA', 'city' => 'Belém'],
            ['name' => 'UBS SC', 'city' => 'Florianópolis'],
            ['name' => 'UBS RS', 'city' => 'Porto Alegre'],
        ];

        foreach ($units as $unit) {
            HealthUnit::firstOrCreate($unit);
        }
    }
}
