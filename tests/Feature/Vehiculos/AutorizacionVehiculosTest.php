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
    $this->seed([
        RolesPermisosSeeder::class,
        TiposVehiculoSeeder::class,
    ]);

    $this->tipoBus = TipoVehiculo::where(
        'nombre',
        'BUS'
    )->firstOrFail();

    $this->vehiculo = Vehiculo::create([
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
});

test('listar vehiculos sin autenticacion devuelve 401', function () {
    $this->getJson('/api/v1/vehiculos')
        ->assertUnauthorized();
});

test('configurar asientos sin autenticacion devuelve 401', function () {
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                asientoValidoAutorizacion(),
            ],
        ]
    )->assertUnauthorized();
});

test('un operador puede consultar vehiculos', function () {
    $operador = crearUsuarioConRolVehiculo('OPERADOR');

    Sanctum::actingAs($operador);

    $this->getJson('/api/v1/vehiculos')
        ->assertOk();
});

test('un operador no puede crear vehiculos', function () {
    $operador = crearUsuarioConRolVehiculo('OPERADOR');

    Sanctum::actingAs($operador);

    $this->postJson('/api/v1/vehiculos', [
        'tipo_vehiculo_id' => $this->tipoBus->id,
        'placa' => 'ABC-123',
        'codigo_interno' => 'BUS-002',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 40,
        'numero_pisos' => 1,
    ])
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'vehiculos.crear'
        );
});

test('un operador puede consultar asientos', function () {
    $operador = crearUsuarioConRolVehiculo('OPERADOR');

    Sanctum::actingAs($operador);

    $this->getJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/asientos"
    )->assertOk();
});

test('un operador no puede configurar asientos', function () {
    $operador = crearUsuarioConRolVehiculo('OPERADOR');

    Sanctum::actingAs($operador);

    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                asientoValidoAutorizacion(),
            ],
        ]
    )
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'asientos.configurar'
        );
});

test('un conductor puede consultar vehiculos', function () {
    $conductor = crearUsuarioConRolVehiculo('CONDUCTOR');

    Sanctum::actingAs($conductor);

    $this->getJson('/api/v1/vehiculos')
        ->assertOk();
});

test('un conductor no puede modificar el estado de un vehiculo', function () {
    $conductor = crearUsuarioConRolVehiculo('CONDUCTOR');

    Sanctum::actingAs($conductor);

    $this->patchJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/estado",
        [
            'estado' => 'MANTENIMIENTO',
        ]
    )
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'vehiculos.cambiar_estado'
        );
});

test('un cliente no puede acceder al listado administrativo de vehiculos', function () {
    $cliente = crearUsuarioConRolVehiculo('CLIENTE');

    Sanctum::actingAs($cliente);

    $this->getJson('/api/v1/vehiculos')
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'vehiculos.ver'
        );
});

test('un cliente no puede consultar la configuracion administrativa de asientos', function () {
    $cliente = crearUsuarioConRolVehiculo('CLIENTE');

    Sanctum::actingAs($cliente);

    $this->getJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/asientos"
    )
        ->assertForbidden()
        ->assertJsonPath(
            'permiso_requerido',
            'asientos.ver'
        );
});

/**
 * Crea un usuario con el rol solicitado para comprobar
 * la matriz de autorización del RF-02.
 */
function crearUsuarioConRolVehiculo(string $nombreRol): Usuario
{
    static $secuencia = 0;

    $secuencia++;

    $usuario = Usuario::create([
        'nombres' => 'Usuario',
        'apellidos' => $nombreRol,
        'correo' => strtolower($nombreRol)
            . $secuencia
            . '@example.com',
        'contrasena' => 'Password123!',
        'activo' => true,
    ]);

    $rol = Rol::where('nombre', $nombreRol)
        ->firstOrFail();

    $usuario->roles()->attach($rol->id);

    return $usuario;
}

function asientoValidoAutorizacion(): array
{
    return [
        'codigo' => '01',
        'numero' => 1,
        'piso' => 1,
        'fila' => 1,
        'columna' => 1,
        'tipo' => 'NORMAL',
    ];
}
