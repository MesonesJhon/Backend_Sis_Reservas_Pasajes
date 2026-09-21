<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Preparación de las pruebas
|--------------------------------------------------------------------------
|
| RefreshDatabase garantiza que cada prueba se ejecute sobre un estado
| limpio de la base de datos. Luego cargamos los roles y permisos base
| necesarios para los casos de autenticación.
|
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermisosSeeder::class);
});

test('un cliente puede registrarse y recibe el rol cliente', function () {
    $respuesta = $this->postJson(
        '/api/v1/autenticacion/registrar',
        [
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'correo' => 'juan@example.com',
            'contrasena' => 'Password123!',
            'contrasena_confirmation' => 'Password123!',
            'nombre_dispositivo' => 'Pest',
        ]
    );

    $respuesta
        ->assertCreated()
        ->assertJsonPath(
            'usuario.correo',
            'juan@example.com'
        )
        ->assertJsonPath(
            'usuario.roles.0',
            'CLIENTE'
        )
        ->assertJsonStructure([
            'mensaje',
            'usuario',
            'token',
        ]);

    $this->assertDatabaseHas('usuarios', [
        'correo' => 'juan@example.com',
        'activo' => true,
    ]);
});

test('un usuario puede iniciar sesion con credenciales correctas', function () {
    $usuario = Usuario::create([
        'nombres' => 'Juan',
        'apellidos' => 'Perez',
        'correo' => 'juan@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolCliente = Rol::where(
        'nombre',
        'CLIENTE'
    )->firstOrFail();

    $usuario->roles()->attach(
        $rolCliente->id
    );

    $respuesta = $this->postJson(
        '/api/v1/autenticacion/iniciar-sesion',
        [
            'correo' => 'juan@example.com',
            'contrasena' => 'Password123!',
            'nombre_dispositivo' => 'Pest',
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath(
            'usuario.correo',
            'juan@example.com'
        )
        ->assertJsonStructure([
            'mensaje',
            'usuario',
            'token',
        ]);
});

test('una ruta protegida devuelve 401 sin autenticacion', function () {
    $this->getJson('/api/v1/usuarios')
        ->assertUnauthorized();
});
