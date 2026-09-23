<?php

namespace App\Actions\Reservas;

use App\Domain\Reservas\ConsultaReservasAutorizadas;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Obtiene las reservas visibles para
 * el usuario autenticado.
 */
class ListarReservas
{
    public function __construct(
        private readonly ConsultaReservasAutorizadas $consultaAutorizada
    ) {
    }


    public function ejecutar(
        Usuario $usuario,
        array $filtros = []
    ): LengthAwarePaginator {

        /*
         * Partimos de una consulta que ya conoce
         * el alcance autorizado del usuario.
         */
        $consulta =
            $this->consultaAutorizada
                ->para(
                    $usuario
                );


        /*
        |--------------------------------------------------------------------------
        | Relaciones necesarias para la respuesta
        |--------------------------------------------------------------------------
        */

        $consulta->with([
            'viaje',

            'cliente',

            'creadoPor',

            'pasajeros.ocupacionAsiento.asientoViaje',

            'pasajeros.ocupacionAsiento.puntoOrigen',

            'pasajeros.ocupacionAsiento.puntoDestino',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Filtro por estado
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $filtros['estado']
            )
        ) {
            $consulta->where(
                'estado',
                $filtros['estado']
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Filtro por viaje
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $filtros['viaje_id']
            )
        ) {
            $consulta->where(
                'viaje_id',
                $filtros['viaje_id']
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Búsqueda por código
        |--------------------------------------------------------------------------
        |
        | Utilizamos coincidencia parcial porque
        | posteriormente el operador podrá escribir
        | solamente parte del código de reserva.
        |
        */

        if (
            ! empty(
                $filtros['codigo']
            )
        ) {
            $consulta->where(
                'codigo',
                'like',
                '%'
                .trim(
                    $filtros['codigo']
                )
                .'%'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Orden
        |--------------------------------------------------------------------------
        |
        | Las reservas más recientes primero.
        |
        */

        $consulta->orderByDesc(
            'id'
        );


        /*
        |--------------------------------------------------------------------------
        | Paginación
        |--------------------------------------------------------------------------
        */

        $porPagina =
            (int) (
                $filtros['per_page']
                ?? 15
            );


        return $consulta->paginate(
            $porPagina
        );
    }
}
