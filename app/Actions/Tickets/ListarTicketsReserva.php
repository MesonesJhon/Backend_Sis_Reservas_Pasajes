<?php

namespace App\Actions\Tickets;

use App\Domain\Tickets\AutorizacionTicket;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

/**
 * Obtiene todos los tickets pertenecientes
 * a una reserva visible para el usuario.
 */
class ListarTicketsReserva
{
    public function __construct(
        private readonly AutorizacionTicket $autorizacionTicket,

        private readonly ObtenerTicket $obtenerTicket
    ) {
    }


    /**
     * @return Collection<int, Ticket>
     */
    public function ejecutar(
        Usuario $usuario,
        Reserva $reserva
    ): Collection {

        /*
        |--------------------------------------------------------------------------
        | Autorización sobre la reserva
        |--------------------------------------------------------------------------
        */

        if (
            ! $this
                ->autorizacionTicket
                ->puedeVerReserva(
                    $usuario,
                    $reserva
                )
        ) {

            throw new OperacionUsuarioNoPermitidaException(
                'No puedes consultar los tickets de esta reserva.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Obtener tickets
        |--------------------------------------------------------------------------
        */

        $tickets =
            Ticket::query()

                ->where(
                    'reserva_id',
                    $reserva->id
                )

                ->with([

                    'reserva.viaje',

                    'reserva.pagoConfirmacion',

                    'pasajero.ocupacionAsiento.asientoViaje',

                    'pasajero.ocupacionAsiento.puntoOrigen',

                    'pasajero.ocupacionAsiento.puntoDestino',
                ])

                ->orderBy(
                    'id'
                )

                ->get();


        /*
        |--------------------------------------------------------------------------
        | Preparar cada ticket
        |--------------------------------------------------------------------------
        |
        | Reutilizamos ObtenerTicket para no duplicar
        | la construcción del detalle.
        |
        */

        return $tickets->map(

            fn (Ticket $ticket) =>

                $this
                    ->obtenerTicket
                    ->ejecutar(
                        $usuario,
                        $ticket
                    )
        );
    }
}
