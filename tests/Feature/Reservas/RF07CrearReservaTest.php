<?php

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Enums\TipoAsiento;
use App\Models\Asiento;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Helpers\CrearViajeProgramable;

uses(RefreshDatabase::class);


/*
|--------------------------------------------------------------------------
| Preparación
|--------------------------------------------------------------------------
*/

beforeEach(function () {

    $this->seed(
        TestingSeeder::class
    );


    /*
     * El helper crea un viaje BORRADOR
     * con un asiento físico A1.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * Agregamos A2 ANTES de programar el viaje.
     *
     * Cuando BORRADOR -> PROGRAMADO,
     * ConsolidarAsientosViaje copiará A1 y A2
     * al snapshot asientos_viaje.
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
     * Programamos utilizando el flujo real.
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


    /*
     * Ningún test empieza autenticado.
     */
    Auth::forgetGuards();


    /*
     * Datos consolidados.
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
| Helper local para crear un bloqueo real de RF-06
|--------------------------------------------------------------------------
*/

function bloquearAsientoRf07(
    $test,
    $usuario,
    int $asientoViajeId
): OcupacionAsiento {

    $test->actingAs(
        $usuario,
        'sanctum'
    );


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


    return OcupacionAsiento::query()

        ->where(
            'usuario_id',
            $usuario->id
        )

        ->where(
            'asiento_viaje_id',
            $asientoViajeId
        )

        ->latest()

        ->firstOrFail();
}


/*
|--------------------------------------------------------------------------
| 1. Seguridad
|--------------------------------------------------------------------------
*/

test(
    'crear reserva requiere autenticacion',
    function () {

        $this->postJson(
            '/api/v1/reservas',
            []
        )
            ->assertUnauthorized();
    }
);


/*
|--------------------------------------------------------------------------
| 2. Reserva individual
|--------------------------------------------------------------------------
*/

test(
    'cliente puede crear una reserva para un pasajero',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacion =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA1->id
            );


        $response =
            $this->postJson(
                '/api/v1/reservas',
                [
                    'viaje_id' =>
                        $this->viaje->id,

                    'correo_contacto' =>
                        'cliente@example.com',

                    'telefono_contacto' =>
                        '999888777',

                    'pasajeros' => [
                        [
                            'ocupacion_id' =>
                                $ocupacion->id,

                            'tipo_documento' =>
                                'DNI',

                            'numero_documento' =>
                                '12345678',

                            'nombres' =>
                                'Juan',

                            'apellidos' =>
                                'Perez Lopez',
                        ],
                    ],
                ]
            );


        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.estado',
                EstadoReserva::PENDIENTE_PAGO->value
            )
            ->assertJsonPath(
                'data.total',
                '50.00'
            )
            ->assertJsonCount(
                1,
                'data.pasajeros'
            );


        $reservaId =
            $response->json(
                'data.id'
            );


        $this->assertDatabaseHas(
            'reservas',
            [
                'id' =>
                    $reservaId,

                'viaje_id' =>
                    $this->viaje->id,

                'cliente_usuario_id' =>
                    $cliente->id,

                'creado_por_usuario_id' =>
                    $cliente->id,

                'estado' =>
                    EstadoReserva::PENDIENTE_PAGO->value,

                'total' =>
                    50.00,
            ]
        );


        $this->assertDatabaseHas(
            'pasajeros_reserva',
            [
                'reserva_id' =>
                    $reservaId,

                'ocupacion_asiento_id' =>
                    $ocupacion->id,

                'numero_documento' =>
                    '12345678',

                'precio' =>
                    50.00,
            ]
        );


        /*
         * RF-06 -> RF-07:
         *
         * BLOQUEADO -> RESERVADO
         */
        $ocupacion->refresh();


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );


        expect(
            $ocupacion->reserva_id
        )->toBe(
            $reservaId
        );


        /*
         * Crear la reserva no debe eliminar
         * el tiempo restante.
         */
        expect(
            $ocupacion->expira_en
        )->not->toBeNull();
    }
);


/*
|--------------------------------------------------------------------------
| 3. Varios pasajeros
|--------------------------------------------------------------------------
*/

