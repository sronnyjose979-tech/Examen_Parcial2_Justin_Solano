<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SOLUCIÓN [B4] Contrato explícito: si mañana se renombra una columna,
 * la respuesta de la API no cambia. costo_interno y las marcas de tiempo no salen.
 */
class EquipoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'codigo'            => $this->codigo,
            'nombre'            => $this->nombre,
            'estado'            => $this->estado,
            'fecha_adquisicion' => $this->fecha_adquisicion?->toDateString(),
            'categoria'         => new CategoriaResource($this->whenLoaded('categoria')),
        ];
    }
}
