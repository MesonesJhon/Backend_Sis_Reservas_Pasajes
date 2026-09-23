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
 * Confirma definitivamente una reserva.
 *
 * Flujo:
 *
 * Reserva:
 * PENDIENTE_PAGO -> CONFIRMADA
 *
 * Ocupaciones:
 * RESERVADO -> CONFIRMADO
 *
 * Toda la operación es atómica.
 */
class ConfirmarReserva
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $autorizacion
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Reserva $reserva
    ): Reserva {

        /*
         * Utilizamos un resultado intermedio porque,
         * si la reserva ya expiró, necesitamos:
         *
         * 1. marcarla EXPIRADA;
         * 2. liberar sus asientos;
         * 3. hacer COMMIT;
         * 4. recién después responder 422.
         *
         * Si lanzáramos la excepción dentro de la
         * transacción, Laravel haría rollback.
         */
        $resultado = DB::transaction(
            function () use (
                $usuario,
                $reserva
            ): array {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear reserva
                |--------------------------------------------------------------------------
                |
                | Impide que confirmar, cancelar y expirar
                | trabajen simultáneamente sobre la misma reserva.
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
                        ->puedeConfirmar(
                            $usuario,
                            $reservaBloqueada
                        )
                ) {
                    throw new OperacionUsuarioNoPermitidaException(
                        'No tienes autorización para confirmar esta reserva.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 3. Validar transición
                |--------------------------------------------------------------------------
                */

                if (
                    ! $reservaBloqueada
                        ->estado
                        ->puedeCambiarA(
                            EstadoReserva::CONFIRMADA
                        )
                ) {
                    throw new OperacionReservaInvalidaException(
                        'La reserva no puede confirmarse desde su estado actual.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 4. Bloquear todas las ocupaciones
                |--------------------------------------------------------------------------
                |
                | Mantener orderBy(id) ayuda a establecer
                | un orden estable de locks en PostgreSQL.
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
                | 5. Verificar integridad de ocupaciones
                |--------------------------------------------------------------------------
                |
                | Antes de modificar absolutamente nada,
                | verificamos TODAS.
                |
                | Esto evita confirmaciones parciales.
                |
                */

                foreach ($ocupaciones as $ocupacion) {

                    if (
                        $ocupacion->estado
                        !== EstadoOcupacionAsiento::RESERVADO
                    ) {
                        throw new OperacionReservaInvalidaException(
                            'Todas las ocupaciones deben encontrarse en estado RESERVADO para confirmar la reserva.'
                        );
                    }


                    /*
                     * Las reservas creadas por RF-07 deben
                     * mantener una fecha de vencimiento
                     * mientras están PENDIENTE_PAGO.
                     */
                    if ($ocupacion->expira_en === null) {
                        throw new OperacionReservaInvalidaException(
                            'Una de las ocupaciones no tiene una fecha de expiración válida.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Validar vencimiento
                |--------------------------------------------------------------------------
                */

                if ($reservaBloqueada->expira_en === null) {
                    throw new OperacionReservaInvalidaException(
                        'La reserva no tiene una fecha de expiración válida.'
                    );
                }


                /*
                 * No confiamos solamente en reserva.expira_en.
                 *
                 * También verificamos las ocupaciones
                 * individualmente como protección adicional
                 * frente a datos inconsistentes.
                 */
                $hayOcupacionExpirada =
                    $ocupaciones->contains(
                        function ($ocupacion): bool {

                            return $ocupacion
                                ->expira_en
                                ->lte(
                                    now()
                                );
                        }
                    );


                $reservaExpirada =
                    $reservaBloqueada
                        ->expira_en
                        ->lte(
                            now()
                        );


                /*
                |--------------------------------------------------------------------------
                | 7. Si venció, persistir EXPIRADA
                |--------------------------------------------------------------------------
                |
                | NO lanzamos la excepción todavía.
                |
                */

                if (
                    $reservaExpirada
                    || $hayOcupacionExpirada
                ) {

                    foreach (
                        $ocupaciones
                        as $ocupacion
                    ) {
                        $ocupacion->update([
                            'estado' =>
                                EstadoOcupacionAsiento::LIBERADO,

                            'expira_en' =>
                                null,
                        ]);
                    }


                    $reservaBloqueada->update([
                        'estado' =>
                            EstadoReserva::EXPIRADA,

                        'expirada_en' =>
                            now(),
                    ]);


                    return [
                        'expirada' =>
                            true,

                        'reserva' =>
                            $reservaBloqueada
                                ->refresh(),
                    ];
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Confirmar todas las ocupaciones
                |--------------------------------------------------------------------------
                |
                | La reserva ya fue validada completamente.
                |
                */

                foreach (
                    $ocupaciones
                    as $ocupacion
                ) {

                    $ocupacion->update([
                        'estado' =>
                            EstadoOcupacionAsiento::CONFIRMADO,

                        /*
                         * Un asiento confirmado ya no
                         * tiene vencimiento temporal.
                         */
                        'expira_en' =>
                            null,
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | 9. Confirmar reserva
                |--------------------------------------------------------------------------
                */

                $reservaBloqueada->update([
                    'estado' =>
                        EstadoReserva::CONFIRMADA,

                    'confirmada_en' =>
                        now(),

                    /*
                     * Ya no existe un vencimiento pendiente.
                     */
                    'expira_en' =>
                        null,
                ]);


                /*
                |--------------------------------------------------------------------------
                | 10. Respuesta
                |--------------------------------------------------------------------------
                */

                return [
                    'expirada' =>
                        false,

                    'reserva' =>
                        $reservaBloqueada
                            ->refresh()
                            ->load([
                                'viaje',

                                'cliente',

                                'creadoPor',

                                'pasajeros.ocupacionAsiento.asientoViaje',

                                'pasajeros.ocupacionAsiento.puntoOrigen',

                                'pasajeros.ocupacionAsiento.puntoDestino',
                            ]),
                ];
            }
        );


        /*
        |--------------------------------------------------------------------------
        | La expiración ya quedó persistida
        |--------------------------------------------------------------------------
        |
        | Aquí estamos FUERA de la transacción.
        |
        | Por lo tanto:
        |
        | EXPIRADA y LIBERADO no sufrirán rollback.
        |
        */

        if ($resultado['expirada']) {
            throw new OperacionReservaInvalidaException(
                'La reserva ya expiró y no puede confirmarse.'
            );
        }


        return $resultado['reserva'];
    }
}
