<?php

namespace App\Actions\Usuarios;

use App\Models\Usuario;

/**
 * Caso de uso encargado de modificar los datos
 * generales de un usuario existente.
 */
class ActualizarUsuario
{
    /**
     * Actualiza únicamente la información general permitida.
     */
    public function ejecutar(
        Usuario $usuario,
        array $datos
    ): Usuario {
        $usuario->update([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'correo' => $datos['correo'],
        ]);

        return $usuario->load('roles.permisos');
    }
}
