<?php

use App\Enums\EstadoVehiculo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\CrearViajeProgramable;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $admin = Usuario::factory()->create();

    $admin->roles()->attach(
        Rol::where('nombre', 'ADMINISTRADOR')
            ->firstOrFail()
            ->id
    );

    $this->actingAs($admin, 'sanctum');
});

test('no permite programar un viaje sin tarifas', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $viaje->tarifas()->delete();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});

test('no permite programar un viaje sin conductor', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $viaje->personal()->delete();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});

test('no permite programar con vehiculo inactivo', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $viaje->vehiculo->update([
        'activo' => false,
    ]);

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});

test('no permite programar con vehiculo en mantenimiento', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $viaje->vehiculo->update([
        'estado' => EstadoVehiculo::MANTENIMIENTO,
    ]);

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});

test('no permite programar con conductor inactivo', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $conductor = $viaje
        ->personal()
        ->firstOrFail()
        ->usuario;

    $conductor->update([
        'activo' => false,
    ]);

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});

test('no permite programar una ruta inactiva', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $viaje->ruta->update([
        'activo' => false,
    ]);

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});

test('no permite programar un vehiculo sin asientos activos', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $viaje->vehiculo
        ->asientos()
        ->update([
            'activo' => false,
        ]);

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});
