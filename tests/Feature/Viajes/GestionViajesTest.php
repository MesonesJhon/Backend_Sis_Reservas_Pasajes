<?php

use App\Models\Rol;
use App\Models\Ruta;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $this->administrador = Usuario::factory()->create();

    $rol = Rol::query()
        ->where('nombre', 'ADMINISTRADOR')
        ->firstOrFail();

    $this->administrador->roles()->attach($rol->id);

    $this->actingAs(
        $this->administrador,
        'sanctum'
    );
});

test('un administrador puede crear un viaje borrador', function () {
    $ruta = Ruta::factory()->create([
        'duracion_estimada_minutos' => 360,
    ]);

    $vehiculo = Vehiculo::factory()->create();

    $respuesta = $this->postJson('/api/v1/viajes', [
        'codigo' => 'via-test-001',
        'ruta_id' => $ruta->id,
        'vehiculo_id' => $vehiculo->id,
        'salida_programada' => '2026-10-10 08:00:00',
        'observaciones' => 'Viaje de prueba',
    ]);

    $respuesta
        ->assertCreated()
        ->assertJsonPath(
            'data.codigo',
            'VIA-TEST-001'
        )
        ->assertJsonPath(
            'data.estado',
            'BORRADOR'
        )
        ->assertJsonPath(
            'data.salida_programada',
            '2026-10-10 08:00:00'
        )
        ->assertJsonPath(
            'data.llegada_estimada',
            '2026-10-10 14:00:00'
        );

    $this->assertDatabaseHas('viajes', [
        'codigo' => 'VIA-TEST-001',
        'estado' => 'BORRADOR',
    ]);
});

test('la llegada se calcula utilizando la duracion de la ruta', function () {
    $ruta = Ruta::factory()->create([
        'duracion_estimada_minutos' => 180,
    ]);

    $vehiculo = Vehiculo::factory()->create();

    $respuesta = $this->postJson('/api/v1/viajes', [
        'codigo' => 'VIA-002',
        'ruta_id' => $ruta->id,
        'vehiculo_id' => $vehiculo->id,
        'salida_programada' => '2026-10-10 08:00:00',
    ]);

    $respuesta
        ->assertCreated()
        ->assertJsonPath(
            'data.llegada_estimada',
            '2026-10-10 11:00:00'
        );
});

test('no permite codigos de viaje duplicados', function () {
    $ruta = Ruta::factory()->create();
    $vehiculo = Vehiculo::factory()->create();

    \App\Models\Viaje::factory()->create([
        'codigo' => 'VIA-001',
    ]);

    $this->postJson('/api/v1/viajes', [
        'codigo' => 'VIA-001',
        'ruta_id' => $ruta->id,
        'vehiculo_id' => $vehiculo->id,
        'salida_programada' => '2026-10-10 08:00:00',
    ])->assertUnprocessable();
});

test('un viaje borrador puede modificarse', function () {
    $viaje = \App\Models\Viaje::factory()->create();

    $respuesta = $this->putJson(
        "/api/v1/viajes/{$viaje->id}",
        [
            'codigo' => $viaje->codigo,
            'ruta_id' => $viaje->ruta_id,
            'vehiculo_id' => $viaje->vehiculo_id,
            'salida_programada' => '2026-11-01 10:00:00',
            'observaciones' => 'Horario modificado',
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath(
            'data.observaciones',
            'Horario modificado'
        );
});
