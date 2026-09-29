<?php

namespace App\Actions\Reservas;
use App\Actions\Postventa\RegistrarOperacionPostventa;
use App\Enums\OrigenOperacionPostventa;
use App\Enums\TipoOperacionPostventa;
use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Exceptions\OperacionReservaInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use App\Actions\Tickets\AnularTicketsReserva;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Enums\EstadoPago;
use App\Models\Pago;
use App\Enums\EstadoReprogramacion;
use App\Models\ReprogramacionReserva;
use Illuminate\Support\Facades\DB;

/**
 * Cancela una reserva y libera todas
 * las ocupaciones asociadas.
 *
 * La modificación de la reserva y de los
 * asientos se realiza dentro de una única
 * transacción.
 */
class CancelarReserva
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $autorizacion,
        private readonly AnularTicketsReserva $anularTicketsReserva,
        private readonly RegistrarOperacionPostventa $registrarOperacionPostventa
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Reserva $reserva,
        ?string $motivo = null
    ): Reserva {

        return DB::transaction(
            function () use (
                $usuario,
                $reserva,
                $motivo
            ) {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear la reserva
                |--------------------------------------------------------------------------
                |
                | Impide que dos procesos intenten:
                |
                | - cancelar;
                | - confirmar;
                | - expirar;
                |
                | la misma reserva al mismo tiempo.
                |
                */

                $reservaBloqueada =
                    Reserva::query()
                        ->whereKey(
                            $reserva->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 2. Autorización
                |--------------------------------------------------------------------------
                */

                if (
                    ! $this
                        ->autorizacion
                        ->puedeCancelar(
                            $usuario,
                            $reservaBloqueada
                        )
                ) {
                    throw new OperacionUsuarioNoPermitidaException(
                        'No puedes cancelar una reserva que pertenece a otro usuario.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 3. Validar transición
                |--------------------------------------------------------------------------
                |
                | De acuerdo con EstadoReserva:
                |
                | PENDIENTE_PAGO -> CANCELADA
                | CONFIRMADA     -> CANCELADA
                |
                | CANCELADA y EXPIRADA no pueden volver
                | a cambiar de estado.
                |
                */

                if (
                    ! $reservaBloqueada
                        ->estado
                        ->puedeCambiarA(
                            EstadoReserva::CANCELADA
                        )
                ) {
                    throw new OperacionReservaInvalidaException(
                        'La reserva no puede cancelarse desde su estado actual.'
                    );
                }

                $estadoAnterior =
                    $reservaBloqueada->estado;




                /*
                |--------------------------------------------------------------------------
                | RF-10.2 - Reserva proveniente de reprogramación
                |--------------------------------------------------------------------------
                |
                | Una reserva CONFIRMADA generada mediante una
                | reprogramación no puede utilizar la cancelación
                | tradicional.
                |
                | Puede contener valor económico transferido desde
                | una reserva pagada originalmente.
                |
                | La cancelación deberá pasar posteriormente por
                | el flujo postventa correspondiente.
                |
                */

                if (
                    $reservaBloqueada->estado
                    === EstadoReserva::CONFIRMADA
                ) {

                    $reprogramacionOrigen =
                        ReprogramacionReserva::query()

                            ->where(
                                'reserva_destino_id',
                                $reservaBloqueada->id
                            )

                            ->where(
                                'estado',
                                EstadoReprogramacion::COMPLETADA->value
                            )

                            ->lockForUpdate()

                            ->first();


                    if (
                        $reprogramacionOrigen
                        !== null
                    ) {
                        throw new OperacionReservaInvalidaException(
                            'Una reserva confirmada proveniente de una reprogramación debe gestionarse mediante el flujo de postventa.'
                        );
                    }
                }




                /*
                |--------------------------------------------------------------------------
                | Proteger reservas pagadas electrónicamente
                |--------------------------------------------------------------------------
                |
                | Una reserva CONFIRMADA mediante un pago electrónico
                | no puede cancelarse simplemente liberando el asiento.
                |
                | Primero debe existir una devolución real del dinero.
                |
                | Esto evita:
                |
                | Reserva CANCELADA
                | Asiento LIBERADO
                | Pago todavía APROBADO
                |
                */

                if (
                    $reservaBloqueada->estado
                    === EstadoReserva::CONFIRMADA

                    &&

                    $reservaBloqueada->pago_confirmacion_id
                    !== null
                ) {

                    $pagoConfirmacion =
                        Pago::query()

                            ->whereKey(
                                $reservaBloqueada
                                    ->pago_confirmacion_id
                            )

                            ->lockForUpdate()

                            ->first();


                    if (
                        $pagoConfirmacion !== null

                        &&

                        $pagoConfirmacion->estado
                        === EstadoPago::APROBADO
                    ) {

                        throw new OperacionReservaInvalidaException(
                            'La reserva posee un pago electrónico aprobado. Debe procesarse el reembolso antes de cancelar la reserva.'
                        );
                    }
                }



                /*
                |--------------------------------------------------------------------------
                | Anular tickets vigentes
                |--------------------------------------------------------------------------
                |
                | En ValidarTicket el orden crítico es:
                |
                | Reserva -> Ticket -> Ocupación
                |
                | Conservamos aquí el mismo orden.
                |
                | Si posteriormente falla la cancelación,
                | la transacción exterior también revertirá
                | la anulación de los tickets.
                |
                */

                $motivoAnulacionTicket =
                    $motivo !== null
                    && trim(
                        $motivo
                    ) !== ''

                        ? 'Reserva cancelada: '
                            .trim(
                                $motivo
                            )

                        : 'Reserva cancelada.';


                $this
                    ->anularTicketsReserva
                    ->ejecutar(
                        $reservaBloqueada->id,
                        $motivoAnulacionTicket
                    );


                /*
                |--------------------------------------------------------------------------
                | 4. Bloquear ocupaciones
                |--------------------------------------------------------------------------
                |
                | Mantenemos siempre el mismo orden para
                | reducir riesgos de deadlock en PostgreSQL.
                |
                */

                $ocupaciones =
                    OcupacionAsiento::query()

                        ->where(
                            'reserva_id',
                            $reservaBloqueada->id
                        )

                        ->orderBy('id')

                        ->lockForUpdate()

                        ->get();


                if ($ocupaciones->isEmpty()) {
                    throw new OperacionReservaInvalidaException(
                        'La reserva no tiene ocupaciones de asiento asociadas.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 5. Liberar asientos
                |--------------------------------------------------------------------------
                |
                | Una reserva PENDIENTE_PAGO tendrá
                | normalmente ocupaciones RESERVADO.
                |
                | Una futura reserva CONFIRMADA tendrá
                | ocupaciones CONFIRMADO.
                |
                */

                foreach ($ocupaciones as $ocupacion) {

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


                /*
                |--------------------------------------------------------------------------
                | 6. Cambiar estado de la reserva
                |--------------------------------------------------------------------------
                */

                $reservaBloqueada->update([
                    'estado' =>
                        EstadoReserva::CANCELADA,

                    'cancelada_en' =>
                        now(),

                    'motivo_cancelacion' =>
                        $motivo !== null
                            ? trim($motivo)
                            : null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | RF-10.1 - Trazabilidad de cancelación
                |--------------------------------------------------------------------------
                */

                $this
                    ->registrarOperacionPostventa
                    ->ejecutar(
                        tipo:
                            TipoOperacionPostventa::CANCELACION,

                        origen:
                            OrigenOperacionPostventa::USUARIO,

                        reservaId:
                            $reservaBloqueada->id,

                        ejecutadoPorUsuarioId:
                            $usuario->id,

                        motivo:
                            $motivo,

                        datos: [
                            'estado_anterior' =>
                                $estadoAnterior->value,

                            'estado_nuevo' =>
                                EstadoReserva::CANCELADA->value,

                            'causa' =>
                                'CANCELACION_MANUAL',
                        ],

                        claveIdempotencia:
                            'postventa:cancelacion:reserva:'
                            .$reservaBloqueada->id
                    );


                /*
                |--------------------------------------------------------------------------
                | 7. Respuesta completa
                |--------------------------------------------------------------------------
                */

                return $reservaBloqueada
                    ->refresh()
                    ->load([
                        'viaje',

                        'cliente',

                        'creadoPor',

                        'pasajeros.ocupacionAsiento.asientoViaje',

                        'pasajeros.ocupacionAsiento.puntoOrigen',

                        'pasajeros.ocupacionAsiento.puntoDestino',
                    ]);
            }
        );
    }
}
