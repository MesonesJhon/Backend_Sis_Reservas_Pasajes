<?php

use App\Domain\Rutas\ValidadorSegmentoRuta;
use App\Models\Punto;
use App\Models\PuntoRuta;
use App\Models\Ruta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ruta = Ruta::factory()->create([
        'duracion_estimada_minutos' => 360,
    ]);

    $this->chiclayo = Punto::factory()->create();
    $this->lambayeque = Punto::factory()->create();
    $this->olmos = Punto::factory()->create();
    $this->chota = Punto::factory()->create();

    PuntoRuta::create([
        'ruta_id' => $this->ruta->id,
        'punto_id' => $this->chiclayo->id,
        'orden' => 1,
        'permite_embarque' => true,
        'permite_desembarque' => false,
        'minutos_desde_origen' => 0,
    ]);

    PuntoRuta::create([
        'ruta_id' => $this->ruta->id,
        'punto_id' => $this->lambayeque->id,
        'orden' => 2,
        'permite_embarque' => true,
        'permite_desembarque' => true,
        'minutos_desde_origen' => 30,
    ]);

    PuntoRuta::create([
        'ruta_id' => $this->ruta->id,
        'punto_id' => $this->olmos->id,
        'orden' => 3,
        'permite_embarque' => true,
        'permite_desembarque' => true,
        'minutos_desde_origen' => 120,
    ]);

    PuntoRuta::create([
        'ruta_id' => $this->ruta->id,
        'punto_id' => $this->chota->id,
        'orden' => 4,
        'permite_embarque' => false,
        'permite_desembarque' => true,
        'minutos_desde_origen' => 360,
    ]);

    $this->validador = app(
        ValidadorSegmentoRuta::class
    );
});

test('segmento chiclayo chota es valido', function () {
    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->chiclayo->id,
            $this->chota->id
        )
    )->toBeTrue();
});

test('segmento lambayeque chota es valido', function () {
    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->lambayeque->id,
            $this->chota->id
        )
    )->toBeTrue();
});

test('segmento inverso es invalido', function () {
    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->chota->id,
            $this->lambayeque->id
        )
    )->toBeFalse();
});

test('origen y destino iguales forman segmento invalido', function () {
    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->olmos->id,
            $this->olmos->id
        )
    )->toBeFalse();
});

test('punto que no pertenece a ruta genera segmento invalido', function () {
    $otroPunto = Punto::factory()->create();

    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->chiclayo->id,
            $otroPunto->id
        )
    )->toBeFalse();
});

test('punto sin permiso de embarque no puede ser origen', function () {
    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->chota->id,
            $this->chiclayo->id
        )
    )->toBeFalse();
});

test('punto sin permiso de desembarque no puede ser destino', function () {
    expect(
        $this->validador->esValido(
            $this->ruta,
            $this->lambayeque->id,
            $this->chiclayo->id
        )
    )->toBeFalse();
});

test('calcula duracion del recorrido completo', function () {
    $duracion = $this->validador->calcularDuracion(
        $this->ruta,
        $this->chiclayo->id,
        $this->chota->id
    );

    expect($duracion)->toBe(360);
});

test('calcula duracion de un segmento intermedio', function () {
    $duracion = $this->validador->calcularDuracion(
        $this->ruta,
        $this->lambayeque->id,
        $this->chota->id
    );

    /*
     * Chota = 360
     * Lambayeque = 30
     *
     * 360 - 30 = 330
     */
    expect($duracion)->toBe(330);
});

test('segmento invalido no tiene duracion', function () {
    $duracion = $this->validador->calcularDuracion(
        $this->ruta,
        $this->chota->id,
        $this->chiclayo->id
    );

    expect($duracion)->toBeNull();
});
