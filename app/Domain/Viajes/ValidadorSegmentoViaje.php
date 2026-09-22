<?php

namespace App\Domain\Viajes;

use App\Models\Viaje;
use Exception;


/**
 * Valida que un viaje pueda vender
 * un segmento específico.
 *
 * Ejemplo:
 *
 * Chiclayo → Chota
 *
 * El origen debe estar antes del destino
 * dentro del recorrido del viaje.
 */
class ValidadorSegmentoViaje
{


    /**
     * Valida el segmento solicitado.
     *
     * @throws Exception
     */
    public function validar(
        Viaje $viaje,
        int $origenId,
        int $destinoId
    ): bool {


        /*
         * Obtenemos el recorrido congelado
         * del viaje.
         *
         * NO usamos puntos_ruta.
         */
        $puntos = $viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();



        $origen = $puntos
            ->where(
                'punto_id',
                $origenId
            )
            ->first();



        $destino = $puntos
            ->where(
                'punto_id',
                $destinoId
            )
            ->first();



        if (!$origen) {

            throw new Exception(
                'El origen no pertenece al recorrido.'
            );
        }



        if (!$destino) {

            throw new Exception(
                'El destino no pertenece al recorrido.'
            );
        }



        /*
         * El viaje solamente puede venderse
         * en sentido de recorrido.
         *
         * Ejemplo:
         *
         * Chiclayo -> Chota ✅
         *
         * Chota -> Chiclayo ❌
         */
        if (
            $origen->orden >= $destino->orden
        ) {

            throw new Exception(
                'El origen debe estar antes que el destino.'
            );
        }



        /*
         * Validamos reglas comerciales.
         */
        if (!$origen->permite_embarque) {

            throw new Exception(
                'El origen no permite embarque.'
            );
        }



        if (!$destino->permite_desembarque) {

            throw new Exception(
                'El destino no permite desembarque.'
            );
        }



        return true;
    }

}
