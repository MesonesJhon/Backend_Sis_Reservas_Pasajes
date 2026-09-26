<?php

namespace App\Actions\Tickets;

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTicket;
use App\Exceptions\OperacionTicketInvalidaException;
use App\Models\OcupacionAsiento;
use App\Models\PasajeroReserva;
use App\Models\Reserva;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Emite los tickets electrónicos correspondientes
 * a todos los pasajeros de una reserva confirmada.
 *
 * REGLAS:
 *
 * - la reserva debe estar CONFIRMADA;
 * - cada pasajero debe poseer una ocupación CONFIRMADA;
 * - la ocupación debe pertenecer a la misma reserva y viaje;
 * - existe exactamente un ticket por pasajero;
 * - ejecutar esta Action varias veces NO crea duplicados.
 *
 * Esta Action solamente genera el registro lógico del ticket.
 * La representación QR será incorporada posteriormente.
 */
class EmitirTicketsReserva
{
    /**
     * @return Collection<int, Ticket>
     */
    public function ejecutar(
        int $reservaId
    ): Collection {

        return DB::transaction(
            function () use (
                $reservaId
            ): Collection {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear la reserva
                |--------------------------------------------------------------------------
                |
                | Todas las operaciones importantes de reserva
                | utilizan esta fila como lock principal.
                |
                */

                $reserva =
                    Reserva::query()

                        ->whereKey(
                            $reservaId
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 2. Solamente una reserva CONFIRMADA genera tickets
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    !== EstadoReserva::CONFIRMADA
                ) {
                    throw new OperacionTicketInvalidaException(
                        'Solamente una reserva confirmada puede emitir tickets.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 3. Bloquear pasajeros
                |--------------------------------------------------------------------------
                */

                $pasajeros =
                    PasajeroReserva::query()

                        ->where(
                            'reserva_id',
                            $reserva->id
                        )

                        ->orderBy('id')

                        ->lockForUpdate()

                        ->get();


                if (
                    $pasajeros->isEmpty()
                ) {
                    throw new OperacionTicketInvalidaException(
                        'La reserva confirmada no posee pasajeros para emitir tickets.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 4. Bloquear ocupaciones
                |--------------------------------------------------------------------------
                |
                | Primero obtenemos todos los IDs y luego bloqueamos
                | en orden estable para reducir riesgo de deadlocks.
                |
                */

                $ocupacionIds =
                    $pasajeros
                        ->pluck(
                            'ocupacion_asiento_id'
                        )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->values();


                $ocupaciones =
                    OcupacionAsiento::query()

                        ->whereIn(
                            'id',
                            $ocupacionIds
                        )

                        ->orderBy('id')

                        ->lockForUpdate()

                        ->get()

                        ->keyBy(
                            'id'
                        );


                if (
                    $ocupaciones->count()
                    !== $pasajeros->count()
                ) {
                    throw new OperacionTicketInvalidaException(
                        'No todas las ocupaciones de los pasajeros existen.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 5. Validar integridad comercial
                |--------------------------------------------------------------------------
                */

                foreach (
                    $pasajeros
                    as $pasajero
                ) {

                    $ocupacion =
                        $ocupaciones->get(
                            $pasajero
                                ->ocupacion_asiento_id
                        );


                    if (
                        ! $ocupacion
                    ) {
                        throw new OperacionTicketInvalidaException(
                            'El pasajero no posee una ocupación válida.'
                        );
                    }


                    /*
                     * La ocupación debe pertenecer
                     * exactamente a esta reserva.
                     */
                    if (
                        (int)
                        $ocupacion->reserva_id
                        !==
                        (int)
                        $reserva->id
                    ) {
                        throw new OperacionTicketInvalidaException(
                            'La ocupación del pasajero no pertenece a la reserva.'
                        );
                    }


                    /*
                     * También debe pertenecer
                     * al mismo viaje.
                     */
                    if (
                        (int)
                        $ocupacion->viaje_id
                        !==
                        (int)
                        $reserva->viaje_id
                    ) {
                        throw new OperacionTicketInvalidaException(
                            'La ocupación del pasajero no pertenece al viaje de la reserva.'
                        );
                    }


                    /*
                     * Un ticket válido solamente puede emitirse
                     * cuando el asiento ya quedó confirmado.
                     */
                    if (
                        $ocupacion->estado
                        !== EstadoOcupacionAsiento::CONFIRMADO
                    ) {
                        throw new OperacionTicketInvalidaException(
                            'Todas las ocupaciones deben estar confirmadas antes de emitir tickets.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Emisión idempotente
                |--------------------------------------------------------------------------
                |
                | pasajero_reserva_id posee UNIQUE en base de datos.
                |
                | Además, la Reserva permanece bloqueada durante
                | toda la emisión.
                |
                */

                $tickets =
                    collect();


                foreach (
                    $pasajeros
                    as $pasajero
                ) {

                    $ticket =
                        Ticket::query()
                            ->firstOrCreate(
                                [
                                    'pasajero_reserva_id' =>
                                        $pasajero->id,
                                ],
                                [
                                    'codigo' =>
                                        $this
                                            ->generarCodigo(),

                                    'reserva_id' =>
                                        $reserva->id,

                                    'estado' =>
                                        EstadoTicket::VIGENTE,

                                    'emitido_en' =>
                                        now(),
                                ]
                            );


                    /*
                     * Protección adicional frente
                     * a datos inconsistentes.
                     */
                    if (
                        (int)
                        $ticket->reserva_id
                        !==
                        (int)
                        $reserva->id
                    ) {
                        throw new OperacionTicketInvalidaException(
                            'El ticket existente pertenece a una reserva diferente.'
                        );
                    }


                    /*
                     * IMPORTANTE:
                     *
                     * Si ya existía un ticket UTILIZADO
                     * o ANULADO, NO lo revivimos.
                     *
                     * La idempotencia significa conservarlo,
                     * no crear uno nuevo.
                     */

                    $tickets->push(
                        $ticket
                    );
                }


                return $tickets;
            }
        );
    }


    /**
     * Genera un identificador público.
     *
     * ULID:
     * - no expone el ID incremental;
     * - es prácticamente único;
     * - mantiene orden temporal.
     *
     * TKT- + 26 caracteres = 30 caracteres.
     */
    private function generarCodigo(): string
    {
        return
            'TKT-'
            .Str::upper(
                (string)
                Str::ulid()
            );
    }
}
