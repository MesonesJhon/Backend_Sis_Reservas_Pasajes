<?php

use App\Enums\EstadoViaje;
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

test('no permite programar dos viajes superpuestos con el mismo vehiculo', function () {
    $primerViaje = CrearViajeProgramable::ejecutar(
        salida: '2026-10-10 08:00:00'
    );

    $this->patchJson(
        "/api/v1/viajes/{$primerViaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    /*
     * Reutilizamos el mismo vehículo, pero el helper
     * generará otro conductor.
     */
    $segundoViaje = CrearViajeProgramable::ejecutar(
        vehiculo: $primerViaje->vehiculo,
        salida: '2026-10-10 10:00:00'
    );

    $this->patchJson(
        "/api/v1/viajes/{$segundoViaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();

    expect(
        $segundoViaje->fresh()->estado
    )->toBe(EstadoViaje::BORRADOR);
});

test('permite reutilizar el vehiculo cuando los horarios son consecutivos', function () {
    $primerViaje = CrearViajeProgramable::ejecutar(
        salida: '2026-10-10 08:00:00'
    );

    $this->patchJson(
        "/api/v1/viajes/{$primerViaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    /*
     * El primer viaje termina exactamente a las 14:00.
     */
    $segundoViaje = CrearViajeProgramable::ejecutar(
        vehiculo: $primerViaje->vehiculo,
        salida: '2026-10-10 14:00:00'
    );

    $this->patchJson(
        "/api/v1/viajes/{$segundoViaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();
});

test('no permite programar al mismo conductor en viajes superpuestos', function () {
    $primerViaje = CrearViajeProgramable::ejecutar(
        salida: '2026-10-10 08:00:00'
    );

    $conductor = $primerViaje
        ->personal()
        ->firstOrFail()
        ->usuario;

    $this->patchJson(
        "/api/v1/viajes/{$primerViaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    /*
     * No pasamos el vehículo anterior, por lo que el helper
     * crea otro vehículo. El conflicto será exclusivamente
     * por el conductor.
     */
    $segundoViaje = CrearViajeProgramable::ejecutar(
        conductor: $conductor,
        salida: '2026-10-10 10:00:00'
    );

    $this->patchJson(
        "/api/v1/viajes/{$segundoViaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});
