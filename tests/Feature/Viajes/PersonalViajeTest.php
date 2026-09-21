<?php

use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('puede asignar un conductor a un viaje borrador', function () {
    $viaje = Viaje::factory()->create();

    $conductor = Usuario::factory()->create([
        'activo' => true,
    ]);

    $conductor->roles()->attach(
        Rol::where('nombre', 'CONDUCTOR')
            ->firstOrFail()
            ->id
    );

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/personal",
        [
            'personal' => [
                [
                    'usuario_id' => $conductor->id,
                    'funcion' => 'CONDUCTOR',
                ],
            ],
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'data.0.funcion',
            'CONDUCTOR'
        );

    $this->assertDatabaseHas('personal_viaje', [
        'viaje_id' => $viaje->id,
        'usuario_id' => $conductor->id,
        'funcion' => 'CONDUCTOR',
    ]);
});

test('no permite utilizar un cliente como conductor', function () {
    $viaje = Viaje::factory()->create();

    $cliente = Usuario::factory()->create([
        'activo' => true,
    ]);

    $cliente->roles()->attach(
        Rol::where('nombre', 'CLIENTE')
            ->firstOrFail()
            ->id
    );

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/personal",
        [
            'personal' => [
                [
                    'usuario_id' => $cliente->id,
                    'funcion' => 'CONDUCTOR',
                ],
            ],
        ]
    )->assertUnprocessable();

    $this->assertDatabaseMissing('personal_viaje', [
        'viaje_id' => $viaje->id,
        'usuario_id' => $cliente->id,
    ]);
});

test('no permite asignar un conductor inactivo', function () {
    $viaje = Viaje::factory()->create();

    $conductor = Usuario::factory()->create([
        'activo' => false,
    ]);

    $conductor->roles()->attach(
        Rol::where('nombre', 'CONDUCTOR')
            ->firstOrFail()
            ->id
    );

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/personal",
        [
            'personal' => [
                [
                    'usuario_id' => $conductor->id,
                    'funcion' => 'CONDUCTOR',
                ],
            ],
        ]
    )->assertUnprocessable();
});

test('no permite repetir un usuario en el mismo viaje', function () {
    $viaje = Viaje::factory()->create();

    $conductor = Usuario::factory()->create();

    $conductor->roles()->attach(
        Rol::where('nombre', 'CONDUCTOR')
            ->firstOrFail()
            ->id
    );

    $this->putJson(
        "/api/v1/viajes/{$viaje->id}/personal",
        [
            'personal' => [
                [
                    'usuario_id' => $conductor->id,
                    'funcion' => 'CONDUCTOR',
                ],
                [
                    'usuario_id' => $conductor->id,
                    'funcion' => 'CONDUCTOR_AUXILIAR',
                ],
            ],
        ]
    )->assertUnprocessable();
});
