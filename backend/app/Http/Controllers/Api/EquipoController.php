<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarEquipoRequest;
use App\Http\Resources\EquipoResource;
use App\Models\Equipo;
use App\Services\EquipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * SOLUCIÓN [B3] Controlador delgado: recibe, delega y responde.
 */
class EquipoController extends Controller
{
    public function __construct(private EquipoService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        // El servidor impone el techo: ?per_page=999999 no agota la memoria
        $porPagina = max(1, min($request->integer('per_page', 10), 50));

        return EquipoResource::collection(
            $this->service->listar($request->query('q'), $request->query('estado'), $porPagina)
        );
    }

    public function show(Equipo $equipo): EquipoResource
    {
        return new EquipoResource($equipo->load('categoria'));
    }

    public function store(GuardarEquipoRequest $request): JsonResponse
    {
        $equipo = $this->service->crear($request->validated());

        return (new EquipoResource($equipo))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('equipos.show', $equipo));
    }

    public function update(GuardarEquipoRequest $request, Equipo $equipo): EquipoResource
    {
        return new EquipoResource($this->service->actualizar($equipo, $request->validated()));
    }

    public function destroy(Equipo $equipo): Response
    {
        $this->service->eliminar($equipo);

        return response()->noContent();
    }

    public function prestar(Equipo $equipo): EquipoResource
    {
        return new EquipoResource($this->service->prestar($equipo));
    }
}
