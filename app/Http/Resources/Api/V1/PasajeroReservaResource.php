<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PasajeroReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' =>
                $this->id,


            'tipo_documento' =>
                $this->tipo_documento,


            'numero_documento' =>
                $this->numero_documento,


            'nombres' =>
                $this->nombres,


            'apellidos' =>
                $this->apellidos,


            'telefono' =>
                $this->telefono,


            'correo' =>
                $this->correo,


            /*
             * Precio congelado para este pasajero.
             */
            'precio' =>
                $this->precio,


            /*
             * La ocupación determina asiento
             * y segmento.
             */
            'ocupacion' =>
                $this->whenLoaded(
                    'ocupacionAsiento',
                    function (): array {

                        return [

                            'id' =>
                                $this
                                    ->ocupacionAsiento
                                    ->id,


                            'asiento_viaje_id' =>
                                $this
                                    ->ocupacionAsiento
                                    ->asiento_viaje_id,


                            'asiento_codigo' =>
                                $this
                                    ->ocupacionAsiento
                                    ->asientoViaje
                                    ?->codigo,


                            'punto_origen_id' =>
                                $this
                                    ->ocupacionAsiento
                                    ->punto_origen_id,


                            'punto_origen' =>
                                $this
                                    ->ocupacionAsiento
                                    ->puntoOrigen
                                    ?->nombre,


                            'punto_destino_id' =>
                                $this
                                    ->ocupacionAsiento
                                    ->punto_destino_id,


                            'punto_destino' =>
                                $this
                                    ->ocupacionAsiento
                                    ->puntoDestino
                                    ?->nombre,
                        ];
                    }
                ),
        ];
    }
}
