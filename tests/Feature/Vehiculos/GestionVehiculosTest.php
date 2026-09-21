<?php

use App\Models\Rol;
use App\Models\TipoVehiculo;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\TiposVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    /*
     * Cada prueba comienza con una base limpia y con los
     * catálogos/permisos necesarios para ejecutar RF-02.
     */
    $this->seed([
        RolesPermisosSeeder::class,
        TiposVehiculoSeeder::class,
    ]);

    $this->administrador = Usuario::create([
        'nombres' => 'Administrador',
        'apellidos' => 'Prueba',
        'correo' => 'admin@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rolAdministrador = Rol::where(
        'nombre',
        'ADMINISTRADOR'
    )->firstOrFail();

    $this->administrador->roles()->attach(
        $rolAdministrador->id
    );

    Sanctum::actingAs($this->administrador);

    $this->tipoBus = TipoVehiculo::where(
        'nombre',
        'BUS'
    )->firstOrFail();
});

test('un administrador puede registrar un vehiculo', function () {
    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'T5X-923',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 2,
        'caracteristicas' => [
            'wifi' => true,
            'bano' => true,
        ],
        'estado' => 'OPERATIVO',
    ]);

    $respuesta
        ->assertCreated()
        ->assertJsonPath('vehiculo.placa', 'T5X-923')
        ->assertJsonPath('vehiculo.codigo_interno', 'BUS-001')
        ->assertJsonPath('vehiculo.estado', 'OPERATIVO')
        ->assertJsonPath('vehiculo.activo', true)
        ->assertJsonPath('vehiculo.puede_operar', true);

    $this->assertDatabaseHas('vehiculos', [
        'placa' => 'T5X-923',
        'codigo_interno' => 'BUS-001',
        'capacidad' => 40,
        'numero_pisos' => 2,
        'activo' => true,
    ]);
});

test('la placa se normaliza a mayusculas al registrar un vehiculo', function () {
    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 't5x-923',
        'codigo_interno' => 'bus-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 1,
    ]);

    $respuesta
        ->assertCreated()
        ->assertJsonPath('vehiculo.placa', 'T5X-923')
        ->assertJsonPath('vehiculo.codigo_interno', 'BUS-001');
});

test('un administrador puede listar los vehiculos', function () {
    Vehiculo::create([
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'T5X-923',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 2,
        'estado' => 'OPERATIVO',
        'activo' => true,
    ]);

    $respuesta = $this->getJson('/api/v1/vehiculos');

    $respuesta
        ->assertOk()
        ->assertJsonPath('data.0.placa', 'T5X-923')
        ->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
});

test('un administrador puede consultar un vehiculo', function () {
    $vehiculo = crearVehiculoParaPrueba($this->tipoBus);

    $this->getJson("/api/v1/vehiculos/{$vehiculo->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $vehiculo->id)
        ->assertJsonPath('data.placa', 'T5X-923');
});

test('consultar un vehiculo inexistente devuelve 404', function () {
    $this->getJson('/api/v1/vehiculos/999999')
        ->assertNotFound();
});

test('un administrador puede actualizar un vehiculo', function () {
    $vehiculo = crearVehiculoParaPrueba($this->tipoBus);

    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$vehiculo->id}",
        [
            'tipo_vehiculo_id' => $this->tipoBus->id,
            'placa' => 'T5X-923',
            'codigo_interno' => 'BUS-001',
            'marca' => 'Scania',
            'modelo' => 'K410 HD',
            'capacidad' => 45,
            'numero_pisos' => 2,
            'caracteristicas' => [
                'wifi' => true,
                'tv' => true,
            ],
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath('vehiculo.modelo', 'K410 HD')
        ->assertJsonPath('vehiculo.capacidad', 45);

    $this->assertDatabaseHas('vehiculos', [
        'id' => $vehiculo->id,
        'modelo' => 'K410 HD',
        'capacidad' => 45,
    ]);
});

test('no se puede registrar una placa duplicada', function () {
    crearVehiculoParaPrueba($this->tipoBus);

    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'T5X-923',
        'codigo_interno' => 'BUS-002',
        'marca' => 'Mercedes-Benz',
        'modelo' => 'O500',
        'capacidad' => 40,
        'numero_pisos' => 1,
    ]);

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors('placa');
});

test('no se puede registrar un codigo interno duplicado', function () {
    crearVehiculoParaPrueba($this->tipoBus);

    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'ABC-123',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Mercedes-Benz',
        'modelo' => 'O500',
        'capacidad' => 40,
        'numero_pisos' => 1,
    ]);

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors('codigo_interno');
});

test('no se puede registrar un vehiculo con tipo inexistente', function () {
    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => 999999,
        'placa' => 'ABC-123',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 1,
    ]);

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tipo_vehiculo_id');
});

test('la capacidad debe ser mayor que cero', function () {
    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'ABC-123',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 0,
        'numero_pisos' => 1,
    ]);

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors('capacidad');
});

test('el numero de pisos debe ser como minimo uno', function () {
    $respuesta = $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'ABC-123',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 0,
    ]);

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors('numero_pisos');
});

test('un administrador puede cambiar el estado a mantenimiento', function () {
    $vehiculo = crearVehiculoParaPrueba($this->tipoBus);

    $respuesta = $this->patchJson(
        "/api/v1/vehiculos/{$vehiculo->id}/estado",
        [
            'estado' => 'MANTENIMIENTO',
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath('vehiculo.estado', 'MANTENIMIENTO')
        ->assertJsonPath('vehiculo.puede_operar', false);

    $this->assertDatabaseHas('vehiculos', [
        'id' => $vehiculo->id,
        'estado' => 'MANTENIMIENTO',
    ]);
});

test('no se puede asignar un estado de vehiculo inexistente', function () {
    $vehiculo = crearVehiculoParaPrueba($this->tipoBus);

    $this->patchJson(
        "/api/v1/vehiculos/{$vehiculo->id}/estado",
        [
            'estado' => 'VOLANDO',
        ]
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estado');
});

test('un administrador puede desactivar un vehiculo', function () {
    $vehiculo = crearVehiculoParaPrueba($this->tipoBus);

    $respuesta = $this->patchJson(
        "/api/v1/vehiculos/{$vehiculo->id}/activo",
        [
            'activo' => false,
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath('vehiculo.activo', false)
        ->assertJsonPath('vehiculo.puede_operar', false);

    expect($vehiculo->fresh()->activo)->toBeFalse();
});

test('un administrador puede reactivar un vehiculo', function () {
    $vehiculo = crearVehiculoParaPrueba(
        $this->tipoBus,
        activo: false
    );

    $respuesta = $this->patchJson(
        "/api/v1/vehiculos/{$vehiculo->id}/activo",
        [
            'activo' => true,
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath('vehiculo.activo', true);

    expect($vehiculo->fresh()->activo)->toBeTrue();
});

/**
 * Crea un vehículo reutilizable para los escenarios
 * funcionales del RF-02.
 */
function crearVehiculoParaPrueba(
    TipoVehiculo $tipoVehiculo,
    bool $activo = true
): Vehiculo {
    return Vehiculo::create([
        'tipo_vehiculo_id' => $tipoVehiculo->id,
        'placa' => 'T5X-923',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 2,
        'estado' => 'OPERATIVO',
        'activo' => $activo,
    ]);
}
