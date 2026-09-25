<?php

namespace App\Actions\Pagos;

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoPago;
use App\Enums\EstadoReserva;
use App\Enums\EstadoViaje;
use App\Models\OcupacionAsiento;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;

/**
 * Aplica comercialmente un reembolso total
 * confirmado por Mercado Pago.
 *
 * IMPORTANTE:
 *
 * Esta Action NO realiza el reembolso.
 *
 * El dinero ya fue devuelto por Mercado Pago.
 * Esta clase sincroniza las consecuencias
 * comerciales dentro de nuestro sistema.
 */
class AplicarReembolsoAReserva
{
    public function ejecutar(
        int $pagoId
    ): bool {

        $referencia =
            Pago::query()
                ->select(
                    'id',
                    'reserva_id'
                )
                ->findOrFail(
                    $pagoId
                );


        return DB::transaction(
            function () use (
                $referencia
            ): bool {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear Reserva
                |--------------------------------------------------------------------------
                */

                $reserva =
                    Reserva::query()

                        ->whereKey(
                            $referencia->reserva_id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 2. Bloquear Pago
                |--------------------------------------------------------------------------
                */

                $pago =
                    Pago::query()

                        ->whereKey(
                            $referencia->id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 3. Debe ser reembolso completo
                |--------------------------------------------------------------------------
                */

                if (
                    $pago->estado
                    !== EstadoPago::REEMBOLSADO
                ) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 4. Estados que ya no necesitan acción
                |--------------------------------------------------------------------------
                */

                if (
                    in_array(
                        $reserva->estado,
                        [
                            EstadoReserva::CANCELADA,
                            EstadoReserva::EXPIRADA,
                        ],
                        true
                    )
                ) {
                    return true;
                }


                /*
                |--------------------------------------------------------------------------
                | 5. Reserva pendiente
                |--------------------------------------------------------------------------
                |
                | Si localmente nunca llegó a confirmarse pero
                | posteriormente descubrimos que el dinero fue
                | reembolsado, ya no existe una venta válida.
                |
                */

                if (
                    $reserva->estado
                    === EstadoReserva::PENDIENTE_PAGO
                ) {

                    $this->cancelarYLiberar(
                        $reserva
                    );


                    return true;
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Reserva confirmada
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    !== EstadoReserva::CONFIRMADA
                ) {
                    return false;
                }


                /*
                 * Debe tratarse del mismo pago que
                 * confirmó originalmente la reserva.
                 */
                if (
                    (int)
                    $reserva->pago_confirmacion_id
                    !==
                    (int)
                    $pago->id
                ) {

                    $this->marcarRevision(
                        $pago,
                        'Se recibió un reembolso para un pago que no corresponde al pago de confirmación de la reserva.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 7. Revisar estado operacional del viaje
                |--------------------------------------------------------------------------
                */

                $viaje =
                    Viaje::query()

                        ->whereKey(
                            $reserva->viaje_id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                 * Cuando el viaje todavía está PROGRAMADO,
                 * podemos devolver el asiento al inventario.
                 */
                if (
                    $viaje->estado
                    === EstadoViaje::PROGRAMADO

                    &&

                    $viaje
                        ->salida_programada
                        ->isFuture()
                ) {

                    $this->cancelarYLiberar(
                        $reserva
                    );


                    return true;
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Viaje operativo o finalizado
                |--------------------------------------------------------------------------
                |
                | No liberamos automáticamente un asiento
                | durante embarque, ruta o después del viaje.
                |
                */

                $this->marcarRevision(
                    $pago,
                    'El pago fue reembolsado cuando el viaje ya había iniciado su ciclo operativo. Requiere revisión manual.'
                );


                return false;
            }
        );
    }


    /**
     * Cancela comercialmente la reserva
     * y libera sus ocupaciones.
     */
    private function cancelarYLiberar(
        Reserva $reserva
    ): void {

        $ocupaciones =
            OcupacionAsiento::query()

                ->where(
                    'reserva_id',
                    $reserva->id
                )

                ->orderBy('id')

                ->lockForUpdate()

                ->get();


        foreach (
            $ocupaciones
            as $ocupacion
        ) {

            if (
                in_array(
                    $ocupacion->estado,
                    [
                        EstadoOcupacionAsiento::BLOQUEADO,
                        EstadoOcupacionAsiento::RESERVADO,
                        EstadoOcupacionAsiento::CONFIRMADO,
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


        $reserva->update([
            'estado' =>
                EstadoReserva::CANCELADA,

            'cancelada_en' =>
                $reserva->cancelada_en
                ?? now(),

            'motivo_cancelacion' =>
                'Reembolso total confirmado por Mercado Pago.',

            'expira_en' =>
                null,
        ]);
    }


    /**
     * Conserva cualquier motivo previo
     * de revisión.
     */
    private function marcarRevision(
        Pago $pago,
        string $motivo
    ): void {

        $motivos = [];


        if (
            is_string(
                $pago->motivo_revision
            )
            && trim(
                $pago->motivo_revision
            ) !== ''
        ) {
            $motivos[] =
                trim(
                    $pago->motivo_revision
                );
        }


        $motivos[] =
            $motivo;


        $pago->update([
            'requiere_revision' =>
                true,

            'motivo_revision' =>
                implode(
                    ' | ',
                    array_unique(
                        $motivos
                    )
                ),
        ]);
    }
}
