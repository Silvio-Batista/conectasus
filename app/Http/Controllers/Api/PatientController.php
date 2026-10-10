<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PatientController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Lista pacientes ordenados pelo nome, com busca parcial e paginação.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $patients = Patient::query()
            ->with('healthUnit')
            ->search($filters['search'] ?? null)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return PatientResource::collection($patients);
    }

    /**
     * Cadastra um paciente.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = Patient::create($request->validated());

        return PatientResource::make($patient->load('healthUnit'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Exibe um paciente. Retorna 404 automaticamente quando o ID não existe.
     */
    public function show(Patient $patient): PatientResource
    {
        return PatientResource::make($patient->load('healthUnit'));
    }

    /**
     * Atualiza um paciente (PUT completo ou PATCH parcial).
     */
    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($request->validated());

        return PatientResource::make($patient->load('healthUnit'));
    }

    /**
     * Exclui um paciente.
     */
    public function destroy(Patient $patient): Response
    {
        $patient->delete();

        return response()->noContent();
    }
}
