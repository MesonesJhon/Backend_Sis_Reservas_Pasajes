<?php

use App\Enums\EstadoViaje;
use App\Models\Viaje;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Database\Seeders\TestingSeeder;

use Tests\Helpers\CrearViajeProgramable;

use App\Models\Rol;
use App\Models\Usuario;


uses(RefreshDatabase::class);



beforeEach(function () {

    /*
     * Cargamos:
     *
     * - roles
     * - permisos
     * - tipos vehículo
     * - configuraciones necesarias
     */
    $this->seed([
        TestingSeeder::class,
    ]);


    $admin = Usuario::factory()->create([
        'activo'=>true,
    ]);


    $admin->roles()->attach(

        Rol::where('nombre','ADMINISTRADOR')
            ->firstOrFail()
            ->id

    );


    $this->actingAs(
        $admin,
        'sanctum'
    );

});



test('puede consultar viajes programados por fecha', function () {


    /*
     * Creamos viaje completo.
     *
     * No usamos factory directamente porque
     * RF-05 necesita:
     *
     * - ruta
     * - puntos
     * - tarifa
     * - vehículo
     */
    $viaje = CrearViajeProgramable::ejecutar(
        salida: '2026-10-10 08:00:00'
    );


    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado'=>'PROGRAMADO'
        ]
    )->assertOk();


    $viaje = $viaje->fresh();



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
                => '2026-10-10',

        ])
    );



    $respuesta
        ->assertOk()
        ->assertJsonStructure([
            'data'
        ]);

});





test('la búsqueda solamente devuelve viajes programados', function () {


    /*
     * Viaje válido que debe aparecer.
     */
    $programado =
        CrearViajeProgramable::ejecutar();


    $this->patchJson(
        "/api/v1/viajes/{$programado->id}/estado",
        [
            'estado'=>'PROGRAMADO'
        ]
    )->assertOk();


    $programado = $programado->fresh();



    /*
     * Viajes que NO deben aparecer.
     */
    Viaje::factory()->create([
        'estado'=>EstadoViaje::BORRADOR
    ]);


    Viaje::factory()->create([
        'estado'=>EstadoViaje::CANCELADO
    ]);



    $puntos = $programado
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
                => $programado
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





test('no devuelve viajes de otra fecha', function () {


    $viaje =
        CrearViajeProgramable::ejecutar(
            salida:'2026-10-11 08:00:00'
        );



    $this->patchJson(
        "/api/v1/viajes/{$viaje->id}/estado",
        [
            'estado'=>'PROGRAMADO'
        ]
    )->assertOk();


    $viaje = $viaje->fresh();



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

            'fecha'=>'2026-10-10',

        ])
    );



    $respuesta
        ->assertOk()
        ->assertJsonCount(
            0,
            'data'
        );

});





test('la búsqueda requiere origen destino y fecha', function () {


    $respuesta = $this->getJson(
        '/api/v1/busqueda/viajes'
    );



    $respuesta
        ->assertUnprocessable()
        ->assertJsonValidationErrors([

            'punto_origen_id',

            'punto_destino_id',

            'fecha',

        ]);

});





test('no devuelve resultados cuando los puntos no pertenecen a ningún viaje', function () {


    $respuesta = $this->getJson(
        '/api/v1/busqueda/viajes?' .
        http_build_query([

            'punto_origen_id'=>9999,

            'punto_destino_id'=>8888,

            'fecha'=>'2026-10-10',

        ])
    );



    /*
     * En búsqueda comercial:
     *
     * No es un error de validación.
     *
     * Simplemente no existen viajes
     * disponibles para ese segmento.
     */
    $respuesta
        ->assertOk()
        ->assertJson([
            'data'=>[]
        ]);

});
