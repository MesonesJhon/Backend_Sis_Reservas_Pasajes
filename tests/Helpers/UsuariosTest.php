<?php

use App\Models\Usuario;
use App\Models\Rol;


/**
 * Crea un usuario asignándole un rol específico
 * para pruebas automatizadas.
 */
function usuarioConRolViajes(
    string $nombreRol
): Usuario {

    $rol = Rol::query()
        ->where('nombre', $nombreRol)
        ->firstOrFail();


    $usuario = Usuario::factory()
        ->create([
            'activo' => true,
        ]);


    $usuario
        ->roles()
        ->attach($rol->id);


    return $usuario;
}



/**
 * Autentica un usuario administrador
 * para pruebas que requieren permisos.
 */
function autenticarAdministrador(): Usuario
{

    $usuario = usuarioConRolViajes(
        'ADMINISTRADOR'
    );


    test()->actingAs(
        $usuario,
        'sanctum'
    );


    return $usuario;
}
