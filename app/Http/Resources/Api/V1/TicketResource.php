<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Relaciones
        |--------------------------------------------------------------------------
        */

        $pasajero =
            $this->pasajero;


        $ocupacion =
            $pasajero
                ?->ocupacionAsiento;


        $asiento =
            $ocupacion
                ?->asientoViaje;


        $origen =
            $ocupacion
                ?->puntoOrigen;


        $destino =
            $ocupacion
                ?->puntoDestino;


        $reserva =
            $this->reserva;


        $viaje =
            $reserva
                ?->viaje;


        $pago =
            $reserva
                ?->pagoConfirmacion;


        /*
        |--------------------------------------------------------------------------
        | Información calculada
        |--------------------------------------------------------------------------
        */

        $horario =
            $this
                ->resource
                ->getAttribute(
                    'horario_segmento_calculado'
                )
            ?? [];


        return [

            /*
            |--------------------------------------------------------------------------
            | Ticket
            |--------------------------------------------------------------------------
            */

            'codigo' =>
                $this->codigo,


            /*
            |--------------------------------------------------------------------------
            | QR
            |--------------------------------------------------------------------------
            |
            | Por ahora devolvemos únicamente el token.
            |
            | La representación SVG/PNG se implementará
            | posteriormente.
            |
            */

            'qr' => [

                'token' =>
                    $this
                        ->resource
                        ->getAttribute(
                            'qr_token'
                        ),
            ],


            'estado' =>
                $this->estado->value,


            'emitido_en' =>
                $this
                    ->emitido_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),


            'validado_en' =>
                $this
                    ->validado_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),


            'anulado_en' =>
                $this
                    ->anulado_en
                    ?->format(
                        'Y-m-d H:i:s'
                    ),


            /*
            |--------------------------------------------------------------------------
            | Pasajero
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | Reserva
            |--------------------------------------------------------------------------
            */

            'reserva' => [

                'codigo' =>
                    $reserva
                        ?->codigo,


                'estado' =>
                    $reserva
                        ?->estado
                        ?->value,
            ],


            /*
            |--------------------------------------------------------------------------
            | Viaje
            |--------------------------------------------------------------------------
            */

            'viaje' => [

                'codigo' =>
                    $viaje
                        ?->codigo,


                'fecha' =>
                    $viaje
                        ?->salida_programada
                        ?->format(
                            'Y-m-d'
                        ),


                'salida_programada' =>
                    $viaje
                        ?->salida_programada
                        ?->format(
                            'Y-m-d H:i:s'
                        ),
            ],


            /*
            |--------------------------------------------------------------------------
            | Asiento
            |--------------------------------------------------------------------------
            */

            'asiento' => [

                'codigo' =>
                    $asiento
                        ?->codigo,


                'numero' =>
                    $asiento
                        ?->numero,


                'piso' =>
                    $asiento
                        ?->piso,


                'tipo' =>
                    $asiento
                        ?->tipo
                        ?->value,
            ],


            /*
            |--------------------------------------------------------------------------
            | Embarque
            |--------------------------------------------------------------------------
            */

            'embarque' => [

                'punto' => [

                    'nombre' =>
                        $origen
                            ?->nombre,


                    'distrito' =>
                        $origen
                            ?->distrito,


                    'direccion' =>
                        $origen
                            ?->direccion,
                ],


                'hora_embarque' =>
                    $horario[
                        'hora_embarque'
                    ]
                    ?? null,
            ],


            /*
            |--------------------------------------------------------------------------
            | Destino
            |--------------------------------------------------------------------------
            */

            'destino' => [

                'punto' => [

                    'nombre' =>
                        $destino
                            ?->nombre,


                    'distrito' =>
                        $destino
                            ?->distrito,


                    'direccion' =>
                        $destino
                            ?->direccion,
                ],


                'hora_llegada' =>
                    $horario[
                        'hora_llegada'
                    ]
                    ?? null,
            ],


            /*
            |--------------------------------------------------------------------------
            | Pago que confirmó la reserva
            |--------------------------------------------------------------------------
            |
            | Puede ser NULL cuando la reserva fue
            | confirmada por un flujo administrativo.
            |
            */

            'pago' =>
                $pago
                    ? [

                        'proveedor' =>
                            $pago
                                ->proveedor
                                ->value,


                        'estado' =>
                            $pago
                                ->estado
                                ->value,


                        'monto' =>
                            $pago
                                ->monto,


                        'moneda' =>
                            $pago
                                ->moneda,
                    ]

                    : null,
        ];
    }
}
