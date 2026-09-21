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

    $administrador = Usuario::create([
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

    $administrador->roles()->attach(
        $rolAdministrador->id
    );

    Sanctum::actingAs($administrador);

    $tipoBus = TipoVehiculo::where(
        'nombre',
        'BUS'
    )->firstOrFail();

    $this->vehiculo = Vehiculo::create([
        'tipo_vehiculo_id' => $tipoBus->id,
        'placa' => 'T5X-923',
        'codigo_interno' => 'BUS-001',
        'marca' => 'Scania',
        'modelo' => 'K410',
        'capacidad' => 4,
        'numero_pisos' => 2,
        'estado' => 'OPERATIVO',
        'activo' => true,
    ]);
});

test('un administrador puede configurar los asientos de un vehiculo', function () {
    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => configuracionValidaAsientos(),
        ]
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath(
            'mensaje',
            'Configuración de asientos actualizada correctamente.'
        );

    $this->assertDatabaseCount('asientos', 4);

    $this->assertDatabaseHas('asientos', [
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '01',
        'piso' => 1,
        'fila' => 1,
        'columna' => 1,
    ]);
});

test('los asientos pueden distribuirse en diferentes pisos', function () {
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => configuracionValidaAsientos(),
        ]
    )->assertOk();

    $this->assertDatabaseHas('asientos', [
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '03',
        'piso' => 2,
    ]);
});

test('los asientos se consultan ordenados por piso fila y columna', function () {
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                [
                    'codigo' => '03',
                    'numero' => 3,
                    'piso' => 2,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'CAMA',
                ],
                [
                    'codigo' => '02',
                    'numero' => 2,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 2,
                    'tipo' => 'NORMAL',
                ],
                [
                    'codigo' => '01',
                    'numero' => 1,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'NORMAL',
                ],
            ],
        ]
    )->assertOk();

    $respuesta = $this->getJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/asientos"
    );

    $respuesta
        ->assertOk()
        ->assertJsonPath('data.0.codigo', '01')
        ->assertJsonPath('data.1.codigo', '02')
        ->assertJsonPath('data.2.codigo', '03');
});

test('no se puede superar la capacidad del vehiculo', function () {
    $asientos = configuracionValidaAsientos();

    $asientos[] = [
        'codigo' => '05',
        'numero' => 5,
        'piso' => 2,
        'fila' => 2,
        'columna' => 1,
        'tipo' => 'NORMAL',
    ];

    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => $asientos,
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'La configuración contiene 5 asientos, pero la capacidad máxima del vehículo es 4.'
        );

    $this->assertDatabaseCount('asientos', 0);
});

test('un asiento no puede pertenecer a un piso inexistente', function () {
    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                [
                    'codigo' => '01',
                    'numero' => 1,
                    'piso' => 3,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'NORMAL',
                ],
            ],
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'El asiento 01 pertenece al piso 3, pero el vehículo solamente tiene 2 piso(s).'
        );
});

test('dos asientos no pueden ocupar la misma posicion', function () {
    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                [
                    'codigo' => '01',
                    'numero' => 1,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'NORMAL',
                ],
                [
                    'codigo' => '02',
                    'numero' => 2,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'NORMAL',
                ],
            ],
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'Existen dos asientos en la posición piso 1, fila 1, columna 1.'
        );

    $this->assertDatabaseCount('asientos', 0);
});

test('dos asientos no pueden utilizar el mismo codigo', function () {
    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                [
                    'codigo' => '01',
                    'numero' => 1,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'NORMAL',
                ],
                [
                    'codigo' => '01',
                    'numero' => 2,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 2,
                    'tipo' => 'NORMAL',
                ],
            ],
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'asientos.1.codigo'
        );
});

test('no se puede utilizar un tipo de asiento inexistente', function () {
    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                [
                    'codigo' => '01',
                    'numero' => 1,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'ULTRA_VIP',
                ],
            ],
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'asientos.0.tipo'
        );
});

