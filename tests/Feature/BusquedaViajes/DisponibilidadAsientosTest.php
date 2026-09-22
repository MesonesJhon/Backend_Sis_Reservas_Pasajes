<?php

use App\Enums\EstadoOcupacionAsiento;
use App\Models\OcupacionAsiento;
use App\Models\Viaje;

use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\AdministradorSeeder;
use Database\Seeders\TiposVehiculoSeeder;
use Database\Seeders\PuntosSeeder;
use Database\Seeders\ViajesDemoSeeder;



/*
|--------------------------------------------------------------------------
| RF-05
|
| Pruebas de disponibilidad real de asientos
|
|--------------------------------------------------------------------------
*/


beforeEach(function () {

    /*
    |--------------------------------------------------------------------------
    | Se ejecuta antes de cada prueba.
    |
    | El Seeder genera:
    |
    | - Vehículo
    | - Ruta
    | - Viaje programado
    | - Puntos del recorrido
    | - Asientos
    |
    |--------------------------------------------------------------------------
    */


    $this->seed([
        RolesPermisosSeeder::class,
        AdministradorSeeder::class,
        TiposVehiculoSeeder::class,
        PuntosSeeder::class,
        ViajesDemoSeeder::class,
    ]);


    $this->viaje = Viaje::first();

});


test(
    'una petición sin autenticación recibe 401',
    function () {


        $response = $this->getJson(
            "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos?"
            ."punto_origen_id=5&"
            ."punto_destino_id=8"
        );


        $response->assertUnauthorized();

    }
);


test(
    'permite consultar disponibilidad de asientos de un viaje',
    function () {


        autenticarAdministrador();


        $response = $this->getJson(
            "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos?"
            ."punto_origen_id=5&"
            ."punto_destino_id=8"
        );



        $response
            ->assertOk()
            ->assertJsonStructure([

                'data' => [

                    '*' => [

                        'id',

                        'codigo',

                        'piso',

                        'fila',

                        'columna',

                        'tipo',

                        'disponible',

                    ]

                ]

            ]);

    }
);


test(
    'un asiento ocupado no aparece disponible en el mismo segmento',
    function () {


        /*
        |--------------------------------------------------------------------------
        | Ocupamos A1:
        |
        | Chiclayo -> Olmos
        |
        |--------------------------------------------------------------------------
        */


        OcupacionAsiento::create([

            'viaje_id'=>$this->viaje->id,

            'asiento_viaje_id'=>1,

            'punto_origen_id'=>5,

            'punto_destino_id'=>7,

            'estado'=>EstadoOcupacionAsiento::CONFIRMADO,

        ]);





        autenticarAdministrador();


        $response = $this->getJson(
            "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos?"
            ."punto_origen_id=5&"
            ."punto_destino_id=8"
        );





        $asientos = collect(
            $response->json('data')
        );



        $asiento = $asientos
            ->firstWhere(
                'codigo',
                'A1'
            );



        expect(
            $asiento['disponible']
        )
        ->toBeFalse();


    }
);


test(
    'permite reutilizar asiento cuando el tramo no se cruza',
    function () {


        /*
        |--------------------------------------------------------------------------
        | Ocupación:
        |
        | Chiclayo -> Olmos
        |
        |--------------------------------------------------------------------------
        */


        OcupacionAsiento::create([

            'viaje_id'=>$this->viaje->id,

            'asiento_viaje_id'=>1,

            'punto_origen_id'=>5,

            'punto_destino_id'=>7,

            'estado'=>EstadoOcupacionAsiento::CONFIRMADO,

        ]);


        /*
        |--------------------------------------------------------------------------
        | Nueva consulta:
        |
        | Olmos -> Chota
        |
        |--------------------------------------------------------------------------
        */



        autenticarAdministrador();


        $response = $this->getJson(
            "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos?"
            ."punto_origen_id=7&"
            ."punto_destino_id=8"
        );





        $asientos = collect(
            $response->json('data')
        );



        $asiento = $asientos
            ->firstWhere(
                'codigo',
                'A1'
            );



        expect(
            $asiento['disponible']
        )
        ->toBeTrue();


    }
);

test(
    'no permite consultar puntos que no pertenecen al viaje',
    function () {


        autenticarAdministrador();


        $response = $this->getJson(
            "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos?"
            ."punto_origen_id=999&"
            ."punto_destino_id=1000"
        );



        $response
            ->assertStatus(422);

    }
);
