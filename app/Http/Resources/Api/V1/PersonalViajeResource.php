<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalViajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'usuario' => $this->whenLoaded(
                'usuario',
                fn () => [
                    'id' => $this->usuario->id,
                    'nombres' => $this->usuario->nombres,
                    'apellidos' => $this->usuario->apellidos,
                    'correo' => $this->usuario->correo,
                ]
            ),

            'funcion' => $this->funcion->value,
        ];
    }
}
