<?php

namespace App\Actions\Tickets;

use App\Enums\EstadoTicket;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Anula los tickets VIGENTES pertenecientes
 * a una reserva.
 *
 * Reglas:
 *
 * VIGENTE   -> ANULADO
 * UTILIZADO -> se conserva
 * ANULADO   -> se conserva
 *
 * La operación es idempotente.
 */
class AnularTicketsReserva
{
    public function ejecutar(
        int $reservaId,
        string $motivo
    ): int {

        $motivo =
            trim(
                $motivo
            );


        if ($motivo === '') {
            $motivo =
                'Ticket anulado por cancelación de la reserva.';
        }


        /*
         * Defensa adicional frente al límite
         * de la columna motivo_anulacion.
         */
        $motivo =
            mb_substr(
                $motivo,
                0,
                255
            );


        return DB::transaction(
            function () use (
                $reservaId,
                $motivo
            ): int {

                /*
                |--------------------------------------------------------------------------
                | Bloquear tickets
                |--------------------------------------------------------------------------
                |
                | orderBy mantiene un orden estable cuando
                | una reserva tiene varios pasajeros.
                |
                */

                $tickets =
                    Ticket::query()

                        ->where(
                            'reserva_id',
                            $reservaId
                        )

                        ->orderBy(
                            'id'
                        )

                        ->lockForUpdate()

                        ->get();


                $cantidadAnulados = 0;


                foreach (
                    $tickets
                    as $ticket
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Solo VIGENTE puede anularse
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $ticket->estado
                        !== EstadoTicket::VIGENTE
                    ) {
                        continue;
                    }


                    if (
                        ! $ticket
                            ->estado
                            ->puedeCambiarA(
                                EstadoTicket::ANULADO
                            )
                    ) {
                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Anulación
                    |--------------------------------------------------------------------------
                    */

                    $ticket->update([
                        'estado' =>
                            EstadoTicket::ANULADO,

                        'anulado_en' =>
                            now(),

                        'motivo_anulacion' =>
                            $motivo,
                    ]);


                    $cantidadAnulados++;
                }


                return $cantidadAnulados;
            }
        );
    }
}
