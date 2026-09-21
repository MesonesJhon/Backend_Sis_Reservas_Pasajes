<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TarifaViajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'punto_origen' => $this->whenLoaded(
                'puntoOrigen',
                fn () => [
                    'id' => $this->puntoOrigen->id,
                    'nombre' => $this->puntoOrigen->nombre,
                ]
            ),

            'punto_destino' => $this->whenLoaded(
                'puntoDestino',
                fn () => [
                    'id' => $this->puntoDestino->id,
                    'nombre' => $this->puntoDestino->nombre,
                ]
            ),

            'precio' => $this->precio,
            'activo' => $this->activo,
        ];
    }
}
