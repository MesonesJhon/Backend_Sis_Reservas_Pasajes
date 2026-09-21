<?php

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

test('puede configurar una tarifa para un segmento valido', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    /*
     * Eliminamos la tarifa que genera el helper para
     * probar específicamente el endpoint.
     */
    $viaje->tarifas()->delete();

    $puntos = $viaje->ruta
        ->puntosRuta()
        ->orderBy('orden')
        ->get();

    $origen = $puntos->first();
    $destino = $puntos->last();

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/tarifas",
        [
            'tarifas' => [
                [
                    'punto_origen_id' =>
                        $origen->punto_id,

                    'punto_destino_id' =>
                        $destino->punto_id,

                    'precio' => 55.50,
                ],
            ],
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'data.0.precio',
            '55.50'
        );

    $this->assertDatabaseHas('tarifas_viaje', [
        'viaje_id' => $viaje->id,
        'punto_origen_id' => $origen->punto_id,
        'punto_destino_id' => $destino->punto_id,
    ]);
});

test('no permite una tarifa con precio cero', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $puntos = $viaje->ruta
        ->puntosRuta()
        ->orderBy('orden')
        ->get();

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/tarifas",
        [
            'tarifas' => [
                [
                    'punto_origen_id' =>
                        $puntos->first()->punto_id,

                    'punto_destino_id' =>
                        $puntos->last()->punto_id,

                    'precio' => 0,
                ],
            ],
        ]
    )->assertUnprocessable();
});

test('no permite una tarifa para un segmento invertido', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $puntos = $viaje->ruta
        ->puntosRuta()
        ->orderBy('orden')
        ->get();

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/tarifas",
        [
            'tarifas' => [
                [
                    'punto_origen_id' =>
                        $puntos->last()->punto_id,

                    'punto_destino_id' =>
                        $puntos->first()->punto_id,

                    'precio' => 20,
                ],
            ],
        ]
    )->assertUnprocessable();
});

test('no permite registrar dos tarifas para el mismo segmento', function () {
    $viaje = CrearViajeProgramable::ejecutar();

    $puntos = $viaje->ruta
        ->puntosRuta()
        ->orderBy('orden')
        ->get();

    $origen = $puntos->first()->punto_id;
    $destino = $puntos->last()->punto_id;

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/tarifas",
        [
            'tarifas' => [
                [
                    'punto_origen_id' => $origen,
                    'punto_destino_id' => $destino,
                    'precio' => 50,
                ],
                [
                    'punto_origen_id' => $origen,
                    'punto_destino_id' => $destino,
                    'precio' => 60,
                ],
            ],
        ]
    )->assertUnprocessable();
});
