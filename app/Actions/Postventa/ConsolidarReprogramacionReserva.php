<?php

namespace App\Actions\Postventa;

use App\Actions\Tickets\AnularTicketsReserva;
use App\Actions\Tickets\EmitirTicketsReserva;
use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReprogramacion;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTicket;
use App\Enums\EstadoViaje;
use App\Enums\OrigenOperacionPostventa;
use App\Enums\TipoOperacionPostventa;
use App\Exceptions\OperacionReprogramacionInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use App\Models\PasajeroReserva;
use App\Models\ReprogramacionReserva;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;

/**
 * Consolida definitivamente una reprogramación.
 *
 * IMPORTANTE:
 *
 * Esta versión únicamente permite consolidar
 * cuando la diferencia tarifaria es exactamente 0.
 *
 * Las diferencias económicas y penalidades
 * serán resueltas en RF-10.3.
 */
class ConsolidarReprogramacionReserva
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $autorizacion,

        private readonly AnularTicketsReserva $anularTicketsReserva,

        private readonly EmitirTicketsReserva $emitirTicketsReserva,

        private readonly RegistrarOperacionPostventa $registrarOperacionPostventa
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        ReprogramacionReserva $reprogramacion
    ): ReprogramacionReserva {

        /*
        |--------------------------------------------------------------------------
        | Lectura inicial
        |--------------------------------------------------------------------------
        |
        | Solamente necesitamos conocer los IDs para
        | establecer posteriormente los locks.
        |
        */

        $referencia =
            ReprogramacionReserva::query()
                ->findOrFail(
                    $reprogramacion->id
                );


        if (
            $referencia->reserva_destino_id
            === null
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'La reprogramación todavía no posee una reserva destino.'
            );
        }


        return DB::transaction(
            function () use (
                $usuario,
                $referencia
            ): ReprogramacionReserva {

                /*
                |--------------------------------------------------------------------------
                | 1. Bloquear reserva origen
                |--------------------------------------------------------------------------
                |
                | Conservamos el mismo recurso principal utilizado
                | por confirmar/cancelar/reservar.
                |
                */

                $reservaOrigen =
                    Reserva::query()

                        ->whereKey(
                            $referencia
                                ->reserva_origen_id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 2. Bloquear reserva destino
                |--------------------------------------------------------------------------
                */

                $reservaDestino =
                    Reserva::query()

                        ->whereKey(
                            $referencia
                                ->reserva_destino_id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 3. Bloquear viajes
                |--------------------------------------------------------------------------
                */

                $viajes =
                    Viaje::query()

                        ->whereIn(
                            'id',
                            [
                                $reservaOrigen
                                    ->viaje_id,

                                $reservaDestino
                                    ->viaje_id,
                            ]
                        )

                        ->orderBy(
                            'id'
                        )

                        ->lockForUpdate()

                        ->get()

                        ->keyBy(
                            'id'
                        );


                $viajeOrigen =
                    $viajes->get(
                        $reservaOrigen
                            ->viaje_id
                    );


                $viajeDestino =
                    $viajes->get(
                        $reservaDestino
                            ->viaje_id
                    );


                if (
                    $viajeOrigen === null
                    ||
                    $viajeDestino === null
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'Los viajes asociados a la reprogramación no son válidos.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 4. Bloquear proceso de reprogramación
                |--------------------------------------------------------------------------
                |
                | El orden se mantiene:
                |
                | Reserva origen
                | Reserva destino
                | Viajes
                | Reprogramación
                |
                */

                $reprogramacion =
                    ReprogramacionReserva::query()

                        ->whereKey(
                            $referencia->id
                        )

                        ->lockForUpdate()

                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | 5. Verificar que las referencias no cambiaron
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $reprogramacion
                        ->reserva_origen_id
                    !==
                    (int) $reservaOrigen->id

                    ||

                    (int) $reprogramacion
                        ->reserva_destino_id
                    !==
                    (int) $reservaDestino->id
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'Las reservas asociadas a la reprogramación cambiaron de forma inesperada.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 6. Autorización
                |--------------------------------------------------------------------------
                */

                if (
                    ! $this
                        ->autorizacion
                        ->puedeReprogramar(
                            $usuario,
                            $reservaOrigen
                        )
                ) {
                    throw new OperacionUsuarioNoPermitidaException(
                        'No puedes consolidar esta reprogramación.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 7. Idempotencia
                |--------------------------------------------------------------------------
                */

                if (
                    $reprogramacion->estado
                    === EstadoReprogramacion::COMPLETADA
                ) {
                    return $this->cargarResultado(
                        $reprogramacion
                    );
                }


                if (
                    $reprogramacion->estado
                    === EstadoReprogramacion::CANCELADA
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'Una reprogramación cancelada no puede completarse.'
                    );
                }


                if (
                    $reprogramacion->estado
                    !== EstadoReprogramacion::PENDIENTE_AJUSTE
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reprogramación todavía no se encuentra preparada para consolidarse.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 8. Estados de reservas
                |--------------------------------------------------------------------------
                */

                if (
                    $reservaOrigen->estado
                    !== EstadoReserva::CONFIRMADA
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reserva original debe encontrarse CONFIRMADA.'
                    );
                }


                if (
                    $reservaDestino->estado
                    !== EstadoReserva::PENDIENTE_PAGO
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reserva destino debe encontrarse PENDIENTE_PAGO.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 9. Propietario comercial
                |--------------------------------------------------------------------------
                */

                if (
                    (int) (
                        $reservaOrigen
                            ->cliente_usuario_id
                        ?? 0
                    )
                    !==
                    (int) (
                        $reservaDestino
                            ->cliente_usuario_id
                        ?? 0
                    )
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reserva destino no conserva al propietario de la reserva original.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 10. Estados de viaje
                |--------------------------------------------------------------------------
                |
                | Si el viaje original ya entró en EMBARCANDO,
                | la reprogramación comercial normal deja de ser válida.
                |
                */

                if (
                    $viajeOrigen->estado
                    !== EstadoViaje::PROGRAMADO
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'El viaje original ya no admite consolidar la reprogramación.'
                    );
                }


                if (
                    $viajeDestino->estado
                    !== EstadoViaje::PROGRAMADO
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'El viaje destino debe permanecer PROGRAMADO.'
                    );
                }


                if (
                    ! $viajeOrigen
                        ->salida_programada
                        ->isFuture()
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La salida del viaje original ya ocurrió.'
                    );
                }


                if (
                    ! $viajeDestino
                        ->salida_programada
                        ->isFuture()
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La salida del viaje destino ya ocurrió.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 11. Diferencia económica
                |--------------------------------------------------------------------------
                |
                | RF-10.2 solamente consolida saldo cero.
                |
                | Ejemplos:
                |
                | 50 -> 50 ✅
                | 50 -> 70 ❌ RF-10.3
                | 50 -> 40 ❌ RF-10.3
                |
                */

                $totalOrigen =
                    $this->convertirACentavos(
                        (string)
                        $reservaOrigen->total
                    );


                $totalDestino =
                    $this->convertirACentavos(
                        (string)
                        $reservaDestino->total
                    );


                $diferencia =
                    $totalDestino
                    -
                    $totalOrigen;


                if (
                    $diferencia
                    !== 0
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reprogramación posee una diferencia tarifaria pendiente de resolver.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 12. Bloquear pasajeros
                |--------------------------------------------------------------------------
                */

                $pasajeros =
                    PasajeroReserva::query()

                        ->whereIn(
                            'reserva_id',
                            [
                                $reservaOrigen->id,
                                $reservaDestino->id,
                            ]
                        )

                        ->orderBy(
                            'id'
                        )

                        ->lockForUpdate()

                        ->get();


                $pasajerosOrigen =
                    $pasajeros
                        ->where(
                            'reserva_id',
                            $reservaOrigen->id
                        )
                        ->values();


                $pasajerosDestino =
                    $pasajeros
                        ->where(
                            'reserva_id',
                            $reservaDestino->id
                        )
                        ->values();


                if (
                    $pasajerosOrigen->isEmpty()

                    ||

                    $pasajerosOrigen->count()
                    !==
                    $pasajerosDestino->count()
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'Los pasajeros de las reservas origen y destino no son consistentes.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 13. Verificar identidad de pasajeros
                |--------------------------------------------------------------------------
                */

                $documentosOrigen =
                    $pasajerosOrigen

                        ->map(
                            fn (
                                PasajeroReserva $pasajero
                            ): string =>
                                strtoupper(
                                    trim(
                                        $pasajero
                                            ->tipo_documento
                                    )
                                )
                                .'|'
                                .trim(
                                    $pasajero
                                        ->numero_documento
                                )
                        )

                        ->sort()

                        ->values()
                        ->all();


                $documentosDestino =
                    $pasajerosDestino

                        ->map(
                            fn (
                                PasajeroReserva $pasajero
                            ): string =>
                                strtoupper(
                                    trim(
                                        $pasajero
                                            ->tipo_documento
                                    )
                                )
                                .'|'
                                .trim(
                                    $pasajero
                                        ->numero_documento
                                )
                        )

                        ->sort()

                        ->values()
                        ->all();


                if (
                    $documentosOrigen
                    !==
                    $documentosDestino
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'Los pasajeros de la reserva destino no corresponden a la reserva original.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | 14. Bloquear tickets
                |--------------------------------------------------------------------------
                |
                | El destino todavía NO debe tener tickets.
                |
                */

                $tickets =
                    Ticket::query()

                        ->whereIn(
                            'reserva_id',
                            [
                                $reservaOrigen->id,
                                $reservaDestino->id,
                            ]
                        )

                        ->orderBy(
                            'id'
                        )

                        ->lockForUpdate()

                        ->get();


                $ticketsOrigen =
                    $tickets
                        ->where(
                            'reserva_id',
                            $reservaOrigen->id
                        )
                        ->values();


                $ticketsDestino =
                    $tickets
                        ->where(
                            'reserva_id',
                            $reservaDestino->id
                        )
                        ->values();


                if (
                    $ticketsOrigen->count()
                    !==
                    $pasajerosOrigen->count()
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reserva original no posee un ticket válido para cada pasajero.'
                    );
                }


                if (
                    $ticketsDestino->isNotEmpty()
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reserva destino ya posee tickets antes de ser consolidada.'
                    );
                }


                foreach (
                    $ticketsOrigen
                    as $ticket
                ) {

                    if (
                        $ticket->estado
                        === EstadoTicket::UTILIZADO
                    ) {
                        throw new OperacionReprogramacionInvalidaException(
                            'No se puede completar la reprogramación porque un ticket original ya fue utilizado.'
                        );
                    }


                    if (
                        $ticket->estado
                        !== EstadoTicket::VIGENTE
                    ) {
                        throw new OperacionReprogramacionInvalidaException(
                            'Todos los tickets originales deben permanecer vigentes para completar la reprogramación.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 15. Bloquear ocupaciones
                |--------------------------------------------------------------------------
                */

                $ocupaciones =
                    OcupacionAsiento::query()

                        ->whereIn(
                            'reserva_id',
                            [
                                $reservaOrigen->id,
                                $reservaDestino->id,
                            ]
                        )

                        ->orderBy(
                            'id'
                        )

                        ->lockForUpdate()

                        ->get();


                $ocupacionesOrigen =
                    $ocupaciones
                        ->where(
                            'reserva_id',
                            $reservaOrigen->id
                        )
                        ->values();


                $ocupacionesDestino =
                    $ocupaciones
                        ->where(
                            'reserva_id',
                            $reservaDestino->id
                        )
                        ->values();


                if (
                    $ocupacionesOrigen->count()
                    !==
                    $pasajerosOrigen->count()

                    ||

                    $ocupacionesDestino->count()
                    !==
                    $pasajerosDestino->count()
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'Las ocupaciones de la reprogramación no son consistentes con sus pasajeros.'
                    );
                }


                foreach (
                    $ocupacionesOrigen
                    as $ocupacion
                ) {

                    if (
                        $ocupacion->estado
                        !== EstadoOcupacionAsiento::CONFIRMADO
                    ) {
                        throw new OperacionReprogramacionInvalidaException(
                            'Las ocupaciones originales deben permanecer CONFIRMADAS.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | 16. Validar reserva destino todavía vigente
                |--------------------------------------------------------------------------
                */

                if (
                    $reservaDestino->expira_en === null

                    ||

                    $reservaDestino
                        ->expira_en
                        ->lte(
                            now()
                        )
                ) {
                    throw new OperacionReprogramacionInvalidaException(
                        'La reserva destino expiró antes de completar la reprogramación.'
                    );
                }


                foreach (
                    $ocupacionesDestino
                    as $ocupacion
                ) {

                    if (
                        $ocupacion->estado
                        !== EstadoOcupacionAsiento::RESERVADO
                    ) {
                        throw new OperacionReprogramacionInvalidaException(
                            'Las nuevas ocupaciones deben permanecer RESERVADAS.'
                        );
                    }


                    if (
                        $ocupacion->expira_en === null

                        ||

                        $ocupacion
                            ->expira_en
                            ->lte(
                                now()
                            )
                    ) {
                        throw new OperacionReprogramacionInvalidaException(
                            'Una de las nuevas ocupaciones expiró antes de consolidar la reprogramación.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | TODAS LAS VALIDACIONES TERMINARON
                |--------------------------------------------------------------------------
                |
                | A partir de aquí recién modificamos datos.
                |
                */


                /*
                |--------------------------------------------------------------------------
                | 17. Anular tickets originales
                |--------------------------------------------------------------------------
                */

                $this
                    ->anularTicketsReserva
                    ->ejecutar(
                        $reservaOrigen->id,

                        'Ticket anulado por reprogramación hacia la reserva '
                        .$reservaDestino->codigo
                        .'.'
                    );


                /*
                |--------------------------------------------------------------------------
                | 18. Liberar ocupaciones originales
                |--------------------------------------------------------------------------
                */

                foreach (
                    $ocupacionesOrigen
                    as $ocupacion
                ) {

                    $ocupacion->update([
                        'estado' =>
                            EstadoOcupacionAsiento::LIBERADO,

                        'expira_en' =>
                            null,
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | 19. Cancelar reserva original
                |--------------------------------------------------------------------------
                |
                | NO utilizamos CancelarReserva.
                |
                | Una reprogramación no representa un reembolso.
                |
                | Si la reserva original fue pagada electrónicamente,
                | el pago debe permanecer APROBADO y asociado a su
                | operación histórica.
                |
                */

                $reservaOrigen->update([
                    'estado' =>
                        EstadoReserva::CANCELADA,

                    'cancelada_en' =>
                        now(),

                    'motivo_cancelacion' =>
                        'Reserva reemplazada por reprogramación '
                        .$reprogramacion->codigo
                        .' hacia '
                        .$reservaDestino->codigo
                        .'',
                ]);


                /*
                |--------------------------------------------------------------------------
                | 20. Confirmar nuevas ocupaciones
                |--------------------------------------------------------------------------
                */

                foreach (
                    $ocupacionesDestino
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
                | 21. Confirmar reserva destino
                |--------------------------------------------------------------------------
                |
                | Tampoco utilizamos ConfirmarReserva porque:
                |
                | - un CLIENTE sí puede completar una
                |   reprogramación propia;
                |
                | - la reserva no está siendo confirmada
                |   por un nuevo pago normal;
                |
                | - su valor comercial proviene de la
                |   reserva original.
                |
                */

                $momentoCompletado =
                    now();


                $reservaDestino->update([
                    'estado' =>
                        EstadoReserva::CONFIRMADA,

                    'confirmada_en' =>
                        $momentoCompletado,

                    'expira_en' =>
                        null,
                ]);


                /*
                |--------------------------------------------------------------------------
                | 22. Emitir nuevos tickets
                |--------------------------------------------------------------------------
                */

                $this
                    ->emitirTicketsReserva
                    ->ejecutar(
                        $reservaDestino->id
                    );


                /*
                |--------------------------------------------------------------------------
                | 23. Completar reprogramación
                |--------------------------------------------------------------------------
                */

                $reprogramacion->update([
                    'estado' =>
                        EstadoReprogramacion::COMPLETADA,

                    'completada_por_usuario_id' =>
                        $usuario->id,

                    'completada_en' =>
                        $momentoCompletado,
                ]);


                /*
                |--------------------------------------------------------------------------
                | 24. Trazabilidad RF-10.1
                |--------------------------------------------------------------------------
                |
                | NO registramos CANCELACION como operación postventa
                | adicional.
                |
                | La cancelación de la reserva origen es una
                | consecuencia de REPROGRAMACION.
                |
                */

                $this
                    ->registrarOperacionPostventa
                    ->ejecutar(
                        tipo:
                            TipoOperacionPostventa::REPROGRAMACION,

                        origen:
                            OrigenOperacionPostventa::USUARIO,

                        reservaId:
                            $reservaOrigen->id,

                        ejecutadoPorUsuarioId:
                            $usuario->id,

                        motivo:
                            $reprogramacion->motivo,

                        datos: [

                            'reprogramacion_id' =>
                                $reprogramacion->id,

                            'reprogramacion_codigo' =>
                                $reprogramacion->codigo,


                            'reserva_origen_id' =>
                                $reservaOrigen->id,

                            'reserva_origen_codigo' =>
                                $reservaOrigen->codigo,

                            'viaje_origen_id' =>
                                $reservaOrigen->viaje_id,

                            'total_original' =>
                                (string)
                                $reservaOrigen->total,


                            'reserva_destino_id' =>
                                $reservaDestino->id,

                            'reserva_destino_codigo' =>
                                $reservaDestino->codigo,

                            'viaje_destino_id' =>
                                $reservaDestino->viaje_id,

                            'total_nuevo' =>
                                (string)
                                $reservaDestino->total,


                            'diferencia_tarifaria' =>
                                '0.00',
                        ],

                        claveIdempotencia:
                            'postventa:reprogramacion:'
                            .$reprogramacion->id
                            .':completada',

                        ocurrioEn:
                            $momentoCompletado
                    );


                /*
                |--------------------------------------------------------------------------
                | 25. Resultado
                |--------------------------------------------------------------------------
                */

                return $this->cargarResultado(
                    $reprogramacion
                );
            }
        );
    }


    private function cargarResultado(
        ReprogramacionReserva $reprogramacion
    ): ReprogramacionReserva {

        return $reprogramacion
            ->refresh()
            ->load([

                'reservaOrigen' =>
                    fn ($consulta) =>
                        $consulta
                            ->with([
                                'pasajeros.ocupacionAsiento',
                                'tickets',
                            ]),

                'reservaDestino' =>
                    fn ($consulta) =>
                        $consulta
                            ->with([
                                'pasajeros.ocupacionAsiento.asientoViaje',
                                'tickets',
                            ]),

                'solicitadaPor',

                'completadaPor',
            ]);
    }


    private function convertirACentavos(
        string $monto
    ): int {

        $monto =
            trim(
                $monto
            );


        if (
            ! preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $monto
            )
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'Uno de los importes de la reprogramación no es válido.'
            );
        }


        [
            $entero,
            $decimales,
        ] =
            array_pad(
                explode(
                    '.',
                    $monto,
                    2
                ),
                2,
                '0'
            );


        $decimales =
            str_pad(
                $decimales,
                2,
                '0'
            );


        return (
            (int) $entero
            * 100
        )
        +
        (int) substr(
            $decimales,
            0,
            2
        );
    }
}
