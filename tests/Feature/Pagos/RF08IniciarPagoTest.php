<?php

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoPago;
use App\Enums\EstadoReserva;
use App\Models\OcupacionAsiento;
use App\Models\Pago;
use App\Models\Reserva;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
     * Configuración falsa.
     *
     * Ningún test contactará realmente
     * los servidores de Mercado Pago.
     */
    config()->set(
        'services.mercadopago.access_token',
        'TEST_ACCESS_TOKEN'
    );

    config()->set(
        'services.mercadopago.base_url',
        'https://api.mercadopago.com'
    );

    config()->set(
        'services.mercadopago.company',
        'Empresa de Transportes Test'
    );


    config()->set(
        'services.mercadopago.web',
        [
            'success_url' =>
                'https://web.test/pagos/success',

            'failure_url' =>
                'https://web.test/pagos/failure',

            'pending_url' =>
                'https://web.test/pagos/pending',
        ]
    );


    config()->set(
        'services.mercadopago.mobile',
        [
            'success_url' =>
                'reservapasajes://pagos/success',

            'failure_url' =>
                'reservapasajes://pagos/failure',

            'pending_url' =>
                'reservapasajes://pagos/pending',
        ]
    );


    /*
     * Viaje.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


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


    $this->puntos =
        $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


    $this->asiento =
        $this->viaje
            ->asientosViaje()
            ->firstOrFail();
});


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
|
| RF-06:
| BLOQUEADO
|
| RF-07:
| PENDIENTE_PAGO / RESERVADO
|
*/

function crearReservaParaPagoRf08(
    $test,
    $cliente
): Reserva {

    $test->actingAs(
        $cliente,
        'sanctum'
    );


    /*
     * Bloqueo RF-06.
     */
    $test->postJson(
        '/api/v1/asientos/bloquear',
        [
            'viaje_id' =>
                $test->viaje->id,

            'asiento_viaje_id' =>
                $test->asiento->id,

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

            ->latest()

            ->firstOrFail();


    /*
     * Reserva RF-07.
     */
    $response =
        $test->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $test->viaje->id,

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
                            'Perez',
                    ],
                ],
            ]
        );


    $response->assertCreated();


    return Reserva::findOrFail(
        $response->json(
            'data.id'
        )
    );
}


/*
|--------------------------------------------------------------------------
| Respuesta estándar falsa de Mercado Pago
|--------------------------------------------------------------------------
*/

function respuestaMercadoPagoRf08(): array
{
    return [

        'id' =>
            'ORDTST01RF08TEST001',

        'status' =>
            'created',

        'status_detail' =>
            'created',

        'checkout_url' =>
            'https://www.mercadopago.com.pe/checkout/v1/redirect?order_id=ORDTST01RF08TEST001',
    ];
}


/*
|--------------------------------------------------------------------------
| 1. Autenticación
|--------------------------------------------------------------------------
*/

test(
    'iniciar pago requiere autenticacion',
    function () {

        $this->postJson(
            '/api/v1/reservas/1/pagos',
            [
                'canal' =>
                    'WEB',
            ],
            [
                'Idempotency-Key' =>
                    (string)
                    Str::uuid(),
            ]
        )
            ->assertUnauthorized();
    }
);


/*
|--------------------------------------------------------------------------
| 2. Idempotency-Key obligatorio
|--------------------------------------------------------------------------
*/

test(
    'iniciar pago requiere idempotency key',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $this->postJson(
            "/api/v1/reservas/{$reserva->id}/pagos",
            [
                'canal' =>
                    'WEB',
            ]
        )
            ->assertUnprocessable();
    }
);


/*
|--------------------------------------------------------------------------
| 3. Crear Order real a nivel de integración
|--------------------------------------------------------------------------
*/