test(
    'cliente puede crear una reserva para varios pasajeros',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacionA1 =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA1->id
            );


        $ocupacionA2 =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA2->id
            );


        $response =
            $this->postJson(
                '/api/v1/reservas',
                [
                    'viaje_id' =>
                        $this->viaje->id,

                    'telefono_contacto' =>
                        '999888777',

                    'pasajeros' => [

                        [
                            'ocupacion_id' =>
                                $ocupacionA1->id,

                            'tipo_documento' =>
                                'DNI',

                            'numero_documento' =>
                                '12345678',

                            'nombres' =>
                                'Juan',

                            'apellidos' =>
                                'Perez',
                        ],

                        [
                            'ocupacion_id' =>
                                $ocupacionA2->id,

                            'tipo_documento' =>
                                'DNI',

                            'numero_documento' =>
                                '87654321',

                            'nombres' =>
                                'Maria',

                            'apellidos' =>
                                'Perez',
                        ],
                    ],
                ]
            );


        $response
            ->assertCreated()

            ->assertJsonPath(
                'data.total',
                '100.00'
            )

            ->assertJsonCount(
                2,
                'data.pasajeros'
            );


        $reservaId =
            $response->json(
                'data.id'
            );


        $this->assertDatabaseCount(
            'pasajeros_reserva',
            2
        );


        foreach (
            [
                $ocupacionA1,
                $ocupacionA2,
            ]
            as $ocupacion
        ) {

            $ocupacion->refresh();


            expect(
                $ocupacion->estado
            )->toBe(
                EstadoOcupacionAsiento::RESERVADO
            );


            expect(
                $ocupacion->reserva_id
            )->toBe(
                $reservaId
            );
        }
    }
);


/*
|--------------------------------------------------------------------------
| 4. Protección contra manipulación de precios
|--------------------------------------------------------------------------
*/

test(
    'precio y total enviados por cliente son ignorados',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacion =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA1->id
            );


        $response =
            $this->postJson(
                '/api/v1/reservas',
                [
                    'viaje_id' =>
                        $this->viaje->id,

                    /*
                     * Este campo NO pertenece al Request.
                     */
                    'total' =>
                        1.00,

                    'telefono_contacto' =>
                        '999888777',

                    'pasajeros' => [
                        [
                            'ocupacion_id' =>
                                $ocupacion->id,

                            'tipo_documento' =>
                                'DNI',

                            'numero_documento' =>
                                '12345678',

                            'nombres' =>
                                'Juan',

                            'apellidos' =>
                                'Perez',

                            /*
                             * Tampoco pertenece
                             * al Request.
                             */
                            'precio' =>
                                1.00,
                        ],
                    ],
                ]
            );


        $response
            ->assertCreated()

            ->assertJsonPath(
                'data.total',
                '50.00'
            )

            ->assertJsonPath(
                'data.pasajeros.0.precio',
                '50.00'
            );
    }
);


/*
|--------------------------------------------------------------------------
| 5. Bloqueo de otro cliente
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede crear reserva utilizando bloqueo ajeno',
    function () {

        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacion =
            bloquearAsientoRf07(
                $this,
                $propietario,
                $this->asientoA1->id
            );


        $otroCliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $this->actingAs(
            $otroCliente,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'telefono_contacto' =>
                    '999888777',

                'pasajeros' => [
                    [
                        'ocupacion_id' =>
                            $ocupacion->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '12345678',

                        'nombres' =>
                            'Juan',

                        'apellidos' =>
                            'Perez',
                    ],
                ],
            ]
        )
            ->assertForbidden();


        $this->assertDatabaseCount(
            'reservas',
            0
        );


        expect(
            $ocupacion
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::BLOQUEADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Bloqueo expirado
|--------------------------------------------------------------------------
*/

test(
    'no permite crear reserva con bloqueo expirado',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacion =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA1->id
            );


        $ocupacion->update([
            'expira_en' =>
                now()->subMinute(),
        ]);


        $this->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'telefono_contacto' =>
                    '999888777',

                'pasajeros' => [
                    [
                        'ocupacion_id' =>
                            $ocupacion->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '12345678',

                        'nombres' =>
                            'Juan',

                        'apellidos' =>
                            'Perez',
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'mensaje',
                'Uno de los bloqueos seleccionados ya expiró.'
            );


        $this->assertDatabaseCount(
            'reservas',
            0
        );
    }
);


