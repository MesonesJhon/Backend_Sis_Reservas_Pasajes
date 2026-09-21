<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RutaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,

            'duracion_estimada_minutos' =>
                $this->duracion_estimada_minutos,

            'activo' => $this->activo,

            'origen' => $this->when(
                $this->relationLoaded('puntosRuta')
                && $this->puntosRuta->isNotEmpty(),
                function () {
                    $origen = $this->puntosRuta
                        ->sortBy('orden')
                        ->first();

                    return new PuntoRutaResource($origen);
                }
            ),

            'destino' => $this->when(
                $this->relationLoaded('puntosRuta')
                && $this->puntosRuta->isNotEmpty(),
                function () {
                    $destino = $this->puntosRuta
                        ->sortByDesc('orden')
                        ->first();

                    return new PuntoRutaResource($destino);
                }
            ),

            'recorrido' => PuntoRutaResource::collection(
                $this->whenLoaded('puntosRuta')
            ),

            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }
}
