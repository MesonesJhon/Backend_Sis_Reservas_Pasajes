<?php

namespace App\Domain\Viajes;

use App\Models\Viaje;
use Carbon\Carbon;


/**
 * Calcula horarios reales del segmento.
 */
class CalculadorHorarioSegmento
{


    public function calcular(
        Viaje $viaje,
        int $origenId,
        int $destinoId
    ): array {


        $origen = $viaje
            ->puntosViaje()
            ->where(
                'punto_id',
                $origenId
            )
            ->firstOrFail();



        $destino = $viaje
            ->puntosViaje()
            ->where(
                'punto_id',
                $destinoId
            )
            ->firstOrFail();



        $salidaViaje = Carbon::parse(
            $viaje->salida_programada
        );



        $horaSalida = $salidaViaje
            ->copy()
            ->addMinutes(
                $origen->minutos_desde_origen
            );



        $horaLlegada = $salidaViaje
            ->copy()
            ->addMinutes(
                $destino->minutos_desde_origen
            );



        return [

            'hora_embarque'
                => $horaSalida
                    ->format('H:i'),


            'hora_llegada'
                => $horaLlegada
                    ->format('H:i'),


            'duracion_minutos'
                =>
                $destino->minutos_desde_origen
                -
                $origen->minutos_desde_origen,
        ];

    }

}
