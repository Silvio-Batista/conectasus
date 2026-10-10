<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    private HealthUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = HealthUnit::factory()->create(['name' => 'UBS Central', 'city' => 'Belém']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Silva',
            'cpf' => '123.456.789-01',
            'birth_date' => '1995-05-15',
            'phone' => '(91) 99999-9999',
            'health_unit_id' => $this->unit->id,
        ], $overrides);
    }

    // ---------------------------------------------------------------- listagem

    public function test_lists_patients_ordered_by_name_with_health_unit(): void
    {
        Patient::factory()->for($this->unit)->create(['name' => 'Carlos Lima']);
        Patient::factory()->for($this->unit)->create(['name' => 'Ana Souza']);

        $this->getJson('/api/patients')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana Souza')
            ->assertJsonPath('data.1.name', 'Carlos Lima')
            ->assertJsonPath('data.0.health_unit.name', 'UBS Central')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'uuid', 'name', 'cpf', 'birth_date', 'phone', 'health_unit_id', 'health_unit' => ['id', 'name', 'city']],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_returns_empty_list_when_there_are_no_patients(): void
    {
        $this->getJson('/api/patients')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    // ---------------------------------------------------------------- cadastro

    public function test_creates_patient_with_valid_data(): void
    {
        $response = $this->postJson('/api/patients', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonPath('data.cpf', '12345678901')
            ->assertJsonPath('data.birth_date', '1995-05-15')
            ->assertJsonPath('data.phone', '91999999999')
            ->assertJsonPath('data.health_unit_id', $this->unit->id)
            ->assertJsonPath('data.health_unit.name', 'UBS Central')
            ->assertJsonPath('data.health_unit.city', 'Belém');

        $this->assertDatabaseHas('patients', [
            'cpf' => '12345678901',
            'phone' => '91999999999',
            'health_unit_id' => $this->unit->id,
        ]);

        $this->assertNotEmpty(Patient::first()->uuid);
    }

    public function test_preserves_leading_zeros_in_cpf(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['cpf' => '012.345.678-90']))
            ->assertCreated()
            ->assertJsonPath('data.cpf', '01234567890');
    }

    public function test_phone_is_optional(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['phone' => null]))
            ->assertCreated()
            ->assertJsonPath('data.phone', null);

        $this->postJson('/api/patients', $this->validPayload(['cpf' => '98765432100', 'phone' => '']))
            ->assertCreated()
            ->assertJsonPath('data.phone', null);
    }

    public function test_requires_name(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['name' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'O nome é obrigatório.']);
    }

    public function test_rejects_name_longer_than_150_characters(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['name' => str_repeat('a', 151)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'O nome deve ter no máximo 150 caracteres.']);
    }

    public function test_rejects_cpf_without_exactly_eleven_digits(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['cpf' => '1234567890']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'O CPF deve conter exatamente 11 dígitos.']);

        $this->postJson('/api/patients', $this->validPayload(['cpf' => '123456789012']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'O CPF deve conter exatamente 11 dígitos.']);
    }

    public function test_rejects_duplicate_cpf(): void
    {
        Patient::factory()->for($this->unit)->create(['cpf' => '12345678901']);

        $this->postJson('/api/patients', $this->validPayload(['cpf' => '123.456.789-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'Este CPF já está cadastrado.']);

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_rejects_future_birth_date(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['birth_date' => now()->addDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date' => 'A data de nascimento não pode ser futura.']);
    }

    public function test_rejects_invalid_birth_date(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['birth_date' => 'data-invalida']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date' => 'Informe uma data de nascimento válida.']);
    }

    public function test_rejects_phone_with_invalid_length(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['phone' => '(91) 9999']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'O telefone deve conter 10 ou 11 dígitos.']);
    }

    public function test_rejects_nonexistent_health_unit(): void
    {
        $this->postJson('/api/patients', $this->validPayload(['health_unit_id' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['health_unit_id' => 'Selecione uma unidade de saúde válida.']);
    }

    public function test_rejects_soft_deleted_health_unit(): void
    {
        $deletedUnit = HealthUnit::factory()->create();
        $deletedUnit->delete();

        $this->postJson('/api/patients', $this->validPayload(['health_unit_id' => $deletedUnit->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['health_unit_id' => 'Selecione uma unidade de saúde válida.']);
    }

    public function test_requires_all_mandatory_fields(): void
    {
        $this->postJson('/api/patients', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'cpf', 'birth_date', 'health_unit_id'])
            ->assertJsonMissingValidationErrors(['phone']);
    }

    // ---------------------------------------------------------------- consulta

    public function test_shows_patient_with_health_unit(): void
    {
        $patient = Patient::factory()->for($this->unit)->create(['name' => 'Maria Silva']);

        $this->getJson("/api/patients/{$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonPath('data.health_unit.id', $this->unit->id);
    }

    public function test_returns_404_for_unknown_patient(): void
    {
        $this->getJson('/api/patients/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Registro não encontrado.');
    }

    // ---------------------------------------------------------------- atualização

    public function test_updates_patient_ignoring_its_own_cpf_in_uniqueness_check(): void
    {
        $patient = Patient::factory()->for($this->unit)->create(['cpf' => '12345678901']);
        $otherUnit = HealthUnit::factory()->create(['name' => 'UBS Marco']);

        $this->putJson("/api/patients/{$patient->id}", [
            'name' => 'Maria Atualizada',
            'cpf' => '123.456.789-01',
            'birth_date' => '1990-01-01',
            'phone' => '(91) 3222-1111',
            'health_unit_id' => $otherUnit->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Atualizada')
            ->assertJsonPath('data.cpf', '12345678901')
            ->assertJsonPath('data.phone', '9132221111')
            ->assertJsonPath('data.health_unit.name', 'UBS Marco');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'name' => 'Maria Atualizada',
            'health_unit_id' => $otherUnit->id,
        ]);
    }

    public function test_rejects_cpf_already_used_by_another_patient_on_update(): void
    {
        Patient::factory()->for($this->unit)->create(['cpf' => '11111111111']);
        $patient = Patient::factory()->for($this->unit)->create(['cpf' => '22222222222']);

        $this->putJson("/api/patients/{$patient->id}", $this->validPayload(['cpf' => '11111111111']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'Este CPF já está cadastrado.']);
    }

    public function test_put_requires_all_mandatory_fields(): void
    {
        $patient = Patient::factory()->for($this->unit)->create();

        $this->putJson("/api/patients/{$patient->id}", ['name' => 'Somente nome'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf', 'birth_date', 'health_unit_id']);
    }

    public function test_partially_updates_patient_with_patch(): void
    {
        $patient = Patient::factory()->for($this->unit)->create(['name' => 'Nome Original', 'phone' => '91999999999']);

        $this->patchJson("/api/patients/{$patient->id}", ['phone' => '(91) 3222-1111'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Original')
            ->assertJsonPath('data.phone', '9132221111');
    }

    public function test_returns_404_when_updating_unknown_patient(): void
    {
        $this->putJson('/api/patients/999', $this->validPayload())
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- exclusão

    public function test_deletes_patient(): void
    {
        $patient = Patient::factory()->for($this->unit)->create();

        $this->deleteJson("/api/patients/{$patient->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);

        $this->getJson("/api/patients/{$patient->id}")->assertNotFound();
        $this->getJson('/api/patients')->assertJsonPath('meta.total', 0);
    }

    public function test_returns_404_when_deleting_unknown_patient(): void
    {
        $this->deleteJson('/api/patients/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Registro não encontrado.');
    }

    // ---------------------------------------------------------------- busca e paginação

    public function test_searches_patients_by_partial_name(): void
    {
        Patient::factory()->for($this->unit)->create(['name' => 'Maria Silva']);
        Patient::factory()->for($this->unit)->create(['name' => 'Ana Maria Souza']);
        Patient::factory()->for($this->unit)->create(['name' => 'João Pedro']);

        $this->getJson('/api/patients?search=mar')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Ana Maria Souza')
            ->assertJsonPath('data.1.name', 'Maria Silva');

        $this->getJson('/api/patients?search=inexistente')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_paginates_ten_patients_per_page(): void
    {
        Patient::factory()->count(15)->for($this->unit)->create();

        $this->getJson('/api/patients')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 15);

        $this->getJson('/api/patients?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_pagination_links_keep_the_search_term(): void
    {
        Patient::factory()->count(12)->for($this->unit)->create(['name' => fn () => 'Maria '.fake()->lastName()]);

        $response = $this->getJson('/api/patients?search=maria')->assertOk();

        $this->assertStringContainsString('search=maria', $response->json('links.next'));
    }
}
