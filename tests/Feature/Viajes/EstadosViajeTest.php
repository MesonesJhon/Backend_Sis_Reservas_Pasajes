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

test('un viaje completo puede pasar de borrador a programado', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado' => 'PROGRAMADO',
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'data.estado',
            'PROGRAMADO'
        );

    expect(
        $viaje->fresh()->estado
    )->toBe(EstadoViaje::PROGRAMADO);
});

test('al programar se consolida el recorrido del viaje', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    expect(
        $viaje->puntosViaje()->count()
    )->toBe(0);

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado' => 'PROGRAMADO',
        ]
    )->assertOk();

    expect(
        $viaje->puntosViaje()->count()
    )->toBe(2);

    $this->assertDatabaseHas('puntos_viaje', [
        'viaje_id' => $viaje->id,
        'orden' => 1,
        'minutos_desde_origen' => 0,
    ]);

    $this->assertDatabaseHas('puntos_viaje', [
        'viaje_id' => $viaje->id,
        'orden' => 2,
        'minutos_desde_origen' => 360,
    ]);
});

test('un viaje programado puede pasar por todo el flujo operativo', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'EMBARCANDO']
    )->assertOk();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'EN_RUTA']
    )->assertOk();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'FINALIZADO']
    )
        ->assertOk()
        ->assertJsonPath(
            'data.estado',
            'FINALIZADO'
        );
});

test('no permite pasar directamente de borrador a finalizado', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado' => 'FINALIZADO',
        ]
    )->assertUnprocessable();

    expect(
        $viaje->fresh()->estado
    )->toBe(EstadoViaje::BORRADOR);
});

test('un viaje programado ya no puede modificar su configuracion', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}",
        [
            'codigo' => $viaje->codigo,
            'ruta_id' => $viaje->ruta_id,
            'vehiculo_id' => $viaje->vehiculo_id,
            'salida_programada' =>
                '2026-12-01 10:00:00',
        ]
    )->assertUnprocessable();
});

test('un viaje programado puede cancelarse', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'CANCELADO']
    )
        ->assertOk()
        ->assertJsonPath(
            'data.estado',
            'CANCELADO'
        );
});

test('un viaje finalizado no puede volver a programado', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    foreach ([
        'PROGRAMADO',
        'EMBARCANDO',
        'EN_RUTA',
        'FINALIZADO',
    ] as $estado) {
        $this->patchJson(
            "/api/v1/viajes/{$viaje->id}/estado",
            ['estado' => $estado]
        )->assertOk();
    }

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertUnprocessable();
});



test('el recorrido programado permanece aunque cambie la ruta original', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        ['estado' => 'PROGRAMADO']
    )->assertOk();

    $puntoHistorico = $viaje
        ->puntosViaje()
        ->where('orden', 2)
        ->firstOrFail();

    expect(
        $puntoHistorico->minutos_desde_origen
    )->toBe(360);

    /*
     * Simulamos una modificación posterior de la
     * configuración maestra de la ruta.
     */
    $viaje->ruta
        ->puntosRuta()
        ->where('orden', 2)
        ->update([
            'minutos_desde_origen' => 420,
        ]);

    /*
     * El snapshot del viaje debe permanecer intacto.
     */
    expect(
        $viaje
            ->puntosViaje()
            ->where('orden', 2)
            ->firstOrFail()
            ->minutos_desde_origen
    )->toBe(360);
});



test('al programar un viaje se consolida el inventario de asientos', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    expect(
        $viaje->asientosViaje()->count()
    )->toBe(0);

    $cantidadAsientosActivos = $viaje->vehiculo
        ->asientos()
        ->where('activo', true)
        ->count();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado' => 'PROGRAMADO',
        ]
    )->assertOk();

    expect(
        $viaje->asientosViaje()->count()
    )->toBe($cantidadAsientosActivos);
});


test('el inventario del viaje permanece aunque cambie el asiento fisico', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado' => 'PROGRAMADO',
        ]
    )->assertOk();

    $asientoViaje = $viaje
        ->asientosViaje()
        ->firstOrFail();

    $codigoOriginal = $asientoViaje->codigo;

    $asientoFisico = $viaje->vehiculo
        ->asientos()
        ->firstOrFail();

    $asientoFisico->update([
        'codigo' => 'MODIFICADO',
    ]);

    /*
     * Volvemos a consultar la BD.
     *
     * El snapshot no debe cambiar aunque cambie
     * la configuración física del vehículo.
     */
    expect(
        $viaje
            ->asientosViaje()
            ->findOrFail($asientoViaje->id)
            ->codigo
    )->toBe($codigoOriginal);
});
