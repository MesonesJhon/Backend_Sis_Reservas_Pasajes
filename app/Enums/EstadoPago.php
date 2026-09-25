<?php

namespace App\Enums;

/**
 * Representa el estado interno de un intento de pago.
 *
 * Este estado es independiente del estado comercial
 * de la reserva.
 */
enum EstadoPago: string
{
    /**
     * El intento existe internamente, pero todavía
     * no se ha iniciado correctamente con el proveedor.
     */
    case CREADO = 'CREADO';


    /**
     * El proveedor recibió la operación y todavía
     * no existe una resolución definitiva.
     */
    case PENDIENTE = 'PENDIENTE';


    /**
     * El proveedor confirmó que el dinero
     * fue aprobado.
     */
    case APROBADO = 'APROBADO';


    /**
     * El proveedor rechazó la operación.
     */
    case RECHAZADO = 'RECHAZADO';


    /**
     * El intento fue cancelado.
     */
    case CANCELADO = 'CANCELADO';


    /**
     * Ocurrió un error durante el procesamiento
     * o comunicación con el proveedor.
     */
    case ERROR = 'ERROR';


    /**
     * El dinero previamente aprobado
     * fue posteriormente reembolsado.
     */
    case REEMBOLSADO = 'REEMBOLSADO';


    /**
     * Indica si el intento ya terminó.
     *
     * PENDIENTE todavía puede cambiar posteriormente
     * mediante una notificación Webhook.
     */
    public function esFinal(): bool
    {
        return in_array(
            $this,
            [
                self::APROBADO,
                self::RECHAZADO,
                self::CANCELADO,
                self::ERROR,
                self::REEMBOLSADO,
            ],
            true
        );
    }


    /**
     * Indica si el intento terminó sin concretar
     * correctamente el pago.
     *
     * Esto permitirá posteriormente que el cliente
     * inicie un nuevo intento de pago.
     */
    public function permiteNuevoIntento(): bool
    {
        return in_array(
            $this,
            [
                self::RECHAZADO,
                self::CANCELADO,
                self::ERROR,
            ],
            true
        );
    }
}
