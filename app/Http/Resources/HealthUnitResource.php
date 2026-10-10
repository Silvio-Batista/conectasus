<?php

namespace App\Http\Resources;

use App\Models\HealthUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HealthUnit
 */
class HealthUnitResource extends JsonResource
{
    /**
     * Expõe apenas os campos utilizados pelo frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
        ];
    }
}
