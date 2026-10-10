<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HealthUnitResource;
use App\Models\HealthUnit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HealthUnitController extends Controller
{
    /**
     * Lista todas as unidades de saúde ordenadas pelo nome.
     */
    public function index(): AnonymousResourceCollection
    {
        $units = HealthUnit::query()->orderBy('name')->get();

        return HealthUnitResource::collection($units);
    }
}