/*
|--------------------------------------------------------------------------
| 7. Ocupación repetida
|--------------------------------------------------------------------------
*/

test(
    'una misma ocupacion no puede asignarse a dos pasajeros',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacion =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA1->id
            );


        $this->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'telefono_contacto' =>
                    '999888777',

                'pasajeros' => [

                    [
                        'ocupacion_id' =>
                            $ocupacion->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '12345678',

                        'nombres' =>
                            'Juan',

                        'apellidos' =>
                            'Perez',
                    ],

                    [
                        'ocupacion_id' =>
                            $ocupacion->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '87654321',

                        'nombres' =>
                            'Maria',

                        'apellidos' =>
                            'Perez',
                    ],
                ],
            ]
        )
            ->assertUnprocessable();


        $this->assertDatabaseCount(
            'reservas',
            0
        );
    }
);


/*
|--------------------------------------------------------------------------
| 8. Operador
|--------------------------------------------------------------------------
*/

test(
    'operador puede crear reserva presencial sin cuenta de cliente',
    function () {

        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $ocupacion =
            bloquearAsientoRf07(
                $this,
                $operador,
                $this->asientoA1->id
            );


        $response =
            $this->postJson(
                '/api/v1/reservas',
                [
                    'viaje_id' =>
                        $this->viaje->id,

                    'telefono_contacto' =>
                        '999888777',

                    'pasajeros' => [
                        [
                            'ocupacion_id' =>
                                $ocupacion->id,

                            'tipo_documento' =>
                                'DNI',

                            'numero_documento' =>
                                '12345678',

                            'nombres' =>
                                'Pedro',

                            'apellidos' =>
                                'Gomez',
                        ],
                    ],
                ]
            );


        $response
            ->assertCreated();


        $reservaId =
            $response->json(
                'data.id'
            );


        $this->assertDatabaseHas(
            'reservas',
            [
                'id' =>
                    $reservaId,

                /*
                 * Venta presencial.
                 */
                'cliente_usuario_id' =>
                    null,

                /*
                 * Sí sabemos quién registró
                 * la operación.
                 */
                'creado_por_usuario_id' =>
                    $operador->id,

                'estado' =>
                    EstadoReserva::PENDIENTE_PAGO->value,
            ]
        );
    }
);


/*
|--------------------------------------------------------------------------
| 9. Fallo no deja reserva parcial
|--------------------------------------------------------------------------
*/

test(
    'fallo de una ocupacion impide crear una reserva parcial',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $ocupacionA1 =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA1->id
            );


        $ocupacionA2 =
            bloquearAsientoRf07(
                $this,
                $cliente,
                $this->asientoA2->id
            );


        /*
         * Simulamos que A2 dejó de estar disponible
         * justo antes de crear la reserva.
         */
        $ocupacionA2->update([
            'estado' =>
                EstadoOcupacionAsiento::LIBERADO,

            'expira_en' =>
                null,
        ]);


        $this->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'telefono_contacto' =>
                    '999888777',

                'pasajeros' => [

                    [
                        'ocupacion_id' =>
                            $ocupacionA1->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '12345678',

                        'nombres' =>
                            'Juan',

                        'apellidos' =>
                            'Perez',
                    ],

                    [
                        'ocupacion_id' =>
                            $ocupacionA2->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '87654321',

                        'nombres' =>
                            'Maria',

                        'apellidos' =>
                            'Perez',
                    ],
                ],
            ]
        )
            ->assertUnprocessable();


        /*
         * No debe existir ninguna reserva.
         */
        $this->assertDatabaseCount(
            'reservas',
            0
        );


        $this->assertDatabaseCount(
            'pasajeros_reserva',
            0
        );


        /*
         * A1 tampoco debe haber sido convertido
         * parcialmente a RESERVADO.
         */
        expect(
            $ocupacionA1
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::BLOQUEADO
        );


        expect(
            $ocupacionA1
                ->fresh()
                ->reserva_id
        )->toBeNull();
    }
);
