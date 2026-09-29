<?php

namespace App\Actions\Postventa;

use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Domain\Viajes\ObtenerTarifaSegmento;
use App\Domain\Viajes\ValidadorSegmentoViaje;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReprogramacion;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTicket;
use App\Enums\EstadoViaje;
use App\Exceptions\OperacionReprogramacionInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\ReprogramacionReserva;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\Viaje;
use Exception;

/**
 * Evalúa si una reserva puede ser reprogramada
 * y calcula el importe del nuevo viaje.
 *
 * IMPORTANTE:
 *
 * Esta Action NO:
 *
 * - crea reservas;
 * - bloquea asientos;
 * - modifica tickets;
 * - cancela la reserva original;
 * - mueve dinero.
 *
 * Únicamente valida y cotiza.
 */
class CotizarReprogramacionReserva
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $autorizacion,

        private readonly ValidadorSegmentoViaje $validadorSegmento,

        private readonly ObtenerTarifaSegmento $obtenerTarifa
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Reserva $reservaOrigen,
        Viaje $viajeDestino
    ): array {

        /*
        |--------------------------------------------------------------------------
        | 1. Autorización
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
                'No puedes reprogramar esta reserva.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Recargar estado real
        |--------------------------------------------------------------------------
        */

        $reservaOrigen =
            Reserva::query()

                ->with([
                    'viaje',

                    'pasajeros.ocupacionAsiento',

                    'pasajeros.ticket',
                ])

                ->findOrFail(
                    $reservaOrigen->id
                );


        $viajeDestino =
            Viaje::query()
                ->findOrFail(
                    $viajeDestino->id
                );


        /*
        |--------------------------------------------------------------------------
        | 3. Reserva original
        |--------------------------------------------------------------------------
        */

        if (
            $reservaOrigen->estado
            !== EstadoReserva::CONFIRMADA
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'Solo una reserva confirmada puede ser reprogramada.'
            );
        }


        $viajeOrigen =
            $reservaOrigen->viaje;


        if (
            $viajeOrigen === null
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'La reserva original no posee un viaje válido.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Viaje original
        |--------------------------------------------------------------------------
        |
        | Una vez que comienza EMBARCANDO ya no permitimos
        | una reprogramación comercial normal.
        |
        */

        if (
            $viajeOrigen->estado
            !== EstadoViaje::PROGRAMADO
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'El viaje original ya no admite reprogramaciones.'
            );
        }


        if (
            ! $viajeOrigen
                ->salida_programada
                ->isFuture()
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'No se puede reprogramar una reserva cuyo viaje ya inició o cuya salida ya pasó.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. El destino debe ser otro viaje
        |--------------------------------------------------------------------------
        */

        if (
            (int) $viajeOrigen->id
            ===
            (int) $viajeDestino->id
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'El viaje destino debe ser diferente al viaje original.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Viaje destino
        |--------------------------------------------------------------------------
        */

        if (
            $viajeDestino->estado
            !== EstadoViaje::PROGRAMADO
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'El viaje destino debe encontrarse PROGRAMADO.'
            );
        }


        if (
            ! $viajeDestino
                ->salida_programada
                ->isFuture()
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'El viaje destino debe tener una salida futura.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Reprogramación activa
        |--------------------------------------------------------------------------
        |
        | CANCELADA y COMPLETADA son históricas.
        |
        | SOLICITADA o PENDIENTE_AJUSTE impiden
        | comenzar otro proceso simultáneo.
        |
        */

        $existeReprogramacionActiva =
            ReprogramacionReserva::query()

                ->where(
                    'reserva_origen_id',
                    $reservaOrigen->id
                )

                ->whereIn(
                    'estado',
                    [
                        EstadoReprogramacion::SOLICITADA->value,

                        EstadoReprogramacion::PENDIENTE_AJUSTE->value,
                    ]
                )

                ->exists();


        if (
            $existeReprogramacionActiva
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'La reserva ya posee una reprogramación activa.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 8. Pasajeros
        |--------------------------------------------------------------------------
        */

        if (
            $reservaOrigen
                ->pasajeros
                ->isEmpty()
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'La reserva no contiene pasajeros reprogramables.'
            );
        }


        $totalOriginalCentavos =
            0;


        $totalNuevoCentavos =
            0;


        $detallePasajeros =
            [];


        /*
        |--------------------------------------------------------------------------
        | 9. Evaluar cada pasajero
        |--------------------------------------------------------------------------
        */

        foreach (
            $reservaOrigen->pasajeros
            as $pasajero
        ) {

            $ocupacion =
                $pasajero
                    ->ocupacionAsiento;


            /*
            |--------------------------------------------------------------------------
            | Integridad de ocupación
            |--------------------------------------------------------------------------
            */

            if (
                $ocupacion === null
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'Uno de los pasajeros no posee una ocupación válida.'
                );
            }


            if (
                (int) $ocupacion->reserva_id
                !==
                (int) $reservaOrigen->id
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'Una ocupación no corresponde a la reserva original.'
                );
            }


            if (
                (int) $ocupacion->viaje_id
                !==
                (int) $viajeOrigen->id
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'Una ocupación no corresponde al viaje original.'
                );
            }


            if (
                $ocupacion->estado
                !== EstadoOcupacionAsiento::CONFIRMADO
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'Todos los pasajeros deben mantener una ocupación confirmada para poder reprogramar.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Ticket
            |--------------------------------------------------------------------------
            */

            $ticket =
                $pasajero->ticket;


            if (
                $ticket === null
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'Uno de los pasajeros no posee un ticket emitido.'
                );
            }


            if (
                $ticket->estado
                === EstadoTicket::UTILIZADO
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'No se puede reprogramar una reserva con tickets ya utilizados.'
                );
            }


            if (
                $ticket->estado
                !== EstadoTicket::VIGENTE
            ) {
                throw new OperacionReprogramacionInvalidaException(
                    'Todos los tickets deben encontrarse vigentes para reprogramar la reserva.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | El segmento debe existir también en el nuevo viaje
            |--------------------------------------------------------------------------
            |
            | Conservamos:
            |
            | punto de embarque
            | punto de desembarque
            |
            | Reprogramar NO significa cambiar el trayecto.
            |
            */

            try {

                $this
                    ->validadorSegmento
                    ->validar(
                        $viajeDestino,

                        $ocupacion->punto_origen_id,

                        $ocupacion->punto_destino_id
                    );

            } catch (Exception) {

                throw new OperacionReprogramacionInvalidaException(
                    'El viaje destino no permite uno de los segmentos de la reserva original.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Nueva tarifa
            |--------------------------------------------------------------------------
            */

            try {

                $tarifa =
                    $this
                        ->obtenerTarifa
                        ->obtener(
                            $viajeDestino,

                            $ocupacion->punto_origen_id,

                            $ocupacion->punto_destino_id
                        );

            } catch (Exception) {

                throw new OperacionReprogramacionInvalidaException(
                    'El viaje destino no posee una tarifa activa para uno de los segmentos.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Precio histórico vs nuevo precio
            |--------------------------------------------------------------------------
            */

            $precioOriginal =
                (string) $pasajero->precio;


            $precioNuevo =
                (string) $tarifa->precio;


            $totalOriginalCentavos +=
                $this->convertirACentavos(
                    $precioOriginal
                );


            $totalNuevoCentavos +=
                $this->convertirACentavos(
                    $precioNuevo
                );


            $detallePasajeros[] = [

                'pasajero_reserva_id' =>
                    $pasajero->id,

                'tipo_documento' =>
                    $pasajero->tipo_documento,

                'numero_documento' =>
                    $pasajero->numero_documento,

                'nombres' =>
                    $pasajero->nombres,

                'apellidos' =>
                    $pasajero->apellidos,

                'segmento' => [

                    'punto_origen_id' =>
                        $ocupacion->punto_origen_id,

                    'punto_destino_id' =>
                        $ocupacion->punto_destino_id,
                ],

                'precio_original' =>
                    $precioOriginal,

                'precio_nuevo' =>
                    $precioNuevo,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | 10. Consistencia del precio histórico
        |--------------------------------------------------------------------------
        |
        | El total de los snapshots individuales debe
        | seguir coincidiendo con reservas.total.
        |
        */

        $totalReservaCentavos =
            $this->convertirACentavos(
                (string) $reservaOrigen->total
            );


        if (
            $totalReservaCentavos
            !== $totalOriginalCentavos
        ) {
            throw new OperacionReprogramacionInvalidaException(
                'El total histórico de la reserva no coincide con los precios de sus pasajeros.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 11. Diferencia tarifaria preliminar
        |--------------------------------------------------------------------------
        |
        | > 0  : cliente deberá pagar diferencia
        | = 0  : mismo importe
        | < 0  : existe saldo a favor
        |
        | RF-10.3 aplicará posteriormente penalidades.
        |
        */

        $diferenciaCentavos =
            $totalNuevoCentavos
            -
            $totalOriginalCentavos;


        $direccionDiferencia =
            match (true) {

                $diferenciaCentavos > 0 =>
                    'A_PAGAR',

                $diferenciaCentavos < 0 =>
                    'A_FAVOR',

                default =>
                    'SIN_DIFERENCIA',
            };


        /*
        |--------------------------------------------------------------------------
        | 12. Cotización
        |--------------------------------------------------------------------------
        */

        return [

            'reserva_origen_id' =>
                $reservaOrigen->id,

            'viaje_origen_id' =>
                $viajeOrigen->id,

            'viaje_destino_id' =>
                $viajeDestino->id,

            'total_original' =>
                $this->convertirDesdeCentavos(
                    $totalOriginalCentavos
                ),

            'total_nuevo' =>
                $this->convertirDesdeCentavos(
                    $totalNuevoCentavos
                ),

            'diferencia' =>
                $this->convertirDesdeCentavosConSigno(
                    $diferenciaCentavos
                ),

            'direccion_diferencia' =>
                $direccionDiferencia,

            'pasajeros' =>
                $detallePasajeros,
        ];
    }


    /**
     * Convierte un decimal monetario
     * a centavos enteros.
     *
     * "70.25" -> 7025
     */
    private function convertirACentavos(
        string $monto
    ): int {

        $partes =
            explode(
                '.',
                $monto,
                2
            );


        $entero =
            (int) $partes[0];


        $decimales =
            $partes[1]
            ?? '0';


        $decimales =
            str_pad(
                $decimales,
                2,
                '0'
            );


        $decimales =
            substr(
                $decimales,
                0,
                2
            );


        return (
            $entero * 100
        )
        +
        (int) $decimales;
    }


    private function convertirDesdeCentavos(
        int $centavos
    ): string {

        return sprintf(
            '%d.%02d',
            intdiv(
                $centavos,
                100
            ),
            $centavos % 100
        );
    }


    private function convertirDesdeCentavosConSigno(
        int $centavos
    ): string {

        $signo =
            $centavos < 0
                ? '-'
                : '';


        $centavos =
            abs(
                $centavos
            );


        return $signo
            .sprintf(
                '%d.%02d',
                intdiv(
                    $centavos,
                    100
                ),
                $centavos % 100
            );
    }
}
