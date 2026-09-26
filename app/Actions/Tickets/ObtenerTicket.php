<?php

namespace App\Actions\Tickets;

use App\Domain\Tickets\AutorizacionTicket;
use App\Domain\Viajes\CalculadorHorarioSegmento;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Domain\Tickets\GeneradorQrTicket;

/**
 * Obtiene el detalle de un ticket después
 * de verificar que el usuario pueda consultarlo.
 */
class ObtenerTicket
{
    public function __construct(
        private readonly AutorizacionTicket $autorizacionTicket,

        private readonly CalculadorHorarioSegmento $calculadorHorario,
        private readonly GeneradorQrTicket $generadorQrTicket
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Ticket $ticket
    ): Ticket {

        /*
        |--------------------------------------------------------------------------
        | Autorización
        |--------------------------------------------------------------------------
        */

        if (
            ! $this
                ->autorizacionTicket
                ->puedeVer(
                    $usuario,
                    $ticket
                )
        ) {

            throw new OperacionUsuarioNoPermitidaException(
                'No puedes consultar este ticket.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Relaciones necesarias
        |--------------------------------------------------------------------------
        */

        $ticket->loadMissing([

            'reserva.viaje',

            'reserva.pagoConfirmacion',

            'pasajero.ocupacionAsiento.asientoViaje',

            'pasajero.ocupacionAsiento.puntoOrigen',

            'pasajero.ocupacionAsiento.puntoDestino',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Horario real del segmento
        |--------------------------------------------------------------------------
        |
        | No volvemos a implementar el cálculo.
        |
        | Reutilizamos CalculadorHorarioSegmento
        | creado anteriormente para Viajes.
        |
        */

        $this->agregarHorarioSegmento(
            $ticket
        );

        /*
        |--------------------------------------------------------------------------
        | Token seguro para QR
        |--------------------------------------------------------------------------
        |
        | No persistimos este valor.
        |
        | Se genera cuando se consulta el ticket.
        |
        */

        $ticket->setAttribute(
            'qr_token',

            $this
                ->generadorQrTicket
                ->generar(
                    $ticket
                )
        );


        return $ticket;
    }


    /**
     * Agrega información calculada únicamente
     * para la representación de la consulta.
     *
     * No se persiste en la tabla tickets.
     */
    private function agregarHorarioSegmento(
        Ticket $ticket
    ): void {

        $ocupacion =
            $ticket
                ->pasajero
                ->ocupacionAsiento;


        $horario =
            $this
                ->calculadorHorario
                ->calcular(
                    $ticket
                        ->reserva
                        ->viaje,

                    $ocupacion
                        ->punto_origen_id,

                    $ocupacion
                        ->punto_destino_id
                );


        $ticket->setAttribute(
            'horario_segmento_calculado',
            $horario
        );
    }
}
