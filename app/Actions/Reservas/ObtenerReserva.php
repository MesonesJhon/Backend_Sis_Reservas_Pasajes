<?php

namespace App\Actions\Reservas;

use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Reserva;
use App\Models\Usuario;

/**
 * Obtiene el detalle completo de una reserva
 * después de validar que el usuario pueda verla.
 */
class ObtenerReserva
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $consultaAutorizada
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        Reserva $reserva
    ): Reserva {

        /*
        |--------------------------------------------------------------------------
        | Autorización por propiedad / rol
        |--------------------------------------------------------------------------
        */

        if (
            ! $this
                ->consultaAutorizada
                ->puedeVer(
                    $usuario,
                    $reserva
                )
        ) {
            throw new OperacionUsuarioNoPermitidaException(
                'No puedes consultar una reserva que pertenece a otro usuario.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Cargar detalle
        |--------------------------------------------------------------------------
        */

        return $reserva->load([
            'viaje',

            'cliente',

            'creadoPor',

            'pasajeros.ocupacionAsiento.asientoViaje',

            'pasajeros.ocupacionAsiento.puntoOrigen',

            'pasajeros.ocupacionAsiento.puntoDestino',
        ]);
    }
}
