<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsientoResource extends JsonResource
{
    /**
     * Representa la posición y características físicas
     * de un asiento dentro del vehículo.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'numero' => $this->numero,

            'piso' => $this->piso,
            'fila' => $this->fila,
            'columna' => $this->columna,

            'tipo' => $this->tipo->value,
            'caracteristicas' => $this->caracteristicas,

            'activo' => $this->activo,
        ];
    }
}
