<?php

use App\Models\Asiento;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Database\Seeders\TiposVehiculoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TiposVehiculoSeeder::class);

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
        'capacidad' => 40,
        'numero_pisos' => 2,
        'estado' => 'OPERATIVO',
        'activo' => true,
    ]);
});

test('la base de datos impide codigos de asiento duplicados por vehiculo', function () {
    Asiento::create([
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '01',
        'numero' => 1,
        'piso' => 1,
        'fila' => 1,
        'columna' => 1,
        'tipo' => 'NORMAL',
        'activo' => true,
    ]);

    expect(fn () => Asiento::create([
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '01',
        'numero' => 2,
        'piso' => 1,
        'fila' => 1,
        'columna' => 2,
        'tipo' => 'NORMAL',
        'activo' => true,
    ]))->toThrow(QueryException::class);
});

test('la base de datos impide posiciones duplicadas por vehiculo', function () {
    Asiento::create([
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '01',
        'numero' => 1,
        'piso' => 1,
        'fila' => 1,
        'columna' => 1,
        'tipo' => 'NORMAL',
        'activo' => true,
    ]);

    expect(fn () => Asiento::create([
        'vehiculo_id' => $this->vehiculo->id,
        'codigo' => '02',
        'numero' => 2,
        'piso' => 1,
        'fila' => 1,
        'columna' => 1,
        'tipo' => 'NORMAL',
        'activo' => true,
    ]))->toThrow(QueryException::class);
});
