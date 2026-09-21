<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuntoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'nombre' => $this->nombre,
            'tipo' => $this->tipo->value,

            'ubicacion' => [
                'departamento' => $this->departamento,
                'provincia' => $this->provincia,
                'distrito' => $this->distrito,
                'direccion' => $this->direccion,
                'referencia' => $this->referencia,
            ],

            'coordenadas' => [
                'latitud' => $this->latitud,
                'longitud' => $this->longitud,
            ],

            'activo' => $this->activo,

            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }
}
