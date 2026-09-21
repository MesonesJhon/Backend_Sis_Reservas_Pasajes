<?php

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
});

test('administrador puede registrar una ruta', function () {
    $response = $this->postJson('/api/v1/rutas', [
        'codigo' => 'rut-001',
        'nombre' => 'Chiclayo - Chota',
        'duracion_estimada_minutos' => 360,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'ruta.codigo',
            'RUT-001'
        )
        ->assertJsonPath(
            'ruta.nombre',
            'Chiclayo - Chota'
        )
        ->assertJsonPath(
            'ruta.duracion_estimada_minutos',
            360
        )
        ->assertJsonPath(
            'ruta.activo',
            true
        );

    $this->assertDatabaseHas('rutas', [
        'codigo' => 'RUT-001',
        'nombre' => 'Chiclayo - Chota',
        'duracion_estimada_minutos' => 360,
        'activo' => true,
    ]);
});

test('codigo de ruta se normaliza a mayusculas', function () {
    $this->postJson('/api/v1/rutas', [
        'codigo' => '  rut-100  ',
        'nombre' => 'Ruta de prueba',
        'duracion_estimada_minutos' => 120,
    ])->assertCreated();

    $this->assertDatabaseHas('rutas', [
        'codigo' => 'RUT-100',
    ]);
});

test('no se puede registrar un codigo de ruta duplicado', function () {
    Ruta::factory()->create([
        'codigo' => 'RUT-001',
    ]);

    $response = $this->postJson('/api/v1/rutas', [
        'codigo' => 'RUT-001',
        'nombre' => 'Ruta duplicada',
        'duracion_estimada_minutos' => 300,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'codigo',
        ]);
});

test('duracion de ruta debe ser mayor que cero', function () {
    $response = $this->postJson('/api/v1/rutas', [
        'codigo' => 'RUT-002',
        'nombre' => 'Ruta inválida',
        'duracion_estimada_minutos' => 0,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'duracion_estimada_minutos',
        ]);
});

test('administrador puede listar rutas', function () {
    Ruta::factory()->count(3)->create();

    $this->getJson('/api/v1/rutas')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('administrador puede consultar una ruta', function () {
    $ruta = Ruta::factory()->create([
        'nombre' => 'Chiclayo - Chota',
    ]);

    $this->getJson(
        "/api/v1/rutas/{$ruta->id}"
    )
        ->assertOk()
        ->assertJsonPath(
            'data.nombre',
            'Chiclayo - Chota'
        );
});

test('consultar ruta inexistente devuelve 404', function () {
    $this->getJson('/api/v1/rutas/999999')
        ->assertNotFound();
});

test('administrador puede actualizar una ruta', function () {
    $ruta = Ruta::factory()->create([
        'codigo' => 'RUT-001',
        'duracion_estimada_minutos' => 360,
    ]);

    $response = $this->putJson(
        "/api/v1/rutas/{$ruta->id}",
        [
            'codigo' => 'rut-002',
            'nombre' => 'Ruta actualizada',
            'duracion_estimada_minutos' => 420,
        ]
    );

    $response
        ->assertOk()
        ->assertJsonPath(
            'ruta.codigo',
            'RUT-002'
        )
        ->assertJsonPath(
            'ruta.nombre',
            'Ruta actualizada'
        );

    $this->assertDatabaseHas('rutas', [
        'id' => $ruta->id,
        'codigo' => 'RUT-002',
        'duracion_estimada_minutos' => 420,
    ]);
});

test('administrador puede desactivar una ruta', function () {
    $ruta = Ruta::factory()->create([
        'activo' => true,
    ]);

    $this->patchJson(
        "/api/v1/rutas/{$ruta->id}/activo",
        [
            'activo' => false,
        ]
    )
        ->assertOk()
        ->assertJsonPath(
            'ruta.activo',
            false
        );

    $this->assertDatabaseHas('rutas', [
        'id' => $ruta->id,
        'activo' => false,
    ]);
});


test(
    'no permite cambiar duracion si contradice recorrido configurado',
    function () {
        $ruta = Ruta::factory()->create([
            'codigo' => 'RUT-DUR',
            'duracion_estimada_minutos' => 360,
        ]);

        $origen = \App\Models\Punto::factory()->create();
        $destino = \App\Models\Punto::factory()->create();

        \App\Models\PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $origen->id,
            'orden' => 1,
            'permite_embarque' => true,
            'permite_desembarque' => false,
            'minutos_desde_origen' => 0,
        ]);

        \App\Models\PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $destino->id,
            'orden' => 2,
            'permite_embarque' => false,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 360,
        ]);

        $response = $this->putJson(
            "/api/v1/rutas/{$ruta->id}",
            [
                'codigo' => 'RUT-DUR',
                'nombre' => 'Ruta modificada',
                'duracion_estimada_minutos' => 300,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'mensaje',
                'La duración estimada debe coincidir con los minutos '
                . 'acumulados del último punto del recorrido.'
            );

        /*
         * Comprobamos que la actualización rechazada
         * tampoco haya modificado la ruta.
         */
        $this->assertDatabaseHas('rutas', [
            'id' => $ruta->id,
            'duracion_estimada_minutos' => 360,
        ]);
    }
);
