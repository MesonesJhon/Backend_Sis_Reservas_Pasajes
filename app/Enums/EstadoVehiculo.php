<?php

namespace App\Enums;

/**
 * Representa los estados operativos posibles de un vehículo.
 *
 * Este estado indica si la unidad se encuentra disponible
 * operacionalmente. Es independiente del campo "activo",
 * que representa si el vehículo continúa habilitado
 * administrativamente dentro del sistema.
 */
enum EstadoVehiculo: string
{
    case OPERATIVO = 'OPERATIVO';
    case MANTENIMIENTO = 'MANTENIMIENTO';
    case FUERA_DE_SERVICIO = 'FUERA_DE_SERVICIO';

    /**
     * Indica si el vehículo puede utilizarse para
     * operaciones normales de transporte.
     */
    public function permiteOperacion(): bool
    {
        return $this === self::OPERATIVO;
    }
}
