<?php

namespace App\Enums;

/**
 * Representa el estado operativo
 * de un ticket electrónico.
 *
 * Este estado es independiente del
 * estado financiero del Pago y del
 * estado comercial de la Reserva.
 */
enum EstadoTicket: string
{
    /**
     * Ticket emitido y disponible
     * para ser utilizado.
     */
    case VIGENTE = 'VIGENTE';


    /**
     * El pasajero ya utilizó el ticket
     * durante el proceso de embarque.
     */
    case UTILIZADO = 'UTILIZADO';


    /**
     * El ticket dejó de ser válido
     * debido a una cancelación,
     * reembolso u operación administrativa.
     */
    case ANULADO = 'ANULADO';


    /**
     * Determina si el ticket todavía
     * puede utilizarse para embarque.
     */
    public function esValidable(): bool
    {
        return $this === self::VIGENTE;
    }


    /**
     * Define las transiciones permitidas.
     */
    public function puedeCambiarA(
        self $nuevoEstado
    ): bool {

        return match ($this) {

            self::VIGENTE =>
                in_array(
                    $nuevoEstado,
                    [
                        self::UTILIZADO,
                        self::ANULADO,
                    ],
                    true
                ),

            self::UTILIZADO,
            self::ANULADO =>
                false,
        };
    }
}
