<?php

use App\Models\Punto;
use App\Models\Rol;
use App\Models\Ruta;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();

    $this->ruta = Ruta::factory()->create([
        'duracion_estimada_minutos' => 120,
    ]);

    $this->puntoOrigen = Punto::factory()->create();
    $this->puntoDestino = Punto::factory()->create();
});

function usuarioConRol(string $nombreRol): Usuario
{
    $usuario = Usuario::factory()->create([
        'activo' => true,
    ]);

    $rol = Rol::where(
        'nombre',
        $nombreRol
    )->firstOrFail();

    $usuario->roles()->attach($rol->id);

    return $usuario;
}

test('peticion sin token devuelve 401', function () {
    $this->getJson('/api/v1/rutas')
        ->assertUnauthorized();
});

test('administrador tiene acceso completo a rutas', function () {
    $administrador = usuarioConRol('ADMINISTRADOR');

    $this->actingAs(
        $administrador,
        'sanctum'
    );

    $this->getJson('/api/v1/rutas')
        ->assertOk();

    $this->postJson('/api/v1/rutas', [
        'codigo' => 'RUT-ADM',
        'nombre' => 'Ruta administrador',
        'duracion_estimada_minutos' => 100,
    ])->assertCreated();
});

test('operador puede consultar rutas pero no crearlas', function () {
    $operador = usuarioConRol('OPERADOR');

    $this->actingAs(
        $operador,
        'sanctum'
    );

    $this->getJson('/api/v1/rutas')
        ->assertOk();

    $this->getJson(
        "/api/v1/rutas/{$this->ruta->id}"
    )->assertOk();

    $this->postJson('/api/v1/rutas', [
        'codigo' => 'RUT-OPER',
        'nombre' => 'Ruta operador',
        'duracion_estimada_minutos' => 100,
    ])
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'rutas.crear'
        );
});

test('conductor puede consultar rutas pero no modificarlas', function () {
    $conductor = usuarioConRol('CONDUCTOR');

    $this->actingAs(
        $conductor,
        'sanctum'
    );

    $this->getJson('/api/v1/rutas')
        ->assertOk();

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}",
        [
            'codigo' => $this->ruta->codigo,
            'nombre' => 'Intento conductor',
            'duracion_estimada_minutos' =>
                $this->ruta->duracion_estimada_minutos,
        ]
    )
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'rutas.editar'
        );
});

test('cliente no puede acceder a administracion de rutas', function () {
    $cliente = usuarioConRol('CLIENTE');

    $this->actingAs(
        $cliente,
        'sanctum'
    );

    $this->getJson('/api/v1/rutas')
        ->assertForbidden();
});

test('operador no puede configurar recorrido', function () {
    $operador = usuarioConRol('OPERADOR');

    $this->actingAs(
        $operador,
        'sanctum'
    );

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => [
                [
                    'punto_id' =>
                        $this->puntoOrigen->id,
                    'orden' => 1,
                    'permite_embarque' => true,
                    'permite_desembarque' => false,
                    'minutos_desde_origen' => 0,
                ],
                [
                    'punto_id' =>
                        $this->puntoDestino->id,
                    'orden' => 2,
                    'permite_embarque' => false,
                    'permite_desembarque' => true,
                    'minutos_desde_origen' => 120,
                ],
            ],
        ]
    )
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'rutas.configurar_recorrido'
        );
});
