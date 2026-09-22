<?php

namespace App\Domain\Disponibilidad;


/**
 * Determina si dos segmentos de viaje
 * ocupan el mismo tramo del recorrido.
 *
 * Trabaja con el orden de los puntos,
 * no con IDs.
 *
 * Ejemplo:
 *
 * Chiclayo(1) -> Olmos(3)
 *
 * Lambayeque(2) -> Chota(4)
 *
 * Resultado:
 * true
 *
 */
class ValidadorSuperposicionSegmentos
{


    /**
     * Comprueba si existe cruce
     * entre dos segmentos.
     *
     * @param int $inicioExistente
     * @param int $finExistente
     * @param int $inicioNuevo
     * @param int $finNuevo
     *
     * @return bool
     */
    public function existeSuperposicion(
        int $inicioExistente,
        int $finExistente,
        int $inicioNuevo,
        int $finNuevo
    ): bool {


        return
            $inicioExistente < $finNuevo
            &&
            $finExistente > $inicioNuevo;

    }


}
