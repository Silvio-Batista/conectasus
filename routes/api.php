<?php

use App\Http\Controllers\Api\HealthUnitController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Route;

// CRUD completo de pacientes: index, store, show, update (PUT/PATCH) e destroy.
Route::apiResource('patients', PatientController::class);

// Unidades de saúde são somente leitura na API.
Route::apiResource('health-units', HealthUnitController::class)->only('index');
