<?php

namespace App\Actions\Reservas;

use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Exceptions\OperacionReservaInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use App\Models\Usuario;
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
        private readonly ConsultaReservasAutorizadas $autorizacion
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
