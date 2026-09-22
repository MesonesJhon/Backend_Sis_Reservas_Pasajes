<?php

use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
});

// function usuarioConRolViajes(string $rol): Usuario
// {
//     $usuario = Usuario::factory()->create([
//         'activo' => true,
//     ]);

//     $usuario->roles()->attach(
//         Rol::where('nombre', $rol)
//             ->firstOrFail()
//             ->id
//     );

//     return $usuario;
// }

test('una peticion sin autenticacion recibe 401', function () {
    $this->getJson('/api/v1/viajes')
        ->assertUnauthorized();
});

test('el administrador puede consultar viajes', function () {
    $admin = usuarioConRolViajes('ADMINISTRADOR');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/viajes')
        ->assertOk();
});

test('el operador puede administrar viajes', function () {
    $operador = usuarioConRolViajes('OPERADOR');

    $viaje = Viaje::factory()->create();

    $this->actingAs($operador, 'sanctum')
        ->getJson(
            "/api/v1/viajes/{$viaje->id}"
        )
        ->assertOk();
});

test('el conductor puede consultar pero no crear viajes', function () {
    $conductor = usuarioConRolViajes('CONDUCTOR');

    $this->actingAs($conductor, 'sanctum')
        ->getJson('/api/v1/viajes')
        ->assertOk();

    /*
     * El contenido del request no importa en este caso:
     * el middleware debe detener la petición antes del
     * Controller por falta del permiso viajes.crear.
     */
    $this->actingAs($conductor, 'sanctum')
        ->postJson('/api/v1/viajes', [])
        ->assertForbidden();
});

test('el cliente no puede acceder a la administracion de viajes', function () {
    $cliente = usuarioConRolViajes('CLIENTE');

    $this->actingAs($cliente, 'sanctum')
        ->getJson('/api/v1/viajes')
        ->assertForbidden();
});
