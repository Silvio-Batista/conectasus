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
            'name' => $this->name,
            'cpf' => $this->cpf,
            'birth_date' => $this->birth_date?->toDateString(),
            'phone' => $this->phone,
            'health_unit_id' => $this->health_unit_id,
            'health_unit' => HealthUnitResource::make($this->whenLoaded('healthUnit')),
        ];
    }
}
