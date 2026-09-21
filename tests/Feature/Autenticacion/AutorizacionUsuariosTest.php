<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermisosSeeder::class);
});

test('un cliente autenticado no puede listar usuarios', function () {
    $cliente = Usuario::create([
        'nombres' => 'Cliente',
        'apellidos' => 'Prueba',
        'correo' => 'cliente@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolCliente = Rol::where('nombre', 'CLIENTE')->firstOrFail();

    $cliente->roles()->attach($rolCliente->id);

    Sanctum::actingAs($cliente);

    $this->getJson('/api/v1/usuarios')
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'usuarios.ver'
        );
});

test('un administrador autenticado puede listar usuarios', function () {
    $administrador = Usuario::create([
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

    $administrador->roles()->attach(
        $rolAdministrador->id
    );

    Sanctum::actingAs($administrador);

    $this->getJson('/api/v1/usuarios')
        ->assertOk();
});
