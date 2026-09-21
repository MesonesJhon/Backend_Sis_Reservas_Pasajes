<?php

namespace App\Actions\Usuarios;

use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class CambiarEstadoUsuario
{
    /**
     * Cambia el estado administrativo de una cuenta.
     *
     * Protecciones:
     * - Un usuario no puede desactivarse a sí mismo.
     * - Una cuenta administrativa no puede ser desactivada.
     * - Al desactivar una cuenta se revocan sus tokens.
     */
    public function ejecutar(
        Usuario $usuarioAutenticado,
        Usuario $usuarioObjetivo,
        bool $activo
    ): Usuario {
        /*
         * Las reglas especiales solamente son necesarias
         * cuando se intenta desactivar una cuenta.
         */
        if (! $activo) {
            $this->validarDesactivacion(
                $usuarioAutenticado,
                $usuarioObjetivo
            );
        }

        return DB::transaction(function () use (
            $usuarioObjetivo,
            $activo
        ) {
            $usuarioObjetivo->update([
                'activo' => $activo,
            ]);

            /*
             * Un usuario desactivado no debe conservar
             * sesiones o tokens válidos.
             */
            if (! $activo) {
                $usuarioObjetivo->tokens()->delete();
            }

            return $usuarioObjetivo->load(
                'roles.permisos'
            );
        });
    }

    /**
     * Comprueba las reglas de protección antes de
     * permitir la desactivación de una cuenta.
     */
    private function validarDesactivacion(
        Usuario $usuarioAutenticado,
        Usuario $usuarioObjetivo
    ): void {
        if ($usuarioAutenticado->is($usuarioObjetivo)) {
            throw new OperacionUsuarioNoPermitidaException(
                'No puede desactivar su propia cuenta.'
            );
        }

        if ($usuarioObjetivo->tieneRol('ADMINISTRADOR')) {
            throw new OperacionUsuarioNoPermitidaException(
                'No se puede desactivar una cuenta administrativa protegida.'
            );
        }
    }
}
