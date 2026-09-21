<?php

use App\Models\Punto;
use App\Models\Rol;
use App\Models\Ruta;
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

    $this->ruta = Ruta::factory()->create([
        'codigo' => 'RUT-001',
        'nombre' => 'Chiclayo - Chota',
        'duracion_estimada_minutos' => 360,
    ]);

    $this->chiclayo = Punto::factory()->create([
        'nombre' => 'Terminal Chiclayo',
    ]);

    $this->lambayeque = Punto::factory()->create([
        'nombre' => 'Agencia Lambayeque',
    ]);

    $this->olmos = Punto::factory()->create([
        'nombre' => 'Paradero Olmos',
    ]);

    $this->chota = Punto::factory()->create([
        'nombre' => 'Terminal Chota',
    ]);
});

/**
 * Genera un recorrido válido reutilizable
 * por las diferentes pruebas.
 */
function recorridoValido($test): array
{
    return [
        [
            'punto_id' => $test->chiclayo->id,
            'orden' => 1,
            'permite_embarque' => true,
            'permite_desembarque' => false,
            'minutos_desde_origen' => 0,
        ],
        [
            'punto_id' => $test->lambayeque->id,
            'orden' => 2,
            'permite_embarque' => true,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 30,
        ],
        [
            'punto_id' => $test->olmos->id,
            'orden' => 3,
            'permite_embarque' => true,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 120,
        ],
        [
            'punto_id' => $test->chota->id,
            'orden' => 4,
            'permite_embarque' => false,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 360,
        ],
    ];
}

test('administrador puede configurar recorrido valido', function () {
    $response = $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => recorridoValido($this),
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'mensaje',
            'Recorrido configurado correctamente.'
        )
        ->assertJsonCount(
            4,
            'ruta.recorrido'
        );

    $this->assertDatabaseCount(
        'puntos_ruta',
        4
    );
});

test('recorrido debe tener minimo dos puntos', function () {
    $response = $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => [
                [
                    'punto_id' => $this->chiclayo->id,
                    'orden' => 1,
                    'permite_embarque' => true,
                    'permite_desembarque' => true,
                    'minutos_desde_origen' => 0,
                ],
            ],
        ]
    );

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('puntos');
});

test('no permite puntos repetidos en el recorrido', function () {
    $recorrido = recorridoValido($this);

    $recorrido[2]['punto_id'] =
        $this->lambayeque->id;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('no permite ordenes repetidos', function () {
    $recorrido = recorridoValido($this);

    $recorrido[2]['orden'] = 2;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('orden del recorrido debe ser consecutivo', function () {
    $recorrido = recorridoValido($this);

    $recorrido[2]['orden'] = 4;
    $recorrido[3]['orden'] = 5;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('origen debe permitir embarque', function () {
    $recorrido = recorridoValido($this);

    $recorrido[0]['permite_embarque'] = false;
    $recorrido[0]['permite_desembarque'] = true;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'El punto de origen debe permitir embarque.'
        );
});

test('origen debe comenzar en minuto cero', function () {
    $recorrido = recorridoValido($this);

    $recorrido[0]['minutos_desde_origen'] = 10;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('destino debe permitir desembarque', function () {
    $recorrido = recorridoValido($this);

    $recorrido[3]['permite_embarque'] = true;
    $recorrido[3]['permite_desembarque'] = false;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'El punto de destino debe permitir desembarque.'
        );
});

test('cada punto debe permitir alguna operacion', function () {
    $recorrido = recorridoValido($this);

    $recorrido[2]['permite_embarque'] = false;
    $recorrido[2]['permite_desembarque'] = false;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('tiempos deben aumentar progresivamente', function () {
    $recorrido = recorridoValido($this);

    $recorrido[2]['minutos_desde_origen'] = 20;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('tiempo del destino debe coincidir con duracion de ruta', function () {
    $recorrido = recorridoValido($this);

    $recorrido[3]['minutos_desde_origen'] = 350;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorrido,
        ]
    )
        ->assertUnprocessable();
});

test('punto inactivo no puede agregarse al recorrido', function () {
    $this->olmos->update([
        'activo' => false,
    ]);

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => recorridoValido($this),
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'puntos.2.punto_id'
        );
});

test('configuracion invalida no elimina recorrido existente', function () {
    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => recorridoValido($this),
        ]
    )->assertOk();

    $this->assertDatabaseCount(
        'puntos_ruta',
        4
    );

    $recorridoInvalido = recorridoValido($this);

    $recorridoInvalido[3]['minutos_desde_origen'] = 350;

    $this->putJson(
        "/api/v1/rutas/{$this->ruta->id}/recorrido",
        [
            'puntos' => $recorridoInvalido,
        ]
    )->assertUnprocessable();

    /*
     * Como la validación ocurre antes de borrar el recorrido,
     * la configuración anterior debe permanecer intacta.
     */
    $this->assertDatabaseCount(
        'puntos_ruta',
        4
    );

    $this->assertDatabaseHas('puntos_ruta', [
        'ruta_id' => $this->ruta->id,
        'punto_id' => $this->chota->id,
        'orden' => 4,
        'minutos_desde_origen' => 360,
    ]);
});
