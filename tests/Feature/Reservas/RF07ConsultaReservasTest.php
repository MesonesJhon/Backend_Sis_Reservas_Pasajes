<?php

use App\Enums\TipoAsiento;
use App\Models\Asiento;
use App\Models\OcupacionAsiento;
use App\Models\Usuario;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Helpers\CrearViajeProgramable;

uses(RefreshDatabase::class);


/*
|--------------------------------------------------------------------------
| Preparación común
|--------------------------------------------------------------------------
*/

beforeEach(function () {

    $this->seed(
        TestingSeeder::class
    );


    /*
     * Crear viaje BORRADOR.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * El helper ya genera A1.
     *
     * Agregamos A2 para poder crear
     * reservas independientes.
     */
    Asiento::create([
        'vehiculo_id' =>
            $this->viaje->vehiculo_id,

        'codigo' =>
            'A2',

        'numero' =>
            2,

        'piso' =>
            1,

        'fila' =>
            1,

        'columna' =>
            2,

        'tipo' =>
            TipoAsiento::NORMAL,

        'caracteristicas' =>
            [],

        'activo' =>
            true,
    ]);


    /*
     * Programar mediante el endpoint real.
     */
    autenticarAdministrador();


    $this->patchJson(
        "/api/v1/viajes/{$this->viaje->id}/estado",
        [
            'estado' =>
                'PROGRAMADO',
        ]
    )->assertOk();


    $this->viaje =
        $this->viaje->fresh();


    Auth::forgetGuards();


    /*
     * Obtener snapshots consolidados.
     */
    $this->puntos =
        $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


    $this->asientoA1 =
        $this->viaje
            ->asientosViaje()
            ->where(
                'codigo',
                'A1'
            )
            ->firstOrFail();


    $this->asientoA2 =
        $this->viaje
            ->asientosViaje()
            ->where(
                'codigo',
                'A2'
            )
            ->firstOrFail();
});


/*
|--------------------------------------------------------------------------
| Helper: crear reserva mediante flujo real
|--------------------------------------------------------------------------
*/

function crearReservaParaConsultaRf07(
    $test,
    Usuario $cliente,
    int $asientoViajeId,
    string $documento
): array {

    /*
     * Autenticar cliente.
     */
    $test->actingAs(
        $cliente,
        'sanctum'
    );


    /*
     * RF-06:
     * crear bloqueo.
     */
    $test->postJson(
        '/api/v1/asientos/bloquear',
        [
            'viaje_id' =>
                $test->viaje->id,

            'asiento_viaje_id' =>
                $asientoViajeId,

            'punto_origen_id' =>
                $test
                    ->puntos
                    ->first()
                    ->punto_id,

            'punto_destino_id' =>
                $test
                    ->puntos
                    ->last()
                    ->punto_id,
        ]
    )->assertSuccessful();


    $ocupacion =
        OcupacionAsiento::query()

            ->where(
                'usuario_id',
                $cliente->id
            )

            ->where(
                'asiento_viaje_id',
                $asientoViajeId
            )

            ->latest()

            ->firstOrFail();


    /*
     * RF-07:
     * crear reserva.
     */
    $response =
        $test->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $test->viaje->id,

                'telefono_contacto' =>
                    '999888777',

                'pasajeros' => [
                    [
                        'ocupacion_id' =>
                            $ocupacion->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            $documento,

                        'nombres' =>
                            'Pasajero',

                        'apellidos' =>
                            'Prueba',
                    ],
                ],
            ]
        );


    $response->assertCreated();


    return [
        'id' =>
            $response->json(
                'data.id'
            ),

        'codigo' =>
            $response->json(
                'data.codigo'
            ),
    ];
}


/*
|--------------------------------------------------------------------------
| 1. Autenticación
|--------------------------------------------------------------------------
*/

test(
    'listar reservas requiere autenticacion',
    function () {

        $this->getJson(
            '/api/v1/reservas'
        )
            ->assertUnauthorized();
    }
);


/*
|--------------------------------------------------------------------------
| 2. Cliente solamente ve sus reservas
|--------------------------------------------------------------------------
*/

test(
    'cliente solamente puede listar sus propias reservas',
    function () {

        $clienteUno =
            usuarioConRolViajes(
                'CLIENTE'
            );

        $clienteDos =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reservaUno =
            crearReservaParaConsultaRf07(
                $this,
                $clienteUno,
                $this->asientoA1->id,
                '12345678'
            );


        crearReservaParaConsultaRf07(
            $this,
            $clienteDos,
            $this->asientoA2->id,
            '87654321'
        );


        /*
         * Volvemos a autenticar cliente 1.
         */
        $this->actingAs(
            $clienteUno,
            'sanctum'
        );


        $response =
            $this->getJson(
                '/api/v1/reservas'
            );


        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $reservaUno['id']
            );
    }
);


/*
|--------------------------------------------------------------------------
| 3. Cliente puede ver el detalle de su reserva
|--------------------------------------------------------------------------
*/

test(
    'cliente puede consultar detalle de su propia reserva',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaConsultaRf07(
                $this,
                $cliente,
                $this->asientoA1->id,
                '12345678'
            );


        $response =
            $this->getJson(
                "/api/v1/reservas/{$reserva['id']}"
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'data.id',
                $reserva['id']
            )

            ->assertJsonPath(
                'data.codigo',
                $reserva['codigo']
            )

            ->assertJsonCount(
                1,
                'data.pasajeros'
            );
    }
);


/*
|--------------------------------------------------------------------------
| 4. Cliente no puede ver reserva ajena
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede consultar reserva de otro cliente',
    function () {

        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $otroCliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaConsultaRf07(
                $this,
                $propietario,
                $this->asientoA1->id,
                '12345678'
            );


        $this->actingAs(
            $otroCliente,
            'sanctum'
        );


        $this->getJson(
            "/api/v1/reservas/{$reserva['id']}"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'mensaje',
                'No puedes consultar una reserva que pertenece a otro usuario.'
            );
    }
);


/*
|--------------------------------------------------------------------------
| 5. Operador puede consultar reservas del sistema
|--------------------------------------------------------------------------
*/

test(
    'operador puede listar reservas',
    function () {

        $clienteUno =
            usuarioConRolViajes(
                'CLIENTE'
            );

        $clienteDos =
            usuarioConRolViajes(
                'CLIENTE'
            );


        crearReservaParaConsultaRf07(
            $this,
            $clienteUno,
            $this->asientoA1->id,
            '12345678'
        );


        crearReservaParaConsultaRf07(
            $this,
            $clienteDos,
            $this->asientoA2->id,
            '87654321'
        );


        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->getJson(
            '/api/v1/reservas'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Administrador puede consultar cualquier reserva
|--------------------------------------------------------------------------
*/

test(
    'administrador puede consultar cualquier reserva',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaConsultaRf07(
                $this,
                $cliente,
                $this->asientoA1->id,
                '12345678'
            );


        autenticarAdministrador();


        $this->getJson(
            "/api/v1/reservas/{$reserva['id']}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $reserva['id']
            );
    }
);


/*
|--------------------------------------------------------------------------
| 7. Filtrar por código
|--------------------------------------------------------------------------
*/

test(
    'puede filtrar reservas por codigo',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaConsultaRf07(
                $this,
                $cliente,
                $this->asientoA1->id,
                '12345678'
            );


        /*
         * Utilizamos una parte del código.
         */
        $fragmento =
            substr(
                $reserva['codigo'],
                0,
                10
            );


        $response =
            $this->getJson(
                '/api/v1/reservas?codigo='
                .$fragmento
            );


        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $reserva['id']
            );
    }
);
