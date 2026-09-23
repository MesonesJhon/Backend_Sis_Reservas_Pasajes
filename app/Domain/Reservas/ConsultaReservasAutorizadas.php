<?php

namespace App\Domain\Reservas;

use App\Models\Reserva;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/**
 * Centraliza las reglas de visibilidad
 * de las reservas.
 *
 * No confundir:
 *
 * - permiso reservas.ver
 * - alcance de los datos
 *
 * El middleware comprueba si el usuario tiene
 * permiso para entrar al módulo.
 *
 * Esta clase determina qué reservas puede ver.
 */
class ConsultaReservasAutorizadas
{
    /**
     * Construye una consulta restringida
     * según el usuario autenticado.
     */
    public function para(
        Usuario $usuario
    ): Builder {

        $consulta = Reserva::query();


        /*
        |--------------------------------------------------------------------------
        | Administrador
        |--------------------------------------------------------------------------
        |
        | Puede consultar todas las reservas.
        |
        */

        if (
            $usuario->tieneRol(
                'ADMINISTRADOR'
            )
        ) {
            return $consulta;
        }


        /*
        |--------------------------------------------------------------------------
        | Operador
        |--------------------------------------------------------------------------
        |
        | Necesita consultar las reservas del sistema
        | para realizar tareas operativas.
        |
        | Más adelante, si implementamos agencias o sedes,
        | podremos limitar este alcance por establecimiento.
        |
        */

        if (
            $usuario->tieneRol(
                'OPERADOR'
            )
        ) {
            return $consulta;
        }


        /*
        |--------------------------------------------------------------------------
        | Cliente
        |--------------------------------------------------------------------------
        |
        | Únicamente puede consultar reservas donde
        | figure como cliente propietario.
        |
        */

        if (
            $usuario->tieneRol(
                'CLIENTE'
            )
        ) {
            return $consulta->where(
                'cliente_usuario_id',
                $usuario->id
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Cualquier otro rol
        |--------------------------------------------------------------------------
        |
        | Aunque accidentalmente llegara a disponer
        | del permiso reservas.ver, no mostramos datos.
        |
        */

        return $consulta->whereRaw(
            '1 = 0'
        );
    }


    /**
     * Determina si un usuario puede visualizar
     * una reserva concreta.
     */
    public function puedeVer(
        Usuario $usuario,
        Reserva $reserva
    ): bool {

        if (
            $usuario->tieneRol(
                'ADMINISTRADOR'
            )
        ) {
            return true;
        }


        if (
            $usuario->tieneRol(
                'OPERADOR'
            )
        ) {
            return true;
        }


        if (
            $usuario->tieneRol(
                'CLIENTE'
            )
        ) {
            return (int) $reserva->cliente_usuario_id
                === (int) $usuario->id;
        }


        return false;
    }


    /**
     * Determina si el usuario puede cancelar
     * una reserva determinada.
     *
     * Actualmente:
     *
     * - ADMINISTRADOR: cualquier reserva.
     * - OPERADOR: cualquier reserva operativa.
     * - CLIENTE: únicamente una reserva propia.
     *
     * Aunque hoy coincide con puedeVer(),
     * se mantiene como método independiente
     * porque ambas reglas podrían divergir
     * en requerimientos posteriores.
     */
    public function puedeCancelar(
        Usuario $usuario,
        Reserva $reserva
    ): bool {

        return $this->puedeVer(
            $usuario,
            $reserva
        );
    }


    /**
     * Determina si un usuario puede confirmar
     * una reserva.
     *
     * En RF-07 la confirmación representa el cierre
     * comercial de la reserva.
     *
     * Mientras todavía no exista un módulo de pagos:
     *
     * - ADMINISTRADOR puede confirmar.
     * - OPERADOR puede confirmar.
     * - CLIENTE no puede confirmar directamente.
     *
     * En un RF posterior de pagos, la Action
     * ConfirmarReserva podrá reutilizarse desde
     * el proceso interno que valide el pago.
     */
    public function puedeConfirmar(
        Usuario $usuario,
        Reserva $reserva
    ): bool {

        if (
            $usuario->tieneRol(
                'ADMINISTRADOR'
            )
        ) {
            return true;
        }


        if (
            $usuario->tieneRol(
                'OPERADOR'
            )
        ) {
            return true;
        }


        return false;
    }
}
