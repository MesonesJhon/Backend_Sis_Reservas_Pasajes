<?php

namespace App\Domain\Viajes;

use App\Models\Viaje;
use Exception;


/**
 * Obtiene el precio exacto
 * del tramo solicitado.
 */
class ObtenerTarifaSegmento
{


    public function obtener(
        Viaje $viaje,
        int $origenId,
        int $destinoId
    )
    {


        $tarifa = $viaje
            ->tarifas()
            ->where([
                'punto_origen_id'
                    => $origenId,

                'punto_destino_id'
                    => $destinoId,

                'activo'
                    => true,
            ])
            ->first();



        if (!$tarifa) {

            throw new Exception(
                'No existe tarifa para este segmento.'
            );
        }



        return $tarifa;

    }

}
