<?php

namespace App\Enums;

/**
 * Representa el estado comercial de una reserva.
 *
 * Este estado es independiente del estado de las
 * ocupaciones de asiento.
 *
 * Una reserva agrupa uno o varios pasajeros y
 * sus respectivas ocupaciones.
 */
enum EstadoReserva: string
{
    /**
     * La reserva ya fue creada y los asientos
     * están retenidos, pero todavía falta
     * completar el proceso de pago.
     */
    case PENDIENTE_PAGO = 'PENDIENTE_PAGO';


    /**
     * La reserva completó correctamente
     * el proceso comercial.
     */
    case CONFIRMADA = 'CONFIRMADA';


    /**
     * La reserva fue cancelada explícitamente.
     */
    case CANCELADA = 'CANCELADA';


    /**
     * La reserva superó su tiempo máximo
     * sin completar el proceso requerido.
     */
    case EXPIRADA = 'EXPIRADA';


    /**
     * Determina si la reserva todavía se encuentra
     * esperando completar el proceso de compra.
     */
    public function estaPendientePago(): bool
    {
        return $this === self::PENDIENTE_PAGO;
    }


    /**
     * Indica si el estado ya no admite
     * nuevas transiciones.
     *
     * CONFIRMADA no se considera terminal porque
     * posteriormente puede existir una cancelación
     * según las reglas operativas del sistema.
     */
    public function esTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::CANCELADA,
                self::EXPIRADA,
            ],
            true
        );
    }


    /**
     * Define las transiciones permitidas
     * para una reserva.
     *
     * Mantener esta regla dentro del enum evita
     * distribuir condiciones de estado entre
     * Controllers y Actions.
     */
    public function puedeCambiarA(
        self $nuevoEstado
    ): bool {
        return match ($this) {

            self::PENDIENTE_PAGO => in_array(
                $nuevoEstado,
                [
                    self::CONFIRMADA,
                    self::CANCELADA,
                    self::EXPIRADA,
                ],
                true
            ),

            self::CONFIRMADA =>
                $nuevoEstado === self::CANCELADA,

            self::CANCELADA,
            self::EXPIRADA => false,
        };
    }
}