test(
    'cliente puede iniciar pago web de su reserva',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders' =>
                Http::response(
                    respuestaMercadoPagoRf08(),
                    201
                ),
        ]);


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $idempotencyKey =
            (string)
            Str::uuid();


        $response =
            $this->postJson(
                "/api/v1/reservas/{$reserva->id}/pagos",
                [
                    'canal' =>
                        'WEB',
                ],
                [
                    'Idempotency-Key' =>
                        $idempotencyKey,
                ]
            );


        $response
            ->assertCreated()

            ->assertJsonPath(
                'data.estado',
                EstadoPago::PENDIENTE->value
            )

            ->assertJsonPath(
                'data.checkout_url',
                'https://www.mercadopago.com.pe/checkout/v1/redirect?order_id=ORDTST01RF08TEST001'
            );


        /*
        |--------------------------------------------------------------------------
        | Datos internos no expuestos
        |--------------------------------------------------------------------------
        |
        | proveedor_order_id existe en nuestra base de datos,
        | pero no forma parte del contrato público de la API.
        |
        */

        expect(
            array_key_exists(
                'proveedor_order_id',
                $response->json(
                    'data'
                )
            )
        )->toBeFalse();


        $this->assertDatabaseHas(
            'pagos',
            [
                'reserva_id' =>
                    $reserva->id,

                'idempotency_key' =>
                    $idempotencyKey,

                'monto' =>
                    $reserva->total,

                'moneda' =>
                    'PEN',

                'estado' =>
                    EstadoPago::PENDIENTE->value,

                'proveedor_order_id' =>
                    'ORDTST01RF08TEST001',
            ]
        );


        /*
         * Verificamos además qué se envió
         * al proveedor.
         */
        Http::assertSent(
            function ($request) use (
                $idempotencyKey,
                $reserva
            ) {

                $header =
                    $request->header(
                        'X-Idempotency-Key'
                    );


                $datos =
                    $request->data();


                return

                    /*
                    * Endpoint correcto.
                    */
                    $request->url()
                    ===
                    'https://api.mercadopago.com/v1/orders'

                    &&

                    /*
                    * Idempotencia enviada al proveedor.
                    */
                    ($header[0] ?? null)
                    ===
                    $idempotencyKey

                    &&

                    /*
                    * Checkout Pro.
                    */
                    $datos['type']
                    ===
                    'online'

                    &&

                    $datos['processing_mode']
                    ===
                    'manual'

                    &&

                    /*
                    * Monto calculado por backend.
                    */
                    $datos['total_amount']
                    ===
                    $reserva->total

                    &&

                    /*
                    * Existe comprador.
                    */
                    isset(
                        $datos[
                            'payer'
                        ][
                            'email'
                        ]
                    )

                    &&

                    /*
                    * Un pasajero en este test.
                    */
                    count(
                        $datos[
                            'items'
                        ]
                    )
                    === 1

                    &&

                    /*
                    * Estructura mínima comprobada.
                    */
                    isset(
                        $datos[
                            'items'
                        ][0][
                            'title'
                        ]
                    )

                    &&

                    $datos[
                        'items'
                    ][0][
                        'quantity'
                    ]
                    === 1

                    &&

                    isset(
                        $datos[
                            'items'
                        ][0][
                            'unit_price'
                        ]
                    )

                    &&

                    /*
                    * No enviamos todavía configuraciones
                    * avanzadas.
                    */
                    ! array_key_exists(
                        'additional_info',
                        $datos
                    )

                    &&

                    ! array_key_exists(
                        'expiration_time',
                        $datos
                    )

                    &&

                    ! array_key_exists(
                        'capture_mode',
                        $datos
                    )

                    &&

                    $datos[
                        'config'
                    ][
                        'online'
                    ][
                        'auto_return'
                    ]
                    ===
                    'approved';
            }
        );
    }
);


/*
|--------------------------------------------------------------------------
| 4. Reintento con la misma clave
|--------------------------------------------------------------------------
*/

