<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Identificación
            |--------------------------------------------------------------------------
            */

            'id' =>
                $this->id,

            'codigo' =>
                $this->codigo,


            /*
            |--------------------------------------------------------------------------
            | Viaje
            |--------------------------------------------------------------------------
            */

            'viaje_id' =>
                $this->viaje_id,


            /*
            |--------------------------------------------------------------------------
            | Propiedad / auditoría
            |--------------------------------------------------------------------------
            */

            'cliente_usuario_id' =>
                $this->cliente_usuario_id,

            'creado_por_usuario_id' =>
                $this->creado_por_usuario_id,


            /*
            |--------------------------------------------------------------------------
            | Estado comercial
            |--------------------------------------------------------------------------
            */

            'estado' =>
                $this->estado->value,


            /*
            |--------------------------------------------------------------------------
            | Contacto
            |--------------------------------------------------------------------------
            */

            'correo_contacto' =>
                $this->correo_contacto,

            'telefono_contacto' =>
                $this->telefono_contacto,


            /*
            |--------------------------------------------------------------------------
            | Información económica
            |--------------------------------------------------------------------------
            */

            'total' =>
                $this->total,


            /*
            |--------------------------------------------------------------------------
            | Ciclo de vida
            |--------------------------------------------------------------------------
            */

            'expira_en' =>
                $this->expira_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'confirmada_en' =>
                $this->confirmada_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'cancelada_en' =>
                $this->cancelada_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'expirada_en' =>
                $this->expirada_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'motivo_cancelacion' =>
                $this->motivo_cancelacion,


            /*
            |--------------------------------------------------------------------------
            | Pasajeros
            |--------------------------------------------------------------------------
            */

            'pasajeros' =>
                PasajeroReservaResource::collection(
                    $this->whenLoaded(
                        'pasajeros'
                    )
                ),


            /*
            |--------------------------------------------------------------------------
            | Auditoría temporal
            |--------------------------------------------------------------------------
            */

            'created_at' =>
                $this->created_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),

            'updated_at' =>
                $this->updated_at
                    ?->format(
                        'Y-m-d H:i:s'
                    ),
        ];
    }
}
