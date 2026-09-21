<?php

namespace App\Actions\Viajes;

use App\Models\Viaje;

class ConsolidarRecorridoViaje
{
    /**
     * Copia el recorrido actual de la ruta al viaje.
     *
     * A partir de este momento el viaje conserva su
     * propio recorrido histórico independientemente
     * de cambios futuros realizados sobre la ruta.
     */
    public function ejecutar(Viaje $viaje): void
    {
        $puntosRuta = $viaje->ruta
            ->puntosRuta()
            ->orderBy('orden')
            ->get();

        $viaje->puntosViaje()->delete();

        foreach ($puntosRuta as $puntoRuta) {
            $viaje->puntosViaje()->create([
                'punto_id' => $puntoRuta->punto_id,
                'orden' => $puntoRuta->orden,

                'permite_embarque' =>
                    $puntoRuta->permite_embarque,

                'permite_desembarque' =>
                    $puntoRuta->permite_desembarque,

                'minutos_desde_origen' =>
                    $puntoRuta->minutos_desde_origen,
            ]);
        }
    }
}