test(
    'misma idempotency key devuelve el mismo pago',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders' =>
                Http::response(
                    respuestaMercadoPagoRf08(),
                    201
                ),
        ]);


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $key =
            (string)
            Str::uuid();


        $url =
            "/api/v1/reservas/"
            .$reserva->id
            .'/pagos';


        $primera =
            $this->postJson(
                $url,
                [
                    'canal' =>
                        'WEB',
                ],
                [
                    'Idempotency-Key' =>
                        $key,
                ]
            );


        $primera
            ->assertCreated();


        $segunda =
            $this->postJson(
                $url,
                [
                    'canal' =>
                        'WEB',
                ],
                [
                    'Idempotency-Key' =>
                        $key,
                ]
            );


        /*
         * Mismo recurso.
         *
         * Ya no es creación nueva.
         */
        $segunda
            ->assertOk()

            ->assertJsonPath(
                'data.id',
                $primera->json(
                    'data.id'
                )
            );


        $this->assertDatabaseCount(
            'pagos',
            1
        );


        /*
         * Como la primera llamada terminó
         * correctamente, no necesitamos volver
         * a llamar a Mercado Pago.
         */
        Http::assertSentCount(
            1
        );
    }
);


/*
|--------------------------------------------------------------------------
| 5. UUID diferente mientras existe pago activo
|--------------------------------------------------------------------------
*/

test(
    'no permite dos intentos de pago activos para la misma reserva',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders' =>
                Http::response(
                    respuestaMercadoPagoRf08(),
                    201
                ),
        ]);


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $url =
            "/api/v1/reservas/"
            .$reserva->id
            .'/pagos';


        $this->postJson(
            $url,
            [
                'canal' =>
                    'WEB',
            ],
            [
                'Idempotency-Key' =>
                    (string)
                    Str::uuid(),
            ]
        )
            ->assertCreated();


        $this->postJson(
            $url,
            [
                'canal' =>
                    'WEB',
            ],
            [
                'Idempotency-Key' =>
                    (string)
                    Str::uuid(),
            ]
        )
            ->assertConflict()

            ->assertJsonPath(
                'mensaje',
                'La reserva ya tiene un intento de pago activo.'
            );


        $this->assertDatabaseCount(
            'pagos',
            1
        );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Seguridad entre clientes
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede iniciar pago de reserva ajena',
    function () {

        Http::fake();


        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $propietario
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
            "/api/v1/reservas/{$reserva->id}/pagos",
            [
                'canal' =>
                    'WEB',
            ],
            [
                'Idempotency-Key' =>
                    (string)
                    Str::uuid(),
            ]
        )
            ->assertForbidden();


        $this->assertDatabaseCount(
            'pagos',
            0
        );


        Http::assertNothingSent();
    }
);


/*
|--------------------------------------------------------------------------
| 7. Reserva expirada
|--------------------------------------------------------------------------
*/

test(
    'reserva vencida no puede iniciar pago',
    function () {

        Http::fake();


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        /*
         * Simulamos vencimiento.
         */
        $reserva->update([
            'expira_en' =>
                now()->subMinute(),
        ]);


        $reserva
            ->ocupaciones()
            ->update([
                'expira_en' =>
                    now()->subMinute(),
            ]);


        $this->postJson(
            "/api/v1/reservas/{$reserva->id}/pagos",
            [
                'canal' =>
                    'WEB',
            ],
            [
                'Idempotency-Key' =>
                    (string)
                    Str::uuid(),
            ]
        )
            ->assertUnprocessable()

            ->assertJsonPath(
                'mensaje',
                'La reserva ya expiró y no puede iniciar un pago.'
            );


        expect(
            $reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::EXPIRADA
        );


        expect(
            $reserva
                ->ocupaciones()
                ->first()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );


        $this->assertDatabaseCount(
            'pagos',
            0
        );


        Http::assertNothingSent();
    }
);


/*
|--------------------------------------------------------------------------
| 8. Canal móvil
|--------------------------------------------------------------------------
*/

