<?php

namespace App\Actions\Pagos;

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoPago;
use App\Enums\EstadoReserva;
use App\Models\OcupacionAsiento;
use App\Models\Pago;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Aplica el resultado de un pago aprobado
 * sobre la reserva relacionada.
 *
 * IMPORTANTE:
 *
 * Esta Action NO requiere Usuario.
 *
 * Puede ser ejecutada automáticamente por
 * un Webhook legítimo de Mercado Pago.
 *
 * Flujo correcto:
 *
 * Pago APROBADO
 *       ↓
 * Reserva PENDIENTE_PAGO
 *       ↓
 * Reserva CONFIRMADA
 *
 * Ocupaciones:
 *
 * RESERVADO -> CONFIRMADO
 */
class AplicarPagoAReserva
{
    /**
     * @return bool
     *
     * true:
     * la reserva quedó confirmada.
     *
     * false:
     * el pago no podía aplicarse automáticamente.
     */
    public function ejecutar(
        int $pagoId
    ): bool {

        /*
         * Primero obtenemos únicamente la referencia
         * necesaria para conocer la reserva.
         *
         * Todavía no modificamos nada.
         */
        $referenciaPago =
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
                $referenciaPago
            ): bool {

                /*
                |--------------------------------------------------------------------------
                | 1. Lock principal: Reserva
                |--------------------------------------------------------------------------
                |
                | Confirmar, cancelar y expirar una reserva
                | utilizan la misma fila como recurso
                | principal de concurrencia.
                |
                */

                $reserva =
                    Reserva::query()

                        ->whereKey(
                            $referenciaPago->reserva_id
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
                            $referenciaPago->id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 3. Solamente APROBADO puede confirmar
                |--------------------------------------------------------------------------
                */

                if (
                    $pago->estado
                    !== EstadoPago::APROBADO
                ) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 4. No confirmar pagos con inconsistencias
                |--------------------------------------------------------------------------
                */

                if ($pago->requiere_revision) {
                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 5. Idempotencia
                |--------------------------------------------------------------------------
                |
                | Este mismo pago ya confirmó la reserva.
                |
                */

                if (
                    $reserva->estado
                    === EstadoReserva::CONFIRMADA

                    &&

                    (int)
                    $reserva->pago_confirmacion_id
                    === (int)
                    $pago->id
                ) {
                    return true;
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Reserva confirmada por otro proceso
                |--------------------------------------------------------------------------
                |
                | Ejemplo:
                |
                | - pago A quedó pendiente;
                | - se confirmó manualmente;
                | - luego pago A llega aprobado.
                |
                | El dinero existe, pero no debe
                | producir otra confirmación.
                |
                */

                if (
                    $reserva->estado
                    === EstadoReserva::CONFIRMADA
                ) {

                    $this->marcarRevision(
                        $pago,
                        'El pago fue aprobado cuando la reserva ya se encontraba confirmada por otro proceso.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 7. Reserva cancelada
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    === EstadoReserva::CANCELADA
                ) {

                    $this->marcarRevision(
                        $pago,
                        'El pago fue aprobado después de que la reserva fue cancelada.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Reserva previamente expirada
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    === EstadoReserva::EXPIRADA
                ) {

                    $this->marcarRevision(
                        $pago,
                        'El pago fue aprobado después de que la reserva había expirado.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 9. Estado permitido
                |--------------------------------------------------------------------------
                */

                if (
                    $reserva->estado
                    !== EstadoReserva::PENDIENTE_PAGO
                ) {

                    $this->marcarRevision(
                        $pago,
                        'El pago fue aprobado para una reserva cuyo estado no permite confirmación automática.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 10. Bloquear ocupaciones
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


                if ($ocupaciones->isEmpty()) {

                    $this->marcarRevision(
                        $pago,
                        'La reserva pagada no tiene ocupaciones de asiento asociadas.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 11. Vencimiento
                |--------------------------------------------------------------------------
                |
                | No basta con que el scheduler todavía
                | no haya ejecutado ExpirarReserva.
                |
                */

                $reservaVencida =
                    $reserva->expira_en === null

                    ||

                    $reserva
                        ->expira_en
                        ->lte(
                            now()
                        );


                $ocupacionVencida =
                    $ocupaciones->contains(
                        function (
                            OcupacionAsiento $ocupacion
                        ): bool {

                            return $ocupacion->expira_en !== null
                                && $ocupacion
                                    ->expira_en
                                    ->lte(
                                        now()
                                    );
                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | 12. Pago aprobado demasiado tarde
                |--------------------------------------------------------------------------
                */

                if (
                    $reservaVencida
                    || $ocupacionVencida
                ) {

                    /*
                     * La reserva deja de ser utilizable.
                     */
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
                            EstadoReserva::EXPIRADA,

                        'expirada_en' =>
                            $reserva->expirada_en
                            ?? now(),
                    ]);


                    /*
                     * Financieramente el pago sigue siendo
                     * APROBADO.
                     *
                     * Lo que falla es su aplicación
                     * comercial automática.
                     */
                    $this->marcarRevision(
                        $pago,
                        'El pago fue aprobado después del vencimiento de la reserva. Requiere revisión y posible reembolso.'
                    );


                    return false;
                }


                /*
                |--------------------------------------------------------------------------
                | 13. Validar integridad ANTES de modificar
                |--------------------------------------------------------------------------
                */

                foreach (
                    $ocupaciones
                    as $ocupacion
                ) {

                    if (
                        $ocupacion->estado
                        !== EstadoOcupacionAsiento::RESERVADO
                    ) {

                        $this->marcarRevision(
                            $pago,
                            'No todas las ocupaciones asociadas se encuentran en estado RESERVADO.'
                        );


                        return false;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 14. Confirmar ocupaciones
                |--------------------------------------------------------------------------
                */

                foreach (
                    $ocupaciones
                    as $ocupacion
                ) {

                    $ocupacion->update([

                        'estado' =>
                            EstadoOcupacionAsiento::CONFIRMADO,

                        'expira_en' =>
                            null,
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | 15. Confirmar Reserva
                |--------------------------------------------------------------------------
                */

                $reserva->update([

                    'estado' =>
                        EstadoReserva::CONFIRMADA,

                    'pago_confirmacion_id' =>
                        $pago->id,

                    'confirmada_en' =>
                        now(),

                    /*
                     * Una reserva confirmada
                     * ya no tiene vencimiento.
                     */
                    'expira_en' =>
                        null,
                ]);


                return true;
            }
        );
    }


    /**
     * Marca el pago para intervención manual
     * sin alterar su estado financiero.
     *
     * Pago APROBADO sigue siendo APROBADO.
     */
    private function marcarRevision(
        Pago $pago,
        string $motivo
    ): void {

        /*
         * Evitamos duplicar el mismo texto
         * en Webhooks repetidos.
         */
        $motivos = [];


        if ($pago->motivo_revision) {

            $motivos =
                array_map(
                    'trim',
                    explode(
                        ' | ',
                        $pago->motivo_revision
                    )
                );
        }


        if (
            ! in_array(
                $motivo,
                $motivos,
                true
            )
        ) {
            $motivos[] =
                $motivo;
        }


        $pago->update([

            'requiere_revision' =>
                true,

            'motivo_revision' =>
                implode(
                    ' | ',
                    $motivos
                ),
        ]);
    }
}
