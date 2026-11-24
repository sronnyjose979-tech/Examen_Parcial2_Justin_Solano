<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Equipo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * SOLUCIÓN [B2] Capa de negocio de equipos. No conoce HTTP.
 */
class EquipoService
{
    public function listar(?string $q, ?string $estado, int $porPagina): LengthAwarePaginator
    {
        return Equipo::query()
            ->with('categoria')                                   // evita N+1
            ->when($q, fn ($consulta, $texto) => $consulta->where(
                fn ($w) => $w->where('codigo', 'like', "%{$texto}%")
                             ->orWhere('nombre', 'like', "%{$texto}%")
            ))
            ->when($estado, fn ($consulta, $valor) => $consulta->where('estado', $valor))
            ->orderBy('codigo')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function crear(array $datos): Equipo
    {
        return Equipo::create($datos)->load('categoria');
    }

    public function actualizar(Equipo $equipo, array $datos): Equipo
    {
        $equipo->update($datos);

        return $equipo->load('categoria');
    }

    public function eliminar(Equipo $equipo): void
    {
        // R1
        if ($equipo->estado === 'prestado') {
            throw new ReglaNegocioException('No se puede eliminar un equipo prestado.');
        }

        $equipo->delete();
    }

    public function prestar(Equipo $equipo): Equipo
    {
        // R2
        match ($equipo->estado) {
            'mantenimiento' => throw new ReglaNegocioException('No se puede prestar un equipo en mantenimiento.'),
            'prestado'      => throw new ReglaNegocioException('El equipo ya se encuentra prestado.'),
            default         => null,
        };

        $equipo->update(['estado' => 'prestado']);

        return $equipo->load('categoria');
    }
}
