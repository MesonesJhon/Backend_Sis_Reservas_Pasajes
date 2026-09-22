<?php

namespace App\Actions\Viajes;

use App\Domain\Viajes\ValidadorProgramacionViaje;
use App\Enums\EstadoViaje;
use App\Exceptions\ProgramacionViajeInvalidaException;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;

class CambiarEstadoViaje
{
    public function __construct(
        private readonly ValidadorProgramacionViaje $validadorProgramacion,
        private readonly ConsolidarRecorridoViaje $consolidarRecorrido,
        private readonly ConsolidarAsientosViaje $consolidarAsientosViaje,
    ) {
    }

    public function ejecutar(
        Viaje $viaje,
        EstadoViaje $nuevoEstado
    ): Viaje {
        return DB::transaction(function () use (
            $viaje,
            $nuevoEstado
        ) {
            /*
             * Bloqueamos el viaje actual para evitar que dos
             * solicitudes intenten cambiar su estado al mismo tiempo.
             */
            $viaje = Viaje::query()
                ->lockForUpdate()
                ->findOrFail($viaje->id);

            /*
             * Verificamos que la transición solicitada sea válida
             * según el flujo definido en EstadoViaje.
             */
            if (
                ! $viaje->estado
                    ->puedeCambiarA($nuevoEstado)
            ) {
                throw new ProgramacionViajeInvalidaException(
                    "No se permite cambiar el viaje de {$viaje->estado->value} a {$nuevoEstado->value}."
                );
            }

            /*
             * BORRADOR -> PROGRAMADO es una transición especial.
             *
             * Antes de programar el viaje debemos comprobar que
             * todos sus recursos estén disponibles y que su
             * configuración sea válida.
             */
            if (
                $viaje->estado === EstadoViaje::BORRADOR
                && $nuevoEstado === EstadoViaje::PROGRAMADO
            ) {
                /*
                 * Bloqueamos el vehículo.
                 *
                 * Esto evita que dos viajes diferentes puedan
                 * programar simultáneamente el mismo vehículo
                 * para horarios incompatibles.
                 */
                $viaje->vehiculo()
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Obtenemos los usuarios asignados al viaje.
                 *
                 * Los ordenamos para mantener siempre el mismo
                 * orden de bloqueo y reducir el riesgo de deadlocks
                 * cuando existan varios miembros del personal.
                 */
                $usuarioIds = $viaje->personal()
                    ->pluck('usuario_id')
                    ->sort()
                    ->values();

                /*
                 * Bloqueamos los usuarios asignados.
                 *
                 * Esto evita que dos viajes con vehículos distintos
                 * puedan programar simultáneamente al mismo conductor
                 * o miembro del personal.
                 */
                if ($usuarioIds->isNotEmpty()) {
                    Usuario::query()
                        ->whereIn('id', $usuarioIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                }

                /*
                 * Una vez bloqueados los recursos involucrados,
                 * validamos toda la programación.
                 *
                 * Aquí se comprueba:
                 * - Ruta activa y configurada.
                 * - Vehículo activo y operativo.
                 * - Asientos configurados.
                 * - Disponibilidad del vehículo.
                 * - Personal válido.
                 * - Disponibilidad del personal.
                 * - Existencia de tarifas.
                 */
                $this->validadorProgramacion
                    ->validar($viaje);

                /*
                 * Si todas las validaciones fueron correctas,
                 * copiamos el recorrido actual de la ruta al viaje.
                 *
                 * De esta forma el viaje conserva su propio
                 * recorrido aunque la ruta sea modificada después.
                 */
                $this->consolidarRecorrido
                    ->ejecutar($viaje);


                /*
                * Congelamos también el inventario físico
                * de asientos disponible para este viaje.
                */
                $this->consolidarAsientosViaje
                    ->ejecutar($viaje);
            }

            /*
             * Finalmente aplicamos el nuevo estado.
             */
            $viaje->update([
                'estado' => $nuevoEstado,
            ]);

            /*
             * Recargamos toda la información necesaria para
             * devolver el viaje actualizado mediante el Resource.
             */
            return $viaje
                ->refresh()
                ->load([
                    'ruta',
                    'vehiculo',

                    'puntosViaje' => fn ($consulta) =>
                        $consulta
                            ->with('punto')
                            ->orderBy('orden'),

                    'personal.usuario',

                    'tarifas' => fn ($consulta) =>
                        $consulta->with([
                            'puntoOrigen',
                            'puntoDestino',
                        ]),
                ]);
        });
    }
}
