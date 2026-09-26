<?php

namespace App\Domain\Tickets;

use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Enums\FuncionPersonalViaje;

/**
 * Centraliza las reglas de autorización
 * para la consulta de tickets.
 */
class AutorizacionTicket
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $consultaReservas
    ) {
    }

    /**
     * Determina si un usuario puede visualizar
     * un ticket específico.
     */
    public function puedeVer(
        Usuario $usuario,
        Ticket $ticket
    ): bool {

        $ticket->loadMissing(
            'reserva'
        );

        return $this
            ->consultaReservas
            ->puedeVer(
                $usuario,
                $ticket->reserva
            );
    }

    /**
     * Determina si un usuario puede consultar
     * los tickets pertenecientes a una reserva.
     */
    public function puedeVerReserva(
        Usuario $usuario,
        Reserva $reserva
    ): bool {

        return $this
            ->consultaReservas
            ->puedeVer(
                $usuario,
                $reserva
            );
    }


    /**
     * Determina si un usuario puede validar
     * tickets dentro de un viaje concreto.
     *
     * ADMINISTRADOR y OPERADOR poseen alcance
     * operativo global.
     *
     * El CONDUCTOR debe encontrarse asignado
     * específicamente al viaje.
     */
    public function puedeValidarEnViaje(
        Usuario $usuario,
        int $viajeId
    ): bool {

        /*
        |--------------------------------------------------------------------------
        | Personal administrativo / operativo
        |--------------------------------------------------------------------------
        */

        if (
            $usuario->tieneRol(
                'ADMINISTRADOR'
            )
            ||
            $usuario->tieneRol(
                'OPERADOR'
            )
        ) {
            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | Conductor
        |--------------------------------------------------------------------------
        */

        if (
            ! $usuario->tieneRol(
                'CONDUCTOR'
            )
        ) {
            return false;
        }


        /*
        * No basta poseer el rol CONDUCTOR.
        *
        * Debe existir una asignación real:
        *
        * usuario + viaje.
        */
        return $usuario
            ->asignacionesViaje()

            ->where(
                'viaje_id',
                $viajeId
            )

            ->whereIn(
                'funcion',
                [
                    FuncionPersonalViaje::CONDUCTOR->value,
                    FuncionPersonalViaje::CONDUCTOR_AUXILIAR->value,
                ]
            )

            ->exists();
    }
}
