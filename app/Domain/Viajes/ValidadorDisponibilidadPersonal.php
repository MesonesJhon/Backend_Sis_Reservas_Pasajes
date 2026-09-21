<?php

namespace App\Domain\Viajes;

use App\Enums\EstadoViaje;
use App\Models\Viaje;

class ValidadorDisponibilidadPersonal
{
    /**
     * Determina si un usuario está libre durante
     * todo el horario del viaje.
     */
    public function estaDisponible(
        Viaje $viaje,
        int $usuarioId
    ): bool {
        return ! Viaje::query()
            ->where('viajes.id', '!=', $viaje->id)
            ->whereIn('viajes.estado', [
                EstadoViaje::PROGRAMADO->value,
                EstadoViaje::EMBARCANDO->value,
                EstadoViaje::EN_RUTA->value,
            ])
            ->where(
                'viajes.salida_programada',
                '<',
                $viaje->llegada_estimada
            )
            ->where(
                'viajes.llegada_estimada',
                '>',
                $viaje->salida_programada
            )
            ->whereHas(
                'personal',
                fn ($consulta) =>
                    $consulta->where(
                        'usuario_id',
                        $usuarioId
                    )
            )
            ->exists();
    }
}
