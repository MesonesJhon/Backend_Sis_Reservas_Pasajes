<?php

namespace App\Actions\Reservas;

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Expira una reserva PENDIENTE_PAGO
 * que ya superó su fecha límite.
 *
 * La Action es idempotente:
 *
 * si la reserva ya no cumple las condiciones
 * para expirar, simplemente devuelve false.
 */
class ExpirarReserva
{
    public function ejecutar(
        int $reservaId
    ): bool {

        return DB::transaction(
            function () use (
                $reservaId
            ): bool {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear reserva
                |--------------------------------------------------------------------------
                */

                $reserva =
                    Reserva::query()

                        ->whereKey(
                            $reservaId
                        )

                        ->lockForUpdate()

                        ->first();


                /*
                 * Puede haber sido eliminada o modificada
                 * entre la consulta del comando y este punto.
                 */
                if (! $reserva) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 2. Solo PENDIENTE_PAGO puede expirar
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    !== EstadoReserva::PENDIENTE_PAGO
                ) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 3. Debe existir vencimiento
                |--------------------------------------------------------------------------
                */

                if ($reserva->expira_en === null) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 4. Comprobar tiempo
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->expira_en->isFuture()
                ) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 5. Bloquear ocupaciones
                |--------------------------------------------------------------------------
                */

                $ocupaciones =
                    OcupacionAsiento::query()

                        ->where(
                            'reserva_id',
                            $reserva->id
                        )

                        ->orderBy('id')

                        ->lockForUpdate()

                        ->get();


                /*
                |--------------------------------------------------------------------------
                | 6. Liberar ocupaciones temporales
                |--------------------------------------------------------------------------
                |
                | En una reserva pendiente esperamos
                | principalmente estado RESERVADO.
                |
                | También consideramos BLOQUEADO de forma
                | defensiva ante un dato inconsistente.
                |
                | Nunca liberamos CONFIRMADO desde una
                | reserva PENDIENTE_PAGO.
                |
                */

                foreach ($ocupaciones as $ocupacion) {

                    if (
                        in_array(
                            $ocupacion->estado,
                            [
                                EstadoOcupacionAsiento::BLOQUEADO,
                                EstadoOcupacionAsiento::RESERVADO,
                            ],
                            true
                        )
                    ) {
                        $ocupacion->update([
                            'estado' =>
                                EstadoOcupacionAsiento::LIBERADO,

                            'expira_en' =>
                                null,
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 7. Marcar reserva como EXPIRADA
                |--------------------------------------------------------------------------
                |
                | Conservamos expira_en porque representa
                | la fecha límite original.
                |
                | expirada_en registra cuándo el sistema
                | procesó realmente la expiración.
                |
                */

                $reserva->update([
                    'estado' =>
                        EstadoReserva::EXPIRADA,

                    'expirada_en' =>
                        now(),
                ]);


                return true;
            }
        );
    }
}
