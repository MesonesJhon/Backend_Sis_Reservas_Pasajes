<?php

use App\Models\Punto;
use App\Models\PuntoRuta;
use App\Models\Ruta;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('codigo de ruta es unico en base de datos', function () {
    Ruta::factory()->create([
        'codigo' => 'RUT-001',
    ]);

    expect(
        fn () => Ruta::factory()->create([
            'codigo' => 'RUT-001',
        ])
    )->toThrow(QueryException::class);
});

test('orden no puede repetirse dentro de una misma ruta', function () {
    $ruta = Ruta::factory()->create();

    $puntoA = Punto::factory()->create();
    $puntoB = Punto::factory()->create();

    PuntoRuta::create([
        'ruta_id' => $ruta->id,
        'punto_id' => $puntoA->id,
        'orden' => 1,
        'permite_embarque' => true,
        'permite_desembarque' => false,
        'minutos_desde_origen' => 0,
    ]);

    expect(
        fn () => PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $puntoB->id,
            'orden' => 1,
            'permite_embarque' => true,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 30,
        ])
    )->toThrow(QueryException::class);
});

test('punto no puede repetirse dentro de la misma ruta', function () {
    $ruta = Ruta::factory()->create();

    $punto = Punto::factory()->create();

    PuntoRuta::create([
        'ruta_id' => $ruta->id,
        'punto_id' => $punto->id,
        'orden' => 1,
        'permite_embarque' => true,
        'permite_desembarque' => false,
        'minutos_desde_origen' => 0,
    ]);

    expect(
        fn () => PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $punto->id,
            'orden' => 2,
            'permite_embarque' => true,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 30,
        ])
    )->toThrow(QueryException::class);
});

test('punto utilizado por ruta no puede eliminarse fisicamente', function () {
    $ruta = Ruta::factory()->create();
    $punto = Punto::factory()->create();

    PuntoRuta::create([
        'ruta_id' => $ruta->id,
        'punto_id' => $punto->id,
        'orden' => 1,
        'permite_embarque' => true,
        'permite_desembarque' => false,
        'minutos_desde_origen' => 0,
    ]);

    expect(
        fn () => $punto->delete()
    )->toThrow(QueryException::class);
});

test('eliminar ruta elimina sus puntos ruta asociados', function () {
    $ruta = Ruta::factory()->create();

    $puntoA = Punto::factory()->create();
    $puntoB = Punto::factory()->create();

    foreach ([
        [$puntoA, 1, 0],
        [$puntoB, 2, 100],
    ] as [$punto, $orden, $minutos]) {
        PuntoRuta::create([
            'ruta_id' => $ruta->id,
            'punto_id' => $punto->id,
            'orden' => $orden,
            'permite_embarque' => true,
            'permite_desembarque' => true,
            'minutos_desde_origen' => $minutos,
        ]);
    }

    expect(
        PuntoRuta::where(
            'ruta_id',
            $ruta->id
        )->count()
    )->toBe(2);

    $ruta->delete();

    expect(
        PuntoRuta::where(
            'ruta_id',
            $ruta->id
        )->count()
    )->toBe(0);
});
