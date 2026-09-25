<?php

namespace App\Domain\Pagos;

use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\Pago;

/**
 * Define quién puede iniciar el pago
 * de una reserva.
 */
class AutorizacionPago
{
    public function puedeIniciar(
        Usuario $usuario,
        Reserva $reserva
    ): bool {

        /*
         * Administrador:
         * acceso operativo total.
         */
        if (
            $usuario->tieneRol(
                'ADMINISTRADOR'
            )
        ) {
            return true;
        }


        /*
         * Operador:
         * puede iniciar operaciones asociadas
         * a ventas presenciales.
         */
        if (
            $usuario->tieneRol(
                'OPERADOR'
            )
        ) {
            return true;
        }


        /*
         * Cliente:
         * solamente su propia reserva.
         */
        if (
            $usuario->tieneRol(
                'CLIENTE'
            )
        ) {
            return (int)
                $reserva->cliente_usuario_id
                === (int)
                $usuario->id;
        }


        return false;
    }


    /**
     * Determina si un usuario puede consultar
     * un intento de pago.
     */
    public function puedeVer(
        Usuario $usuario,
        Pago $pago
    ): bool {

        /*
        * Administración y operación interna.
        */
        if (
            $usuario->tieneRol('ADMINISTRADOR')
            || $usuario->tieneRol('OPERADOR')
        ) {
            return true;
        }


        /*
        * Un cliente puede consultar pagos
        * correspondientes a sus propias reservas.
        */
        if (
            $usuario->tieneRol('CLIENTE')
        ) {

            $pago->loadMissing(
                'reserva'
            );


            return (int)
                $pago
                    ->reserva
                    ->cliente_usuario_id

                ===

                (int)
                $usuario->id;
        }


        return false;
    }
}
