<?php

namespace App\Actions\Usuarios;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso responsable de registrar usuarios internos.
 *
 * La creación del usuario y la asignación de su rol se realizan
 * dentro de una misma transacción para mantener la consistencia.
 */
class CrearUsuario
{
    /**
     * Registra el usuario y le asigna el rol solicitado.
     */
    public function ejecutar(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'correo' => $datos['correo'],
                'contrasena' => $datos['contrasena'],
                'activo' => true,
            ]);

            $rol = Rol::where('nombre', $datos['rol'])
                ->firstOrFail();

            $usuario->roles()->attach($rol->id);

            return $usuario->load('roles.permisos');
        });
    }
}