test('una nueva configuracion reemplaza completamente la anterior', function () {
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => configuracionValidaAsientos(),
        ]
    )->assertOk();

    $this->assertDatabaseCount('asientos', 4);

    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => [
                [
                    'codigo' => 'A1',
                    'numero' => 1,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 1,
                    'tipo' => 'VIP',
                ],
                [
                    'codigo' => 'A2',
                    'numero' => 2,
                    'piso' => 1,
                    'fila' => 1,
                    'columna' => 2,
                    'tipo' => 'VIP',
                ],
            ],
        ]
    )->assertOk();

    $this->assertDatabaseCount('asientos', 2);

    $this->assertDatabaseMissing('asientos', [
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '03',
    ]);

    $this->assertDatabaseHas('asientos', [
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => 'A1',
        'tipo' => 'VIP',
    ]);
});

test('una configuracion invalida no elimina la configuracion anterior', function () {
    /*
     * Primero guardamos una configuración válida.
     */
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => configuracionValidaAsientos(),
        ]
    )->assertOk();

    $this->assertDatabaseCount('asientos', 4);

    /*
     * Después intentamos reemplazarla por una configuración
     * inválida que excede la capacidad del vehículo.
     */
    $configuracionInvalida = configuracionValidaAsientos();

    $configuracionInvalida[] = [
        'codigo' => '05',
        'numero' => 5,
        'piso' => 2,
        'fila' => 2,
        'columna' => 1,
        'tipo' => 'NORMAL',
    ];

    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => $configuracionInvalida,
        ]
    )->assertUnprocessable();

    /*
     * La configuración original debe continuar intacta.
     */
    $this->assertDatabaseCount('asientos', 4);

    $this->assertDatabaseHas('asientos', [
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '01',
    ]);

    $this->assertDatabaseHas('asientos', [
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '04',
    ]);
});

test('no se puede reducir la capacidad por debajo de los asientos configurados', function () {
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => configuracionValidaAsientos(),
        ]
    )->assertOk();

    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}",
        [
            'tipo_vehiculo_id' => $this->vehiculo->tipo_vehiculo_id,
            'placa' => $this->vehiculo->placa,
            'codigo_interno' => $this->vehiculo->codigo_interno,
            'marca' => $this->vehiculo->marca,
            'modelo' => $this->vehiculo->modelo,
            'capacidad' => 3,
            'numero_pisos' => 2,
            'caracteristicas' => null,
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'La capacidad no puede reducirse a 3 porque el vehículo tiene 4 asientos configurados.'
        );

    expect($this->vehiculo->fresh()->capacidad)->toBe(4);
});

test('no se puede reducir el numero de pisos si existen asientos en un piso superior', function () {
    $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}/configuracion-asientos",
        [
            'asientos' => configuracionValidaAsientos(),
        ]
    )->assertOk();

    $respuesta = $this->putJson(
        "/api/v1/vehiculos/{$this->vehiculo->id}",
        [
            'tipo_vehiculo_id' => $this->vehiculo->tipo_vehiculo_id,
            'placa' => $this->vehiculo->placa,
            'codigo_interno' => $this->vehiculo->codigo_interno,
            'marca' => $this->vehiculo->marca,
            'modelo' => $this->vehiculo->modelo,
            'capacidad' => 4,
            'numero_pisos' => 1,
            'caracteristicas' => null,
        ]
    );

    $respuesta
        ->assertUnprocessable()
        ->assertJsonPath(
            'mensaje',
            'El vehículo tiene asientos configurados hasta el piso 2. No puede reducirse a 1 piso(s).'
        );

    expect($this->vehiculo->fresh()->numero_pisos)->toBe(2);
});

function configuracionValidaAsientos(): array
{
    return [
        [
            'codigo' => '01',
            'numero' => 1,
            'piso' => 1,
            'fila' => 1,
            'columna' => 1,
            'tipo' => 'NORMAL',
        ],
        [
            'codigo' => '02',
            'numero' => 2,
            'piso' => 1,
            'fila' => 1,
            'columna' => 2,
            'tipo' => 'NORMAL',
        ],
        [
            'codigo' => '03',
            'numero' => 3,
            'piso' => 2,
            'fila' => 1,
            'columna' => 1,
            'tipo' => 'CAMA',
        ],
        [
            'codigo' => '04',
            'numero' => 4,
            'piso' => 2,
            'fila' => 1,
            'columna' => 2,
            'tipo' => 'CAMA',
        ],
    ];
}
