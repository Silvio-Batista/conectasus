<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Patient
 */
class PatientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'cpf' => $this->cpf,
            'birth_date' => $this->birth_date?->toDateString(),
            'phone' => $this->phone,
            'health_unit_id' => $this->health_unit_id,
            // Só é incluído quando o relacionamento foi carregado (eager loading).
            'health_unit' => $this->whenLoaded(
                'healthUnit',
                fn () => $this->healthUnit ? HealthUnitResource::make($this->healthUnit) : null
            ),
        ];
    }
}
