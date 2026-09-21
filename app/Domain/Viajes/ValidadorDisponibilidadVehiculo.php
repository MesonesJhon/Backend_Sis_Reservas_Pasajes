<?php

namespace App\Domain\Viajes;

use App\Enums\EstadoViaje;
use App\Models\Viaje;

class ValidadorDisponibilidadVehiculo
{
    /**
     * Determina si el vehículo está libre durante todo
     * el intervalo horario del viaje.
     *
     * Dos intervalos se superponen cuando:
     *
     * inicioExistente < finNuevo
     * &&
     * finExistente > inicioNuevo
     */
    public function estaDisponible(Viaje $viaje): bool
    {
        return ! Viaje::query()
            ->where('id', '!=', $viaje->id)
            ->where('vehiculo_id', $viaje->vehiculo_id)
            ->whereIn('estado', [
                EstadoViaje::PROGRAMADO->value,
                EstadoViaje::EMBARCANDO->value,
                EstadoViaje::EN_RUTA->value,
            ])
            ->where(
                'salida_programada',
                '<',
                $viaje->llegada_estimada
            )
            ->where(
                'llegada_estimada',
                '>',
                $viaje->salida_programada
            )
            ->exists();
    }
}
