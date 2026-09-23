<?php

namespace App\Domain\Asientos;


use App\Enums\EstadoOcupacionAsiento;
use App\Models\Viaje;
use App\Exceptions\OperacionAsientoInvalidaException;
use Carbon\Carbon;

use App\Domain\Disponibilidad\ObtenerOrdenPuntoViaje;
use App\Domain\Disponibilidad\ValidadorSuperposicionSegmentos;


class ValidadorBloqueoAsiento
{

    public function __construct(
        private readonly ObtenerOrdenPuntoViaje $ordenPunto,
        private readonly ValidadorSuperposicionSegmentos $superposicion
    ) {

    }


    /**
     * Valida si un asiento puede bloquearse.
     */
    public function validar(
        Viaje $viaje,
        int $asientoViajeId,
        int $origenId,
        int $destinoId
    ): void {


        /*
        |--------------------------------------------------------------------------
        | 1. Validar que el asiento pertenece al viaje
        |--------------------------------------------------------------------------
        */


        $existeAsiento =
            $viaje
                ->asientosViaje()
                ->where(
                    'id',
                    $asientoViajeId
                )
                ->exists();


        if (! $existeAsiento) {

            throw new OperacionAsientoInvalidaException(
                'El asiento no pertenece al viaje.'
            );

        }



        /*
        |--------------------------------------------------------------------------
        | 2. Limpiar bloqueos expirados
        |--------------------------------------------------------------------------
        */


        $viaje
            ->ocupacionesAsientos()
            ->where(
                'asiento_viaje_id',
                $asientoViajeId
            )
            ->where(
                'estado',
                EstadoOcupacionAsiento::BLOQUEADO
            )
            ->where(
                'expira_en',
                '<=',
                Carbon::now()
            )
            ->update([
                'estado' =>
                    EstadoOcupacionAsiento::LIBERADO,

                'expira_en' =>
                    null,
            ]);





        /*
        |--------------------------------------------------------------------------
        | 3. Buscar ocupaciones activas
        |--------------------------------------------------------------------------
        */


       $ordenOrigenNuevo =
        $this->ordenPunto->obtener(
            $viaje,
            $origenId
        );


        $ordenDestinoNuevo =
        $this->ordenPunto->obtener(
            $viaje,
            $destinoId
        );


        $ocupaciones =
        $viaje
            ->ocupacionesAsientos()

            ->where(
                'asiento_viaje_id',
                $asientoViajeId
            )

            ->where(
                function ($consulta) {

                    /*
                    * CONFIRMADO siempre ocupa.
                    */
                    $consulta->where(
                        'estado',
                        EstadoOcupacionAsiento::CONFIRMADO->value
                    );


                    /*
                    * RESERVADO solo ocupa mientras
                    * siga vigente.
                    *
                    * NULL mantiene compatibilidad
                    * con reservas creadas directamente
                    * durante RF-06.
                    */
                    $consulta->orWhere(
                        function ($reservadas) {

                            $reservadas
                                ->where(
                                    'estado',
                                    EstadoOcupacionAsiento::RESERVADO->value
                                )

                                ->where(
                                    function ($vigencia) {

                                        $vigencia
                                            ->whereNull(
                                                'expira_en'
                                            )

                                            ->orWhere(
                                                'expira_en',
                                                '>',
                                                now()
                                            );
                                    }
                                );
                        }
                    );


                    /*
                    * BLOQUEADO solamente ocupa
                    * si todavía está vigente.
                    */
                    $consulta->orWhere(
                        function ($bloqueos) {

                            $bloqueos
                                ->where(
                                    'estado',
                                    EstadoOcupacionAsiento::BLOQUEADO->value
                                )

                                ->where(
                                    function ($vigencia) {

                                        $vigencia
                                            ->whereNull(
                                                'expira_en'
                                            )

                                            ->orWhere(
                                                'expira_en',
                                                '>',
                                                now()
                                            );
                                    }
                                );
                        }
                    );
                }
            )

            ->get();



        foreach ($ocupaciones as $ocupacion) {


            $ordenOrigenExistente =
                $this->ordenPunto->obtener(
                    $viaje,
                    $ocupacion->punto_origen_id
                );


            $ordenDestinoExistente =
                $this->ordenPunto->obtener(
                    $viaje,
                    $ocupacion->punto_destino_id
                );


            if (
                $this->superposicion->existeSuperposicion(
                    $ordenOrigenExistente,
                    $ordenDestinoExistente,
                    $ordenOrigenNuevo,
                    $ordenDestinoNuevo
                )
            ) {

                throw new OperacionAsientoInvalidaException(
                    'El asiento no se encuentra disponible para este tramo.'
                );

            }

        }


    }

}
