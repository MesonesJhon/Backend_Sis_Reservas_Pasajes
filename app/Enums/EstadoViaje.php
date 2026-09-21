<?php

namespace App\Enums;

enum EstadoViaje: string
{
    case BORRADOR = 'BORRADOR';
    case PROGRAMADO = 'PROGRAMADO';
    case EMBARCANDO = 'EMBARCANDO';
    case EN_RUTA = 'EN_RUTA';
    case FINALIZADO = 'FINALIZADO';
    case CANCELADO = 'CANCELADO';

    /**
     * Determina si el viaje todavía admite cambios
     * administrativos en su configuración.
     */
    public function permiteEdicion(): bool
    {
        return $this === self::BORRADOR;
    }

    /**
     * Determina si el viaje ya terminó su ciclo operativo.
     */
    public function esEstadoFinal(): bool
    {
        return in_array($this, [
            self::FINALIZADO,
            self::CANCELADO,
        ], true);
    }

    /**
     * Define las transiciones de estado permitidas.
     *
     * Centralizar esta regla evita distribuir condiciones
     * de negocio entre Controllers, Actions y Middleware.
     */
    public function puedeCambiarA(self $nuevoEstado): bool
    {
        return match ($this) {
            self::BORRADOR => in_array($nuevoEstado, [
                self::PROGRAMADO,
                self::CANCELADO,
            ], true),

            self::PROGRAMADO => in_array($nuevoEstado, [
                self::EMBARCANDO,
                self::CANCELADO,
            ], true),

            self::EMBARCANDO => in_array($nuevoEstado, [
                self::EN_RUTA,
                self::CANCELADO,
            ], true),

            self::EN_RUTA => $nuevoEstado === self::FINALIZADO,

            self::FINALIZADO,
            self::CANCELADO => false,
        };
    }
}
