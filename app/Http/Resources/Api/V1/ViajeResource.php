<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViajeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,

            'ruta' => $this->whenLoaded(
                'ruta',
                fn () => [
                    'id' => $this->ruta->id,
                    'codigo' => $this->ruta->codigo,
                    'nombre' => $this->ruta->nombre,
                ]
            ),

            'vehiculo' => $this->whenLoaded(
                'vehiculo',
                fn () => [
                    'id' => $this->vehiculo->id,
                    'placa' => $this->vehiculo->placa,
                    'codigo_interno' =>
                        $this->vehiculo->codigo_interno,
                ]
            ),

            'salida_programada' =>
                $this->salida_programada
                    ?->format('Y-m-d H:i:s'),

            'llegada_estimada' =>
                $this->llegada_estimada
                    ?->format('Y-m-d H:i:s'),

            'estado' => $this->estado->value,

            'observaciones' => $this->observaciones,

            'recorrido' => $this->when(
                $this->relationLoaded('puntosViaje'),
                fn () => PuntoViajeResource::collection(
                    $this->puntosViaje
                        ->sortBy('orden')
                        ->values()
                )
            ),

            'personal' => $this->when(
                $this->relationLoaded('personal'),
                fn () => PersonalViajeResource::collection(
                    $this->personal
                )
            ),

            'tarifas' => $this->when(
                $this->relationLoaded('tarifas'),
                fn () => TarifaViajeResource::collection(
                    $this->tarifas
                )
            ),

            'created_at' => $this->created_at
                ?->format('Y-m-d H:i:s'),

            'updated_at' => $this->updated_at
                ?->format('Y-m-d H:i:s'),
        ];
    }
}
