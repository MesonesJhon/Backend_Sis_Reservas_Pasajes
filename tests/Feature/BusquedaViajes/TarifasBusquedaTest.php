<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\CrearViajeProgramable;


uses(RefreshDatabase::class);



beforeEach(function () {

    $this->seed();

    autenticarAdministrador();

});



test('devuelve la tarifa correcta del segmento solicitado', function () {


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

            'punto_origen_id'
                =>$puntos->first()->punto_id,


            'punto_destino_id'
                =>$puntos->last()->punto_id,


            'fecha'
                =>$viaje
                    ->salida_programada
                    ->format('Y-m-d')

        ])
    );



    $respuesta
        ->assertOk()
        ->assertJsonStructure([
            'data'=>[
                '*'=>[
                    'precio'
                ]
            ]
        ]);

});





test('no devuelve segmentos sin tarifa configurada', function () {


    $viaje = CrearViajeProgramable::ejecutar();







    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado'=>'PROGRAMADO'
        ]
    )
    ->assertOk();

    /*
    * Eliminamos tarifas existentes.
    */
    $viaje
        ->tarifas()
        ->delete();

    $puntos = $viaje
        ->fresh()
        ->puntosViaje()
        ->orderBy('orden')
        ->get();



    $respuesta = $this->getJson(
        '/api/v1/busqueda/viajes?' .
        http_build_query([

            'punto_origen_id'
                =>$puntos->first()->punto_id,


            'punto_destino_id'
                =>$puntos->last()->punto_id,


            'fecha'
                =>$viaje
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
