<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermisosSeeder::class);

    $this->administrador = Usuario::create([
        'nombres' => 'Administrador',
        'apellidos' => 'Prueba',
        'correo' => 'admin@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolAdministrador = Rol::where(
        'nombre',
        'ADMINISTRADOR'
    )->firstOrFail();

    $this->administrador->roles()->attach(
        $rolAdministrador->id
    );

    Sanctum::actingAs($this->administrador);
});

test('un administrador puede crear un operador', function () {
    $respuesta = $this->postJson('/api/v1/usuarios', [
        'nombres' => 'Carlos',
        'apellidos' => 'Ramirez',
        'correo' => 'operador@example.com',
        'contrasena' => 'Operador123!',
        'contrasena_confirmation' => 'Operador123!',
        'rol' => 'OPERADOR',
    ]);

    $respuesta
        ->assertCreated()
        ->assertJsonPath(
            'usuario.correo',
            'operador@example.com'
        )
        ->assertJsonPath(
            'usuario.roles.0',
            'OPERADOR'
        );

    $this->assertDatabaseHas('usuarios', [
        'correo' => 'operador@example.com',
        'activo' => true,
    ]);
});

test('un administrador puede cambiar un operador a conductor', function () {
    $usuario = Usuario::create([
        'nombres' => 'Carlos',
        'apellidos' => 'Ramirez',
        'correo' => 'operador@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolOperador = Rol::where('nombre', 'OPERADOR')
        ->firstOrFail();

    $usuario->roles()->attach($rolOperador->id);

    $respuesta = $this->putJson(
        "/api/v1/usuarios/{$usuario->id}/rol",
        [
            'rol' => 'CONDUCTOR',
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath(
            'usuario.roles.0',
            'CONDUCTOR'
        );

    expect(
        $usuario->fresh()->tieneRol('CONDUCTOR')
    )->toBeTrue();

    expect(
        $usuario->fresh()->tieneRol('OPERADOR')
    )->toBeFalse();
});

test('un administrador puede desactivar un operador', function () {
    $usuario = Usuario::create([
        'nombres' => 'Carlos',
        'apellidos' => 'Ramirez',
        'correo' => 'operador@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolOperador = Rol::where('nombre', 'OPERADOR')
        ->firstOrFail();

    $usuario->roles()->attach($rolOperador->id);

    $respuesta = $this->patchJson(
        "/api/v1/usuarios/{$usuario->id}/estado",
        [
            'activo' => false,
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath(
            'usuario.activo',
            false
        );

    $this->assertDatabaseHas('usuarios', [
        'id' => $usuario->id,
        'activo' => false,
    ]);
});

test('un administrador no puede desactivarse a si mismo', function () {
    $respuesta = $this->patchJson(
        "/api/v1/usuarios/{$this->administrador->id}/estado",
        [
            'activo' => false,
        ]
    );

    $respuesta
        ->assertForbidden()
        ->assertJsonPath(
            'mensaje',
            'No puede desactivar su propia cuenta.'
        );

    expect(
        $this->administrador->fresh()->activo
    )->toBeTrue();
});

test('un administrador no puede cambiar su propio rol', function () {
    $respuesta = $this->putJson(
        "/api/v1/usuarios/{$this->administrador->id}/rol",
        [
            'rol' => 'CONDUCTOR',
        ]
    );

    $respuesta
        ->assertForbidden()
        ->assertJsonPath(
            'mensaje',
            'No puede modificar el rol de su propia cuenta.'
        );

    expect(
        $this->administrador->fresh()->tieneRol('ADMINISTRADOR')
    )->toBeTrue();
});

test('un administrador no puede desactivar otra cuenta administrativa', function () {
    $otroAdministrador = Usuario::create([
        'nombres' => 'Segundo',
        'apellidos' => 'Administrador',
        'correo' => 'admin2@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolAdministrador = Rol::where(
        'nombre',
        'ADMINISTRADOR'
    )->firstOrFail();

    $otroAdministrador->roles()->attach(
        $rolAdministrador->id
    );

    $respuesta = $this->patchJson(
        "/api/v1/usuarios/{$otroAdministrador->id}/estado",
        [
            'activo' => false,
        ]
    );

    $respuesta
        ->assertForbidden()
        ->assertJsonPath(
            'mensaje',
            'No se puede desactivar una cuenta administrativa protegida.'
        );

    expect(
        $otroAdministrador->fresh()->activo
    )->toBeTrue();
});


test('un administrador no puede cambiar el rol de otra cuenta administrativa', function () {
    $otroAdministrador = Usuario::create([
        'nombres' => 'Segundo',
        'apellidos' => 'Administrador',
        'correo' => 'admin2@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolAdministrador = Rol::where(
        'nombre',
        'ADMINISTRADOR'
    )->firstOrFail();

    $otroAdministrador->roles()->attach(
        $rolAdministrador->id
    );

    $respuesta = $this->putJson(
        "/api/v1/usuarios/{$otroAdministrador->id}/rol",
        [
            'rol' => 'CONDUCTOR',
        ]
    );

    $respuesta
        ->assertForbidden();

    expect(
        $otroAdministrador->fresh()->tieneRol('ADMINISTRADOR')
    )->toBeTrue();
});
