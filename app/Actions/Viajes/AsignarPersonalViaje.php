<?php

namespace App\Actions\Viajes;

use App\Enums\FuncionPersonalViaje;
use App\Exceptions\ProgramacionViajeInvalidaException;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Support\Facades\DB;

class AsignarPersonalViaje
{
    public function ejecutar(
        Viaje $viaje,
        array $personal
    ): Viaje {
        if (! $viaje->permiteEdicion()) {
            throw new ProgramacionViajeInvalidaException(
                'El personal solo puede modificarse mientras el viaje esté en BORRADOR.'
            );
        }

        return DB::transaction(function () use ($viaje, $personal) {
            foreach ($personal as $asignacion) {
                $usuario = Usuario::query()
                    ->with('roles')
                    ->findOrFail($asignacion['usuario_id']);

                if (! $usuario->activo) {
                    throw new ProgramacionViajeInvalidaException(
                        "El usuario {$usuario->nombres} {$usuario->apellidos} se encuentra inactivo."
                    );
                }

                $funcion = FuncionPersonalViaje::from(
                    $asignacion['funcion']
                );

                /*
                 * Para las funciones de conducción exigimos
                 * que el usuario tenga el rol CONDUCTOR.
                 */
                if (
                    in_array($funcion, [
                        FuncionPersonalViaje::CONDUCTOR,
                        FuncionPersonalViaje::CONDUCTOR_AUXILIAR,
                    ], true)
                    && ! $usuario->tieneRol('CONDUCTOR')
                ) {
                    throw new ProgramacionViajeInvalidaException(
                        "El usuario {$usuario->nombres} {$usuario->apellidos} no posee el rol CONDUCTOR."
                    );
                }
            }

            /*
             * Todas las validaciones ocurren antes de eliminar
             * la configuración existente.
             */
            $viaje->personal()->delete();

            foreach ($personal as $asignacion) {
                $viaje->personal()->create([
                    'usuario_id' => $asignacion['usuario_id'],
                    'funcion' => $asignacion['funcion'],
                ]);
            }

            return $viaje->load([
                'personal.usuario',
            ]);
        });
    }
}
