<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidacionTicketResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        $pasajero =
            $this->pasajero;


        $ocupacion =
            $pasajero
                ?->ocupacionAsiento;


        $asiento =
            $ocupacion
                ?->asientoViaje;


        $reserva =
            $this->reserva;


        $viaje =
            $reserva
                ?->viaje;


        return [

            'codigo' =>
                $this->codigo,


            'estado' =>
                $this->estado->value,


            'validado_en' =>
                $this
                    ->validado_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),


            'pasajero' => [

                'nombres' =>
                    $pasajero
                        ?->nombres,

                'apellidos' =>
                    $pasajero
                        ?->apellidos,

                'tipo_documento' =>
                    $pasajero
                        ?->tipo_documento,

                'numero_documento' =>
                    $pasajero
                        ?->numero_documento,
            ],


            'viaje' => [

                'codigo' =>
                    $viaje
                        ?->codigo,
            ],


            'asiento' => [

                'codigo' =>
                    $asiento
                        ?->codigo,
            ],


            'validado_por' => [

                'nombres' =>
                    $this
                        ->validadoPor
                        ?->nombres,

                'apellidos' =>
                    $this
                        ->validadoPor
                        ?->apellidos,
            ],
        ];
    }
}
