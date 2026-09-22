<?php

namespace App\Http\Resources\Api\V1;


use Illuminate\Http\Request;

use Illuminate\Http\Resources\Json\JsonResource;



/**
 * Formatea la respuesta de disponibilidad
 * de cada asiento.
 */
class AsientoDisponibleResource extends JsonResource
{


    public function toArray(Request $request): array
    {

        return [

            'id' => $this['id'],


            'codigo' => $this['codigo'],


            'piso' => $this['piso'],


            'fila' => $this['fila'],


            'columna' => $this['columna'],


            'tipo' => $this['tipo']->value ?? $this['tipo'],


            'disponible' => $this['disponible'],

        ];

    }

}
