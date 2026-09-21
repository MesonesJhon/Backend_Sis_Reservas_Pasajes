<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Define la representación de un rol que será
 * expuesta mediante la API.
 */
class RolResource extends JsonResource
{
    /**
     * Transforma el modelo Rol en una estructura JSON segura
     * y consistente para los clientes de la API.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,

            'permisos' => $this->whenLoaded(
                'permisos',
                fn () => $this->permisos
                    ->pluck('nombre')
                    ->values()
            ),
        ];
    }
}
