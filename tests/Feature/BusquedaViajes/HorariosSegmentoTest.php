<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\CrearViajeProgramable;


uses(RefreshDatabase::class);


beforeEach(function () {

    $this->seed();

    autenticarAdministrador();

});



test('calcula correctamente horario de embarque del segmento', function () {


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
                => $puntos->first()->punto_id,


            'punto_destino_id'
                => $puntos->last()->punto_id,


            'fecha'
                => $viaje
                    ->salida_programada
                    ->format('Y-m-d')

        ])
    );



    $respuesta
        ->assertOk()
        ->assertJsonStructure([
            'data'=>[
                '*'=>[
                    'horario'=>[
                        'hora_embarque',
                        'hora_llegada',
                        'duracion_minutos'
                    ]
                ]
            ]
        ]);

});





test('la duración corresponde al segmento y no a la ruta completa', function () {


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
                => $puntos->first()->punto_id,


            'punto_destino_id'
                => $puntos->last()->punto_id,


            'fecha'
                => $viaje
                    ->salida_programada
                    ->format('Y-m-d')

        ])
    );



    $respuesta
        ->assertOk();


    expect(
        $respuesta->json(
            'data.0.horario.duracion_minutos'
        )
    )
    ->toBeGreaterThan(0);

});
