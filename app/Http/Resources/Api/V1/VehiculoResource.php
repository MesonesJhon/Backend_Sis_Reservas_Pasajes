<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehiculoResource extends JsonResource
{
    /**
     * Define la representación pública de un vehículo
     * dentro de la API administrativa.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'tipo_vehiculo' => new TipoVehiculoResource(
                $this->whenLoaded('tipoVehiculo')
            ),

            'placa' => $this->placa,
            'codigo_interno' => $this->codigo_interno,
            'marca' => $this->marca,
            'modelo' => $this->modelo,

            'capacidad' => $this->capacidad,
            'numero_pisos' => $this->numero_pisos,

            'caracteristicas' => $this->caracteristicas,

            'estado' => $this->estado->value,
            'activo' => $this->activo,
            'puede_operar' => $this->puedeOperar(),

            'asientos' => AsientoResource::collection(
                $this->whenLoaded('asientos')
            ),

            'asientos_configurados' => $this->whenCounted(
                'asientos'
            ),

            'creado_en' => $this->created_at,
            'actualizado_en' => $this->updated_at,
        ];
    }
}
