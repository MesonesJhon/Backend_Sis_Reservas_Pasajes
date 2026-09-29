<?php

namespace App\Enums;

/**
 * Representa el ciclo de vida de una
 * reprogramación de reserva.
 *
 * Este estado es independiente de EstadoReserva.
 */
enum EstadoReprogramacion: string
{
    /**
     * La reprogramación ha sido solicitada
     * pero aún no ha sido consolidada.
     */
    case SOLICITADA =
        'SOLICITADA';


    /**
     * La nueva reserva ya fue preparada,
     * pero queda pendiente resolver diferencias
     * económicas o penalidades.
     *
     * Se utilizará principalmente en RF-10.3.
     */
    case PENDIENTE_AJUSTE =
        'PENDIENTE_AJUSTE';


    /**
     * La reserva origen fue reemplazada
     * correctamente por la reserva destino.
     */
    case COMPLETADA =
        'COMPLETADA';


    /**
     * La operación de reprogramación fue
     * desistida o invalidada.
     */
    case CANCELADA =
        'CANCELADA';


    public function esTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::COMPLETADA,
                self::CANCELADA,
            ],
            true
        );
    }


    public function puedeCambiarA(
        self $nuevoEstado
    ): bool {

        return match ($this) {

            self::SOLICITADA =>
                in_array(
                    $nuevoEstado,
                    [
                        self::PENDIENTE_AJUSTE,
                        self::COMPLETADA,
                        self::CANCELADA,
                    ],
                    true
                ),

            self::PENDIENTE_AJUSTE =>
                in_array(
                    $nuevoEstado,
                    [
                        self::COMPLETADA,
                        self::CANCELADA,
                    ],
                    true
                ),

            self::COMPLETADA,
            self::CANCELADA =>
                false,
        };
    }
}
