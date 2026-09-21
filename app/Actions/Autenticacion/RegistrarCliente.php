<?php

namespace App\Actions\Autenticacion;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class RegistrarCliente
{
    /**
     * Registra un nuevo usuario y le asigna automáticamente
     * el rol CLIENTE.
     */
    public function ejecutar(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'correo' => $datos['correo'],
                'contrasena' => $datos['contrasena'],
            ]);

            $rolCliente = Rol::where('nombre', 'CLIENTE')
                ->firstOrFail();

            $usuario->roles()->attach($rolCliente->id);

            return $usuario;
        });
    }
}
