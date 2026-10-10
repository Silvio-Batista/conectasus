<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthUnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_health_units_ordered_by_name(): void
    {
        HealthUnit::factory()->create(['name' => 'UBS Pedreira', 'city' => 'Belém']);
        HealthUnit::factory()->create(['name' => 'UBS Central', 'city' => 'Belém']);
        HealthUnit::factory()->create(['name' => 'UBS Marco', 'city' => 'Belém']);

        $this->getJson('/api/health-units')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'UBS Central')
            ->assertJsonPath('data.1.name', 'UBS Marco')
            ->assertJsonPath('data.2.name', 'UBS Pedreira')
            ->assertExactJsonStructure(['data' => ['*' => ['id', 'uuid', 'name', 'city']]]);
    }

    public function test_excludes_soft_deleted_units_from_listing(): void
    {
        HealthUnit::factory()->create(['name' => 'UBS Ativa']);
        HealthUnit::factory()->create(['name' => 'UBS Excluída'])->delete();

        $this->getJson('/api/health-units')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'UBS Ativa');
    }

    public function test_generates_uuid_automatically(): void
    {
        $unit = HealthUnit::create(['name' => 'UBS Central', 'city' => 'Belém']);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $unit->uuid
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('health_units', 3);
    }

    public function test_unit_with_patients_cannot_be_permanently_deleted(): void
    {
        $unit = HealthUnit::factory()->create();
        Patient::factory()->for($unit)->create();

        $this->expectException(QueryException::class);

        $unit->forceDelete();
    }

    public function test_relationships_work_in_both_directions(): void
    {
        $unit = HealthUnit::factory()->create();
        $patients = Patient::factory()->count(2)->for($unit)->create();

        $this->assertCount(2, $unit->patients);
        $this->assertTrue($patients->first()->healthUnit->is($unit));
    }

    public function test_health_units_are_read_only_in_the_api(): void
    {
        $this->postJson('/api/health-units', ['name' => 'Nova', 'city' => 'Belém'])->assertMethodNotAllowed();
    }
}
