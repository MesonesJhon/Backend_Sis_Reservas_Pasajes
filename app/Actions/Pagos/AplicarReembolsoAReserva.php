<?php

namespace App\Actions\Pagos;
use App\Actions\Postventa\RegistrarOperacionPostventa;
use App\Enums\OrigenOperacionPostventa;
use App\Enums\TipoOperacionPostventa;
use App\Enums\EstadoOcupacionAsiento;
use App\Actions\Tickets\AnularTicketsReserva;
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

    public function __construct(
        private readonly AnularTicketsReserva $anularTicketsReserva,
        private readonly RegistrarOperacionPostventa $registrarOperacionPostventa
    ) {
    }


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
                | RF-10.1 - Registrar reembolso financiero
                |--------------------------------------------------------------------------
                |
                | Este evento representa el hecho financiero:
                |
                | Mercado Pago confirmó que el dinero fue reembolsado.
                |
                | Es distinto de que posteriormente la reserva
                | pueda o no cancelarse comercialmente.
                |
                */

                $this->registrarReembolso(
                    $pago
                );


                /*
                |--------------------------------------------------------------------------
                | 4. Estados que ya no necesitan acción
                |--------------------------------------------------------------------------
                */

                /*
                |--------------------------------------------------------------------------
                | Reserva ya cancelada
                |--------------------------------------------------------------------------
                |
                | Puede tratarse de un Webhook repetido.
                |
                | Reintentamos la anulación de tickets porque
                | AnularTicketsReserva es idempotente.
                |
                */

                if (
                    $reserva->estado
                    === EstadoReserva::CANCELADA
                ) {

                    $this
                        ->anularTicketsReserva
                        ->ejecutar(
                            $reserva->id,
                            'Reembolso total confirmado por Mercado Pago.'
                        );


                    return true;
                }


                /*
                |--------------------------------------------------------------------------
                | Reserva expirada
                |--------------------------------------------------------------------------
                |
                | Una reserva EXPIRADA normalmente nunca emitió tickets
                | porque los tickets aparecen únicamente al confirmar.
                |
                */

                if (
                    $reserva->estado
                    === EstadoReserva::EXPIRADA
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
                        $reserva,
                        $pago
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
                        $reserva,
                        $pago
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
        Reserva $reserva,
        Pago $pago
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Anular tickets
        |--------------------------------------------------------------------------
        |
        | Reembolso total válido:
        |
        | Ticket VIGENTE -> ANULADO
        |
        | Un ticket UTILIZADO conserva su auditoría.
        |
        */

        $this
            ->anularTicketsReserva
            ->ejecutar(
                $reserva->id,
                'Reembolso total confirmado por Mercado Pago.'
            );


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

        $estadoAnterior =
            $reserva->estado;

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


        /*
        |--------------------------------------------------------------------------
        | RF-10.1 - Cancelación comercial por reembolso
        |--------------------------------------------------------------------------
        */

        $this
            ->registrarOperacionPostventa
            ->ejecutar(
                tipo:
                    TipoOperacionPostventa::CANCELACION,

                origen:
                    OrigenOperacionPostventa::SISTEMA,

                reservaId:
                    $reserva->id,

                pagoId:
                    $pago->id,

                motivo:
                    'Reserva cancelada como consecuencia de un reembolso total.',

                datos: [
                    'estado_anterior' =>
                        $estadoAnterior->value,

                    'estado_nuevo' =>
                        EstadoReserva::CANCELADA->value,

                    'causa' =>
                        'REEMBOLSO_TOTAL',
                ],

                claveIdempotencia:
                    'postventa:cancelacion-reembolso:reserva:'
                    .$reserva->id
                    .':pago:'
                    .$pago->id
            );
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

    private function registrarReembolso(
        Pago $pago
    ): void {

        $this
            ->registrarOperacionPostventa
            ->ejecutar(
                tipo:
                    TipoOperacionPostventa::REEMBOLSO,

                origen:
                    OrigenOperacionPostventa::PROVEEDOR,

                pagoId:
                    $pago->id,

                monto:
                    $pago->monto,

                moneda:
                    $pago->moneda,

                motivo:
                    'Reembolso total confirmado por Mercado Pago.',

                datos: [
                    'tipo_reembolso' =>
                        'TOTAL',

                    'estado_pago' =>
                        EstadoPago::REEMBOLSADO->value,

                    'proveedor_payment_id' =>
                        $pago->proveedor_payment_id,
                ],

                claveIdempotencia:
                    'postventa:reembolso-total:pago:'
                    .$pago->id,

                ocurrioEn:
                    $pago->reembolsado_en
                    ?? now()
            );
    }
}
