<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Reaproveita a normalização e as mensagens do cadastro, ajustando apenas as regras:
 * o CPF do próprio paciente é ignorado na verificação de unicidade e, em requisições
 * PATCH, apenas os campos enviados são validados (atualização parcial).
 */
class UpdatePatientRequest extends StorePatientRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        $optional = $this->isMethod('PATCH') ? ['sometimes'] : [];

        return [
            'name' => [...$optional, 'bail', 'required', 'string', 'max:150'],
            'cpf' => [...$optional, 'bail', 'required', 'digits:11', Rule::unique('patients', 'cpf')->ignore($patient)],
            'birth_date' => [...$optional, 'bail', 'required', 'date', 'before_or_equal:today'],
            'phone' => [...$optional, 'bail', 'nullable', 'digits_between:10,11'],
            'health_unit_id' => [...$optional, 'bail', 'required', 'integer', $this->healthUnitExists()],
        ];
    }
}