test(
    'pago movil utiliza deep links',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders' =>
                Http::response(
                    respuestaMercadoPagoRf08(),
                    201
                ),
        ]);


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $response =
            $this->postJson(
                "/api/v1/reservas/{$reserva->id}/pagos",
                [
                    'canal' =>
                        'MOVIL',
                ],
                [
                    'Idempotency-Key' =>
                        (string)
                        Str::uuid(),
                ]
            );


        $response
            ->assertCreated()

            ->assertJsonPath(
                'data.canal',
                'MOVIL'
            )

            ->assertJsonPath(
                'data.estado',
                EstadoPago::PENDIENTE->value
            );


        Http::assertSent(
            function ($request) {

                $datos =
                    $request->data();


                return

                    $datos[
                        'config'
                    ][
                        'online'
                    ][
                        'success_url'
                    ]
                    ===
                    'reservapasajes://pagos/success'

                    &&

                    $datos[
                        'config'
                    ][
                        'online'
                    ][
                        'failure_url'
                    ]
                    ===
                    'reservapasajes://pagos/failure'

                    &&

                    $datos[
                        'config'
                    ][
                        'online'
                    ][
                        'pending_url'
                    ]
                    ===
                    'reservapasajes://pagos/pending'

                    &&

                    ! array_key_exists(
                        'additional_info',
                        $datos
                    )

                    &&

                    ! array_key_exists(
                        'expiration_time',
                        $datos
                    )

                    &&

                    ! array_key_exists(
                        'capture_mode',
                        $datos
                    );
            }
        );
    }
);

/*
|--------------------------------------------------------------------------
| Consultar pago propio
|--------------------------------------------------------------------------
*/

test(
    'cliente puede consultar el estado de su propio pago',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders'
                =>
            Http::response(
                respuestaMercadoPagoRf08(),
                201
            ),
        ]);


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $responsePago =
            $this->postJson(
                "/api/v1/reservas/{$reserva->id}/pagos",
                [
                    'canal' =>
                        'WEB',
                ],
                [
                    'Idempotency-Key' =>
                        (string)
                        Str::uuid(),
                ]
            );


        $responsePago
            ->assertCreated();


        $pagoId =
            $responsePago->json(
                'data.id'
            );


        $response =
            $this->getJson(
                "/api/v1/pagos/{$pagoId}"
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'data.id',
                $pagoId
            )

            ->assertJsonPath(
                'data.reserva.id',
                $reserva->id
            )

            ->assertJsonPath(
                'data.estado',
                EstadoPago::PENDIENTE->value
            )

            ->assertJsonPath(
                'data.reserva.estado',
                EstadoReserva::PENDIENTE_PAGO->value
            );


        expect(
            array_key_exists(
                'proveedor_order_id',
                $response->json(
                    'data'
                )
            )
        )->toBeFalse();
    }
);

/*
|--------------------------------------------------------------------------
| Otro cliente no puede consultar el pago
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede consultar pago de otra persona',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders'
                =>
            Http::response(
                respuestaMercadoPagoRf08(),
                201
            ),
        ]);


        $clientePropietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $clientePropietario
            );


        $responsePago =
            $this->postJson(
                "/api/v1/reservas/{$reserva->id}/pagos",
                [
                    'canal' =>
                        'WEB',
                ],
                [
                    'Idempotency-Key' =>
                        (string)
                        Str::uuid(),
                ]
            );


        $pagoId =
            $responsePago->json(
                'data.id'
            );


        $otroCliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $this->actingAs(
            $otroCliente,
            'sanctum'
        );


        $this->getJson(
            "/api/v1/pagos/{$pagoId}"
        )
            ->assertForbidden();
    }
);


/*
|--------------------------------------------------------------------------
| Operador puede consultar
|--------------------------------------------------------------------------
*/

test(
    'operador puede consultar un pago',
    function () {

        Http::fake([
            'https://api.mercadopago.com/v1/orders'
                =>
            Http::response(
                respuestaMercadoPagoRf08(),
                201
            ),
        ]);


        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaParaPagoRf08(
                $this,
                $cliente
            );


        $responsePago =
            $this->postJson(
                "/api/v1/reservas/{$reserva->id}/pagos",
                [
                    'canal' =>
                        'WEB',
                ],
                [
                    'Idempotency-Key' =>
                        (string)
                        Str::uuid(),
                ]
            );


        $pagoId =
            $responsePago->json(
                'data.id'
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
            "/api/v1/pagos/{$pagoId}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $pagoId
            );
    }
);


/*
|--------------------------------------------------------------------------
| Consulta requiere autenticación
|--------------------------------------------------------------------------
*/

test(
    'consultar pago requiere autenticacion',
    function () {

        Auth::forgetGuards();


        $this->getJson(
            '/api/v1/pagos/1'
        )
            ->assertUnauthorized();
    }
);
