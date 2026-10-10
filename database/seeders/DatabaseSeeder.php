<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Observação: não usar WithoutModelEvents aqui, pois o UUID das unidades
     * é gerado pelo evento "creating" do Eloquent (trait HasUuids).
     */
    public function run(): void
    {
        $this->call([
            HealthUnitSeeder::class,
        ]);
    }
}
