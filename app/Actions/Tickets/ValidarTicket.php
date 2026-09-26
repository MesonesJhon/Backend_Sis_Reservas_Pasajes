<?php

namespace App\Actions\Tickets;

use App\Domain\Tickets\AutorizacionTicket;
use App\Domain\Tickets\GeneradorQrTicket;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTicket;
use App\Exceptions\OperacionTicketInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use App\Models\PasajeroReserva;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Valida un ticket electrónico durante
 * el proceso de embarque.
 *
 * Una validación correcta produce:
 *
 * VIGENTE -> UTILIZADO
 */
class ValidarTicket
{
    public function __construct(
        private readonly GeneradorQrTicket $generadorQrTicket,

        private readonly AutorizacionTicket $autorizacionTicket
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        string $token,
        int $viajeId
    ): Ticket {

        /*
        |--------------------------------------------------------------------------
        | 1. Validación criptográfica
        |--------------------------------------------------------------------------
        |
        | Antes de consultar información de negocio comprobamos
        | que el QR fue generado por nuestro backend.
        |
        */

        $codigo =
            $this
                ->generadorQrTicket
                ->validarYExtraerCodigo(
                    $token
                );


        /*
        |--------------------------------------------------------------------------
        | 2. Buscar referencia inicial
        |--------------------------------------------------------------------------
        |
        | Todavía no modificamos nada.
        |
        | Esta búsqueda solamente permite conocer qué reserva
        | debemos bloquear primero dentro de la transacción.
        |
        */

        $referenciaTicket =
            Ticket::query()

                ->where(
                    'codigo',
                    $codigo
                )

                ->first();


        if (
            $referenciaTicket === null
        ) {
            throw new OperacionTicketInvalidaException(
                'El ticket indicado no existe.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Transacción
        |--------------------------------------------------------------------------
        */

        return DB::transaction(
            function () use (
                $usuario,
                $viajeId,
                $referenciaTicket
            ) {

                /*
                |--------------------------------------------------------------------------
                | Reserva
                |--------------------------------------------------------------------------
                |
                | La bloqueamos primero para mantener un orden
                | consistente con operaciones comerciales.
                |
                */

                $reserva =
                    Reserva::query()

                        ->whereKey(
                            $referenciaTicket->reserva_id
                        )

                        ->lockForUpdate()

                        ->first();


                if (
                    $reserva === null
                ) {
                    throw new OperacionTicketInvalidaException(
                        'La reserva asociada al ticket no existe.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Ticket
                |--------------------------------------------------------------------------
                |
                | Este lock es fundamental para impedir:
                |
                | Scanner A -> VIGENTE
                | Scanner B -> VIGENTE
                |
                | simultáneamente.
                |
                */

                $ticket =
                    Ticket::query()

                        ->whereKey(
                            $referenciaTicket->id
                        )

                        ->lockForUpdate()

                        ->first();


                if (
                    $ticket === null
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El ticket indicado ya no existe.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Pasajero
                |--------------------------------------------------------------------------
                */

                $pasajero =
                    PasajeroReserva::query()

                        ->whereKey(
                            $ticket->pasajero_reserva_id
                        )

                        ->first();


                if (
                    $pasajero === null
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El ticket no posee un pasajero válido.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Ocupación
                |--------------------------------------------------------------------------
                */

                $ocupacion =
                    OcupacionAsiento::query()

                        ->whereKey(
                            $pasajero->ocupacion_asiento_id
                        )

                        ->lockForUpdate()

                        ->first();


                if (
                    $ocupacion === null
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El ticket no posee una ocupación de asiento válida.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 4. Ticket VIGENTE
                |--------------------------------------------------------------------------
                */

                if (
                    $ticket->estado
                    !== EstadoTicket::VIGENTE
                ) {

                    if (
                        $ticket->estado
                        === EstadoTicket::UTILIZADO
                    ) {
                        throw new OperacionTicketInvalidaException(
                            'El ticket ya fue utilizado anteriormente.'
                        );
                    }


                    throw new OperacionTicketInvalidaException(
                        'El ticket se encuentra anulado y no puede utilizarse.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 5. Reserva CONFIRMADA
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    !== EstadoReserva::CONFIRMADA
                ) {
                    throw new OperacionTicketInvalidaException(
                        'La reserva asociada al ticket no se encuentra confirmada.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Ocupación CONFIRMADO
                |--------------------------------------------------------------------------
                */

                if (
                    $ocupacion->estado
                    !== EstadoOcupacionAsiento::CONFIRMADO
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El asiento asociado al ticket no se encuentra confirmado.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 7. Integridad interna
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $pasajero->reserva_id
                    !== (int) $reserva->id
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El pasajero no pertenece a la reserva del ticket.'
                    );
                }


                if (
                    (int) $ocupacion->reserva_id
                    !== (int) $reserva->id
                ) {
                    throw new OperacionTicketInvalidaException(
                        'La ocupación no pertenece a la reserva del ticket.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Viaje correcto
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $reserva->viaje_id
                    !== $viajeId
                    ||
                    (int) $ocupacion->viaje_id
                    !== $viajeId
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El ticket no corresponde al viaje seleccionado.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 9. Usuario autorizado para el viaje
                |--------------------------------------------------------------------------
                */

                if (
                    ! $this
                        ->autorizacionTicket
                        ->puedeValidarEnViaje(
                            $usuario,
                            $viajeId
                        )
                ) {
                    throw new OperacionUsuarioNoPermitidaException(
                        'No estás autorizado para validar tickets de este viaje.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 10. Transición
                |--------------------------------------------------------------------------
                */

                if (
                    ! $ticket
                        ->estado
                        ->puedeCambiarA(
                            EstadoTicket::UTILIZADO
                        )
                ) {
                    throw new OperacionTicketInvalidaException(
                        'El ticket no puede marcarse como utilizado.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 11. Auditoría
                |--------------------------------------------------------------------------
                */

                $ticket->estado =
                    EstadoTicket::UTILIZADO;


                $ticket->validado_en =
                    now();


                $ticket->validado_por_usuario_id =
                    $usuario->id;


                $ticket->save();


                /*
                |--------------------------------------------------------------------------
                | 12. Relaciones para respuesta
                |--------------------------------------------------------------------------
                */

                return $ticket->load([
                    'reserva.viaje',
                    'pasajero.ocupacionAsiento.asientoViaje',
                    'pasajero.ocupacionAsiento.puntoOrigen',
                    'pasajero.ocupacionAsiento.puntoDestino',
                    'validadoPor',
                ]);
            }
        );
    }
}
