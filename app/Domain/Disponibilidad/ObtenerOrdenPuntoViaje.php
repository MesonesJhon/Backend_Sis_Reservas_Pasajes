<?php

namespace App\Domain\Disponibilidad;

use App\Models\Viaje;
use Exception;


/**
 * Obtiene la posición de un punto
 * dentro del recorrido congelado.
 */
class ObtenerOrdenPuntoViaje
{


    public function obtener(
        Viaje $viaje,
        int $puntoId
    ): int {


        $punto = $viaje
            ->puntosViaje()
            ->where(
                'punto_id',
                $puntoId
            )
            ->first();



        if (!$punto) {

            throw new \DomainException(
                'El punto no pertenece al viaje.'
            );

        }



        return $punto->orden;

    }

}
