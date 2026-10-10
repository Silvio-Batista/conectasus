<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Remove as máscaras de CPF e telefone antes da validação,
     * preservando zeros à esquerda (os valores são tratados como texto).
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('cpf')) {
            $normalized['cpf'] = $this->digitsOnly($this->input('cpf'));
        }

        if ($this->has('phone')) {
            $normalized['phone'] = $this->digitsOnly($this->input('phone')) ?: null;
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150'],
            'cpf' => ['bail', 'required', 'digits:11', Rule::unique('patients', 'cpf')],
            'birth_date' => ['bail', 'required', 'date', 'before_or_equal:today'],
            'phone' => ['bail', 'nullable', 'digits_between:10,11'],
            'health_unit_id' => ['bail', 'required', 'integer', $this->healthUnitExists()],
        ];
    }

    /**
     * A unidade informada precisa existir e não pode estar excluída (soft delete).
     */
    protected function healthUnitExists(): Exists
    {
        return Rule::exists('health_units', 'id')->whereNull('deleted_at');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.string' => 'O nome deve ser um texto.',
            'name.max' => 'O nome deve ter no máximo 150 caracteres.',
            'cpf.required' => 'O CPF é obrigatório.',
            'cpf.digits' => 'O CPF deve conter exatamente 11 dígitos.',
            'cpf.unique' => 'Este CPF já está cadastrado.',
            'birth_date.required' => 'A data de nascimento é obrigatória.',
            'birth_date.date' => 'Informe uma data de nascimento válida.',
            'birth_date.before_or_equal' => 'A data de nascimento não pode ser futura.',
            'phone.digits_between' => 'O telefone deve conter 10 ou 11 dígitos.',
            'health_unit_id.required' => 'Selecione uma unidade de saúde.',
            'health_unit_id.integer' => 'Selecione uma unidade de saúde válida.',
            'health_unit_id.exists' => 'Selecione uma unidade de saúde válida.',
        ];
    }

    /**
     * Mantém apenas os dígitos do valor informado.
     */
    protected function digitsOnly(mixed $value): string
    {
        return is_scalar($value) ? preg_replace('/\D+/', '', (string) $value) : '';
    }
}
