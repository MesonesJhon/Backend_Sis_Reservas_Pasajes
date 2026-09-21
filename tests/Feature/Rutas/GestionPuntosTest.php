<?php

use App\Models\Punto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $this->administrador = Usuario::factory()->create([
        'activo' => true,
    ]);

    $rolAdministrador = Rol::where(
        'nombre',
        'ADMINISTRADOR'
    )->firstOrFail();

    $this->administrador->roles()->attach(
        $rolAdministrador->id
    );

    $this->actingAs(
        $this->administrador,
        'sanctum'
    );
});

test('administrador puede registrar un punto', function () {
    $response = $this->postJson('/api/v1/puntos', [
        'nombre' => 'Terminal Chiclayo',
        'tipo' => 'TERMINAL',
        'departamento' => 'Lambayeque',
        'provincia' => 'Chiclayo',
        'distrito' => 'Chiclayo',
        'direccion' => 'Av. Principal 123',
        'referencia' => 'Terminal de pasajeros',
        'latitud' => -6.7713700,
        'longitud' => -79.8408800,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'mensaje',
            'Punto registrado correctamente.'
        )
        ->assertJsonPath(
            'punto.nombre',
            'Terminal Chiclayo'
        )
        ->assertJsonPath(
            'punto.tipo',
            'TERMINAL'
        )
        ->assertJsonPath(
            'punto.activo',
            true
        );

    $this->assertDatabaseHas('puntos', [
        'nombre' => 'Terminal Chiclayo',
        'tipo' => 'TERMINAL',
        'departamento' => 'Lambayeque',
        'provincia' => 'Chiclayo',
        'distrito' => 'Chiclayo',
        'activo' => true,
    ]);
});

test('se validan las coordenadas del punto', function () {
    $response = $this->postJson('/api/v1/puntos', [
        'nombre' => 'Punto inválido',
        'tipo' => 'PARADERO',
        'departamento' => 'Lambayeque',
        'provincia' => 'Chiclayo',
        'distrito' => 'Chiclayo',
        'latitud' => -100,
        'longitud' => 200,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'latitud',
            'longitud',
        ]);
});

test('no se puede registrar un tipo de punto invalido', function () {
    $response = $this->postJson('/api/v1/puntos', [
        'nombre' => 'Punto de prueba',
        'tipo' => 'AEROPUERTO',
        'departamento' => 'Lambayeque',
        'provincia' => 'Chiclayo',
        'distrito' => 'Chiclayo',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'tipo',
        ]);
});

test('administrador puede listar los puntos', function () {
    Punto::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/puntos');

    $response
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('administrador puede consultar un punto', function () {
    $punto = Punto::factory()->create([
        'nombre' => 'Terminal Chota',
    ]);

    $response = $this->getJson(
        "/api/v1/puntos/{$punto->id}"
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.nombre',
            'Terminal Chota'
        );
});

test('consultar un punto inexistente devuelve 404', function () {
    $this->getJson('/api/v1/puntos/999999')
        ->assertNotFound();
});

test('administrador puede actualizar un punto', function () {
    $punto = Punto::factory()->create();

    $response = $this->putJson(
        "/api/v1/puntos/{$punto->id}",
        [
            'nombre' => 'Terminal Actualizado',
            'tipo' => 'TERMINAL',
            'departamento' => 'Lambayeque',
            'provincia' => 'Chiclayo',
            'distrito' => 'Chiclayo',
            'direccion' => 'Nueva dirección',
            'referencia' => 'Nueva referencia',
            'latitud' => -6.7700000,
            'longitud' => -79.8400000,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'punto.nombre',
            'Terminal Actualizado'
        );

    $this->assertDatabaseHas('puntos', [
        'id' => $punto->id,
        'nombre' => 'Terminal Actualizado',
    ]);
});

test('administrador puede desactivar un punto', function () {
    $punto = Punto::factory()->create([
        'activo' => true,
    ]);

    $response = $this->patchJson(
        "/api/v1/puntos/{$punto->id}/activo",
        [
            'activo' => false,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'punto.activo',
            false
        );

    $this->assertDatabaseHas('puntos', [
        'id' => $punto->id,
        'activo' => false,
    ]);
});

test('administrador puede reactivar un punto', function () {
    $punto = Punto::factory()->create([
        'activo' => false,
    ]);

    $response = $this->patchJson(
        "/api/v1/puntos/{$punto->id}/activo",
        [
            'activo' => true,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'punto.activo',
            true
        );
});
