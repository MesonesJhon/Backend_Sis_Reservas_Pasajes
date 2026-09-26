<?php

namespace App\Actions\Tickets;

use App\Domain\Tickets\AutorizacionTicket;
use App\Domain\Tickets\GeneradorQrGraficoTicket;
use App\Domain\Tickets\GeneradorQrTicket;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Ticket;
use App\Models\Usuario;

/**
 * Obtiene el QR gráfico de un ticket
 * visible para el usuario actual.
 */
class ObtenerQrTicket
{
    public function __construct(
        private readonly AutorizacionTicket $autorizacionTicket,

        private readonly GeneradorQrTicket $generadorQrTicket,

        private readonly GeneradorQrGraficoTicket $generadorQrGrafico
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Ticket $ticket
    ): string {

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
                'No puedes consultar el código QR de este ticket.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Token criptográfico
        |--------------------------------------------------------------------------
        */

        $token =
            $this
                ->generadorQrTicket
                ->generar(
                    $ticket
                );


        /*
        |--------------------------------------------------------------------------
        | Representación gráfica
        |--------------------------------------------------------------------------
        */

        return $this
            ->generadorQrGrafico
            ->generar(
                $token
            );
    }
}
