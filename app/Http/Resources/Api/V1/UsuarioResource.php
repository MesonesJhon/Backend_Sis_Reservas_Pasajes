<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    /**
     * Define la representación pública de un usuario.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'correo' => $this->correo,
            'activo' => $this->activo,

            'roles' => $this->roles->pluck('nombre'),

            'permisos' => $this->roles
                ->flatMap(fn ($rol) => $rol->permisos)
                ->pluck('nombre')
                ->unique()
                ->values(),
        ];
    }
}
