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
                    ->whereIn(
                        'estado',
                        [
                            EstadoOcupacionAsiento::BLOQUEADO,
                            EstadoOcupacionAsiento::RESERVADO,
                            EstadoOcupacionAsiento::CONFIRMADO,
                        ]
                    )
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
