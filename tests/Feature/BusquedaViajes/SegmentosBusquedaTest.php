<?php

use App\Enums\EstadoViaje;
use App\Models\Viaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\CrearViajeProgramable;


uses(RefreshDatabase::class);


beforeEach(function () {

    $this->seed();

    autenticarAdministrador();

});



test('permite buscar un segmento válido dentro del recorrido', function () {


    $viaje = CrearViajeProgramable::ejecutar();



    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado' => 'PROGRAMADO'
        ]
    )
    ->assertOk();



    $puntos = $viaje
        ->fresh()
        ->puntosViaje()
        ->orderBy('orden')
        ->get();



    $respuesta = $this->getJson(
        '/api/v1/busqueda/viajes?' .
        http_build_query([

            'punto_origen_id'
                => $puntos->first()->punto_id,


            'punto_destino_id'
                => $puntos->last()->punto_id,


            'fecha'
                => $viaje
                    ->salida_programada
                    ->format('Y-m-d'),

        ])
    );


    $respuesta
        ->assertOk()
        ->assertJsonCount(
            1,
            'data'
        );

});





test('no permite buscar un origen que no pertenece al recorrido', function () {


    $viaje = CrearViajeProgramable::ejecutar();



    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado'=>'PROGRAMADO'
        ]
    )
    ->assertOk();



    $respuesta = $this->getJson(
        '/api/v1/busqueda/viajes?' .
        http_build_query([

            'punto_origen_id'=>999,

            'punto_destino_id'=>1,

            'fecha'
                => $viaje
                    ->salida_programada
                    ->format('Y-m-d')

        ])
    );


    $respuesta
        ->assertOk()
        ->assertJsonCount(
            0,
            'data'
        );

});





test('no permite buscar destino antes del origen', function () {


    $viaje = CrearViajeProgramable::ejecutar();



    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado'=>'PROGRAMADO'
        ]
    )
    ->assertOk();



    $puntos = $viaje
        ->fresh()
        ->puntosViaje()
        ->orderBy('orden')
        ->get();



    $respuesta = $this->getJson(
        '/api/v1/busqueda/viajes?' .
        http_build_query([

            /*
             * Invertimos:
             *
             * Chota -> Chiclayo
             *
             */
            'punto_origen_id'
                => $puntos->last()->punto_id,


            'punto_destino_id'
                => $puntos->first()->punto_id,


            'fecha'
                => $viaje
                    ->salida_programada
                    ->format('Y-m-d'),

        ])
    );


    $respuesta
        ->assertOk()
        ->assertJsonCount(
            0,
            'data'
        );

});
