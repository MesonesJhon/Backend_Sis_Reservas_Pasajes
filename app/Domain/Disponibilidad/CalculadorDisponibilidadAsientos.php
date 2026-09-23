<?php

namespace App\Domain\Disponibilidad;

use App\Enums\EstadoOcupacionAsiento;
use App\Models\Viaje;


/**
 * Calcula qué asientos pueden utilizarse
 * para un segmento determinado.
 */
class CalculadorDisponibilidadAsientos
{


    public function __construct(
        private readonly ValidadorSuperposicionSegmentos $validador,
        private readonly ObtenerOrdenPuntoViaje $ordenPunto
    ) {
    }



    /**
     * Devuelve el inventario con disponibilidad.
     */
    public function calcular(
        Viaje $viaje,
        int $origenId,
        int $destinoId
    ) {


        $ordenOrigen = $this->ordenPunto
            ->obtener(
                $viaje,
                $origenId
            );



        $ordenDestino = $this->ordenPunto
            ->obtener(
                $viaje,
                $destinoId
            );



        return $viaje
            ->asientosViaje()
            ->where('activo', true)
            ->get()
            ->map(function ($asiento) use (
                $viaje,
                $ordenOrigen,
                $ordenDestino
            ) {


                $ocupado = $viaje
                    ->ocupacionesAsientos()
                    ->where(
                        'asiento_viaje_id',
                        $asiento->id
                    )
                    ->where(function ($consulta) {

                        /*
                        |--------------------------------------------------------------------------
                        | CONFIRMADO
                        |--------------------------------------------------------------------------
                        |
                        | Siempre ocupa el asiento.
                        |
                        */

                        $consulta->where(
                            'estado',
                            EstadoOcupacionAsiento::CONFIRMADO->value
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | RESERVADO
                        |--------------------------------------------------------------------------
                        |
                        | Caso RF-06 antiguo:
                        |
                        | expira_en = NULL
                        | -> sigue ocupando.
                        |
                        | Caso RF-07:
                        |
                        | expira_en futura
                        | -> sigue ocupando.
                        |
                        | expira_en vencida
                        | -> deja de ocupar inmediatamente.
                        |
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
                        |--------------------------------------------------------------------------
                        | BLOQUEADO
                        |--------------------------------------------------------------------------
                        |
                        | Solamente ocupa mientras siga vigente.
                        |
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
                    })
                    ->get()
                    ->contains(function ($ocupacion) use (
                        $viaje,
                        $ordenOrigen,
                        $ordenDestino
                    ) {


                        $inicio =
                            $this->ordenPunto
                                ->obtener(
                                    $viaje,
                                    $ocupacion
                                        ->punto_origen_id
                                );


                        $fin =
                            $this->ordenPunto
                                ->obtener(
                                    $viaje,
                                    $ocupacion
                                        ->punto_destino_id
                                );


                        return $this->validador
                            ->existeSuperposicion(
                                $inicio,
                                $fin,
                                $ordenOrigen,
                                $ordenDestino
                            );

                    });



                return [

                    'id'
                        => $asiento->id,


                    'codigo'
                        => $asiento->codigo,


                    'piso'
                        => $asiento->piso,


                    'fila'
                        => $asiento->fila,


                    'columna'
                        => $asiento->columna,


                    'tipo'
                        => $asiento->tipo,


                    'disponible'
                        => !$ocupado,

                ];

            });


    }


}
