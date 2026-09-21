<?php

namespace App\Actions\Usuarios;

use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso encargado de modificar el rol operativo
 * asignado a un usuario.
 *
 * Las cuentas administrativas se consideran protegidas
 * y no pueden modificarse mediante este flujo.
 */
class AsignarRolUsuario
{
    /**
     * Reemplaza el rol operativo del usuario después
     * de comprobar las reglas de protección.
     */
    public function ejecutar(
        Usuario $usuarioAutenticado,
        Usuario $usuarioObjetivo,
        string $nombreRol
    ): Usuario {
        $this->validarCambioRol(
            $usuarioAutenticado,
            $usuarioObjetivo
        );

        return DB::transaction(function () use (
            $usuarioObjetivo,
            $nombreRol
        ) {
            $rol = Rol::where('nombre', $nombreRol)
                ->firstOrFail();

            $usuarioObjetivo->roles()->sync([
                $rol->id,
            ]);

            /*
             * El cambio de rol modifica los permisos efectivos.
             * Se revocan las sesiones existentes para obligar al
             * usuario a autenticarse nuevamente con sus nuevos permisos.
             */
            $usuarioObjetivo->tokens()->delete();

            return $usuarioObjetivo->load('roles.permisos');
        });
    }

    /**
     * Impide modificar accidentalmente cuentas administrativas.
     */
    private function validarCambioRol(
        Usuario $usuarioAutenticado,
        Usuario $usuarioObjetivo
    ): void {
        if ($usuarioAutenticado->is($usuarioObjetivo)) {
            throw new OperacionUsuarioNoPermitidaException(
                'No puede modificar el rol de su propia cuenta.'
            );
        }

        if ($usuarioObjetivo->tieneRol('ADMINISTRADOR')) {
            throw new OperacionUsuarioNoPermitidaException(
                'No se puede modificar el rol de una cuenta administrativa protegida.'
            );
        }
    }
}
