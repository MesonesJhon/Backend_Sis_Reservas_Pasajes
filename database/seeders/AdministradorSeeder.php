<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Registra el administrador inicial del sistema.
 *
 * Este usuario permite realizar la configuración administrativa
 * inicial y probar las operaciones protegidas durante el desarrollo.
 */
class AdministradorSeeder extends Seeder
{
    /**
     * Crea el administrador inicial y le asigna
     * el rol ADMINISTRADOR.
     */
    public function run(): void
    {
        $administrador = Usuario::firstOrCreate(
            [
                'correo' => 'admin@prueba.com',
            ],
            [
                'nombres' => 'Administrador',
                'apellidos' => 'Sistema',
                'contrasena' => 'admin+25',
                'activo' => true,
            ]
        );

        $rolAdministrador = Rol::where(
            'nombre',
            'ADMINISTRADOR'
        )->firstOrFail();

        $administrador->roles()->syncWithoutDetaching([
            $rolAdministrador->id,
        ]);
    }
}
