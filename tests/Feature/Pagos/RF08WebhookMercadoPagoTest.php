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
use Illuminate\Support\Facades\Artisan;
use App\Models\EventoWebhookPago;
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

function crearReservaParaWebhookRf08(
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

function respuestaCreacionOrdenWebhookRf08(): array
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


/**
 * Construye el escenario completo necesario
 * antes de recibir un Webhook.
 *
 * Flujo:
 *
 * RF-06:
 * asiento -> BLOQUEADO
 *
 * RF-07:
 * reserva -> PENDIENTE_PAGO
 * ocupación -> RESERVADO
 *
 * RF-08:
 * pago -> PENDIENTE
 * Order de Mercado Pago creada
 *
 * @return array{
 *     cliente: mixed,
 *     reserva: Reserva,
 *     pago: Pago
 * }
 */
function crearPagoPendienteParaWebhookRf08(
    $test
): array {

    /*
    |--------------------------------------------------------------------------
    | 1. Cliente
    |--------------------------------------------------------------------------
    */

    $cliente =
        usuarioConRolViajes(
            'CLIENTE'
        );


    /*
    |--------------------------------------------------------------------------
    | 2. Crear reserva mediante RF-06 + RF-07
    |--------------------------------------------------------------------------
    */

    $reserva =
        crearReservaParaWebhookRf08(
            $test,
            $cliente
        );


    /*
    |--------------------------------------------------------------------------
    | 3. Simular creación de Order en Mercado Pago
    |--------------------------------------------------------------------------
    |
    | En este punto todavía NO estamos probando
    | el Webhook.
    |
    | Solamente necesitamos dejar un Pago local
    | correctamente asociado a una Order.
    |
    */

    Http::fake([
        'https://api.mercadopago.com/v1/orders'
            =>
        Http::response(
            respuestaCreacionOrdenWebhookRf08(),
            201
        ),
    ]);


    /*
    |--------------------------------------------------------------------------
    | 4. Iniciar pago real mediante nuestra API
    |--------------------------------------------------------------------------
    */

    $idempotencyKey =
        (string)
        Str::uuid();


    $response =
        $test->postJson(
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
        );


    /*
    |--------------------------------------------------------------------------
    | 5. Recuperar Pago
    |--------------------------------------------------------------------------
    */

    $pago =
        Pago::findOrFail(
            $response->json(
                'data.id'
            )
        );


    /*
     * Garantizamos que el escenario previo
     * al Webhook es realmente el esperado.
     */
    expect(
        $pago->estado
    )->toBe(
        EstadoPago::PENDIENTE
    );


    expect(
        $pago->proveedor_order_id
    )->not->toBeNull();


    expect(
        $reserva->fresh()->estado
    )->toBe(
        EstadoReserva::PENDIENTE_PAGO
    );


    return [
        'cliente' =>
            $cliente,

        'reserva' =>
            $reserva->fresh(),

        'pago' =>
            $pago->fresh(),
    ];
}

function firmaWebhookMercadoPagoRf08(
    string $dataId,
    string $requestId,
    string $secret,
    string $timestamp
): string {

    /*
     * Manifest utilizado por el SDK oficial:
     *
     * id:{dataId};request-id:{requestId};ts:{ts};
     */
    $manifest =
        'id:'
        .$dataId
        .';request-id:'
        .$requestId
        .';ts:'
        .$timestamp
        .';';


    $hash =
        hash_hmac(
            'sha256',
            $manifest,
            $secret
        );


    return 'ts='
        .$timestamp
        .',v1='
        .$hash;
}

/**
 * Construye una respuesta simulada de:
 *
 * GET /v1/orders/{order_id}
 *
 * Permite reutilizar el mismo formato
 * en todos los escenarios del Webhook.
 */
function respuestaConsultaOrdenWebhookRf08(
    Pago $pago,
    string $estado,
    ?string $detalleEstado,
    ?string $montoTotal = null,
    ?string $montoPagado = null,
    ?string $moneda = 'PEN',
    ?string $paymentId = 'PAY01RF08WEBHOOK'
): array {

    return [

        'id' =>
            $pago->proveedor_order_id,

        'status' =>
            $estado,

        'status_detail' =>
            $detalleEstado,

        'external_reference' =>
            $pago->external_reference,

        'total_amount' =>
            $montoTotal
            ?? $pago->monto,

        'total_paid_amount' =>
            $montoPagado
            ?? $pago->monto,

        'currency' =>
            $moneda,

        'transactions' => [

            'payments' => [

                [
                    'id' =>
                        $paymentId,

                    'status' =>
                        $estado,

                    'status_detail' =>
                        $detalleEstado,
                ],
            ],
        ],
    ];
}


/**
 * Envía una notificación correctamente
 * firmada simulando a Mercado Pago.
 */
function enviarWebhookOrdenRf08(
    $test,
    Pago $pago,
    string $eventoId,
    string $accion = 'order.processed'
) {

    $secret =
        'SECRET_TEST_RF08';


    config()->set(
        'services.mercadopago.webhook_secret',
        $secret
    );


    $requestId =
        'request-'.$eventoId;


    /*
     * La firma solo necesita que el timestamp
     * utilizado aquí sea exactamente el mismo
     * incorporado en X-Signature.
     */
    $timestamp =
        (string)
        now()->timestamp;


    $firma =
        firmaWebhookMercadoPagoRf08(
            $pago->proveedor_order_id,
            $requestId,
            $secret,
            $timestamp
        );


    return $test->postJson(

        '/api/v1/webhooks/mercado-pago'
        .'?data.id='
        .urlencode(
            $pago->proveedor_order_id
        )
        .'&type=order',

        [
            'id' =>
                $eventoId,

            'type' =>
                'order',

            'action' =>
                $accion,

            'data' => [
                'id' =>
                    $pago->proveedor_order_id,
            ],
        ],

        [
            'X-Request-Id' =>
                $requestId,

            'X-Signature' =>
                $firma,
        ]
    );
}



test(
    'webhook valido sincroniza pago aprobado desde mercado pago',
    function () {

        /*
         * Aquí utiliza tu helper actual que deja:
         *
         * Reserva = PENDIENTE_PAGO
         * Pago    = PENDIENTE
         *
         * y devuelve el Pago.
         */
        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        /*
        * Mercado Pago NO está autenticado mediante Sanctum.
        *
        * Limpiamos explícitamente cualquier autenticación
        * que haya quedado del flujo de creación del pago.
        */
        Auth::forgetGuards();


        /*
         * A partir de aquí solamente
         * simulamos GET /v1/orders/{id}.
         */
        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
            =>
            Http::response(
                [
                    'id' =>
                        $pago->proveedor_order_id,

                    'status' =>
                        'processed',

                    'status_detail' =>
                        'accredited',

                    'external_reference' =>
                        $pago->external_reference,

                    'total_amount' =>
                        $pago->monto,

                    'total_paid_amount' =>
                        $pago->monto,

                    'currency' =>
                        'PEN',

                    'transactions' => [
                        'payments' => [
                            [
                                'id' =>
                                    'PAY01TEST123456',

                                'status' =>
                                    'processed',

                                'status_detail' =>
                                    'accredited',
                            ],
                        ],
                    ],
                ],
                200
            ),
        ]);


        $secret =
            'SECRET_TEST_RF08';


        config()->set(
            'services.mercadopago.webhook_secret',
            $secret
        );


        $requestId =
            'request-rf08-001';


        $timestamp =
            (string)
            now()->timestamp;


        $firma =
            firmaWebhookMercadoPagoRf08(

                $pago->proveedor_order_id,

                $requestId,

                $secret,

                $timestamp
            );


        $response =
            $this->postJson(
                '/api/v1/webhooks/mercado-pago'
                .'?data.id='
                .urlencode(
                    $pago->proveedor_order_id
                )
                .'&type=order',

                [
                    'id' =>
                        'EVENTO-RF08-001',

                    'type' =>
                        'order',

                    'action' =>
                        'order.processed',

                    'data' => [
                        'id' =>
                            $pago->proveedor_order_id,
                    ],
                ],

                [
                    'X-Request-Id' =>
                        $requestId,

                    'X-Signature' =>
                        $firma,
                ]
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'recibido',
                true
            );


        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->proveedor_payment_id
        )->toBe(
            'PAY01TEST123456'
        );


        expect(
            $pago->aprobado_en
        )->not->toBeNull();


        expect(
            $pago->requiere_revision
        )->toBeFalse();


        /*
        * ETAPA 4:
        *
        * Un pago aprobado, íntegro y vigente
        * confirma automáticamente la reserva.
        */
        expect(
            $pago
                ->reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );

        expect(
            $pago
                ->reserva
                ->fresh()
                ->pago_confirmacion_id
        )->toBe(
            $pago->id
        );


        $this->assertDatabaseHas(
            'eventos_webhook_pago',
            [
                'evento_id' =>
                    'EVENTO-RF08-001',

                'data_id' =>
                    $pago
                        ->proveedor_order_id,

                'procesado' =>
                    true,
            ]
        );
    }
);


test(
    'webhook con firma invalida es rechazado',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        /*
         * Mercado Pago no utiliza Sanctum.
         */
        Auth::forgetGuards();


        config()->set(
            'services.mercadopago.webhook_secret',
            'SECRET_TEST_RF08'
        );


        /*
         * No debería existir ninguna consulta
         * al proveedor si la firma es inválida.
         */
        Http::fake();


        $response =
            $this->postJson(

                '/api/v1/webhooks/mercado-pago'
                .'?data.id='
                .urlencode(
                    $pago->proveedor_order_id
                )
                .'&type=order',

                [
                    'id' =>
                        'EVENTO-FIRMA-INVALIDA',

                    'type' =>
                        'order',

                    'action' =>
                        'order.processed',

                    'data' => [
                        'id' =>
                            $pago->proveedor_order_id,
                    ],
                ],

                [
                    'X-Request-Id' =>
                        'request-firma-invalida',

                    /*
                     * Firma deliberadamente falsa.
                     */
                    'X-Signature' =>
                        'ts=123456,v1=firma_incorrecta',
                ]
            );


        $response
            ->assertUnauthorized()

            ->assertJsonPath(
                'mensaje',
                'Notificación no autorizada.'
            );


        /*
         * Nada financiero debe modificarse.
         */
        expect(
            $pago
                ->fresh()
                ->estado
        )->toBe(
            EstadoPago::PENDIENTE
        );


        /*
         * Ni siquiera registramos el evento porque
         * primero validamos autenticidad.
         */
        $this->assertDatabaseMissing(
            'eventos_webhook_pago',
            [
                'evento_id' =>
                    'EVENTO-FIRMA-INVALIDA',
            ]
        );


        Http::assertNothingSent();
    }
);

test(
    'webhook repetido es idempotente y no procesa dos veces',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        Auth::forgetGuards();


        /*
         * Contador de consultas reales
         * GET /v1/orders/{id}.
         */
        $consultasProveedor = 0;


        Http::fake(
            function ($request) use (
                $pago,
                &$consultasProveedor
            ) {

                if (
                    $request->method()
                    === 'GET'
                ) {

                    $consultasProveedor++;


                    return Http::response(
                        respuestaConsultaOrdenWebhookRf08(
                            $pago,
                            'processed',
                            'accredited'
                        ),
                        200
                    );
                }


                return Http::response(
                    [],
                    500
                );
            }
        );


        /*
         * Primera notificación.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-IDEMPOTENTE-001'
        )
            ->assertOk();


        expect(
            $pago
                ->fresh()
                ->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        /*
         * Mercado Pago manda exactamente
         * el mismo evento nuevamente.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-IDEMPOTENTE-001'
        )
            ->assertOk();


        /*
         * Solamente la primera notificación
         * debe consultar la Order.
         */
        expect(
            $consultasProveedor
        )->toBe(1);


        /*
         * Solo existe un evento registrado.
         */
        expect(
            DB::table(
                'eventos_webhook_pago'
            )
                ->where(
                    'evento_id',
                    'EVENTO-IDEMPOTENTE-001'
                )
                ->count()
        )->toBe(1);
    }
);


test(
    'webhook sincroniza pago rechazado',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'failed',
                    'payment_rejected'
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-RECHAZADO-001',
            'order.failed'
        )
            ->assertOk();


        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::RECHAZADO
        );


        expect(
            $pago->rechazado_en
        )->not->toBeNull();


        /*
         * Un pago rechazado NO confirma
         * la reserva.
         */
        expect(
            $pago
                ->reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);

test(
    'webhook mantiene pago pendiente cuando mercado pago sigue procesando',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processing',
                    'in_process'
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-PENDIENTE-001',
            'order.processing'
        )
            ->assertOk();


        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::PENDIENTE
        );


        expect(
            $pago->aprobado_en
        )->toBeNull();


        expect(
            $pago->rechazado_en
        )->toBeNull();


        expect(
            $pago->requiere_revision
        )->toBeFalse();


        expect(
            $pago
                ->reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);


test(
    'pago aprobado con monto inconsistente requiere revision',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,

                    'processed',

                    'accredited',

                    /*
                     * Order total incorrecta.
                     */
                    '1.00',

                    /*
                     * Monto pagado incorrecto.
                     */
                    '1.00'
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-MONTO-INVALIDO-001'
        )
            ->assertOk();


        $pago->refresh();


        /*
         * Financieramente Mercado Pago dijo:
         *
         * APROBADO.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        /*
         * Pero nuestro sistema detectó
         * inconsistencia.
         */
        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            $pago->motivo_revision
        )->not->toBeNull();


        /*
         * ETAPA 3:
         *
         * todavía no confirmamos Reserva.
         */
        expect(
            $pago
                ->reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);

test(
    'referencia externa diferente requiere revision',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        Auth::forgetGuards();


        $respuesta =
            respuestaConsultaOrdenWebhookRf08(
                $pago,
                'processed',
                'accredited'
            );


        /*
         * Simulamos una Order que aparentemente
         * tiene el mismo ID pero cuya referencia
         * no corresponde a nuestro intento.
         */
        $respuesta[
            'external_reference'
        ] =
            'PAY-REFERENCIA-AJENA';


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                $respuesta,
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-REFERENCIA-INVALIDA'
        )
            ->assertOk();


        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            $pago->motivo_revision
        )->toContain(
            'referencia externa'
        );


        expect(
            $pago
                ->reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);

test(
    'webhook de order sin id superior tambien puede procesarse',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        Auth::forgetGuards();


        /*
        |--------------------------------------------------------------------------
        | Mercado Pago oficial
        |--------------------------------------------------------------------------
        */

        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processed',
                    'accredited'
                ),
                200
            ),
        ]);


        $secret =
            'SECRET_TEST_RF08';


        config()->set(
            'services.mercadopago.webhook_secret',
            $secret
        );


        $requestId =
            'request-order-sin-id';


        $timestamp =
            (string)
            now()->timestamp;


        $firma =
            firmaWebhookMercadoPagoRf08(
                $pago->proveedor_order_id,
                $requestId,
                $secret,
                $timestamp
            );


        /*
        |--------------------------------------------------------------------------
        | BODY como el simulador REAL
        |--------------------------------------------------------------------------
        |
        | Observa que NO existe:
        |
        | 'id' => 'EVENTO-...'
        |
        */

        $response =
            $this->postJson(

                '/api/v1/webhooks/mercado-pago'
                .'?data.id='
                .urlencode(
                    $pago->proveedor_order_id
                )
                .'&type=order',

                [
                    'action' =>
                        'order.processed',

                    'api_version' =>
                        'v1',

                    'application_id' =>
                        '671679613591468',

                    'data' => [

                        'id' =>
                            $pago->proveedor_order_id,

                        /*
                         * Incluso si estos datos fueran falsos,
                         * nuestro backend NO confiará en ellos.
                         */
                        'status' =>
                            'processed',

                        'status_detail' =>
                            'accredited',

                        'total_paid_amount' =>
                            100000,

                        'external_reference' =>
                            'ext_ref_1234',

                        'transactions' => [

                            'payments' => [

                                [
                                    'id' =>
                                        'PAY-SIMULADOR',
                                ],
                            ],
                        ],
                    ],

                    'live_mode' =>
                        true,

                    'type' =>
                        'order',
                ],

                [
                    'X-Request-Id' =>
                        $requestId,

                    'X-Signature' =>
                        $firma,
                ]
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'recibido',
                true
            );


        /*
        |--------------------------------------------------------------------------
        | Debe prevalecer GET /v1/orders/{id}
        |--------------------------------------------------------------------------
        */

        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->proveedor_payment_id
        )->not->toBe(
            'PAY-SIMULADOR'
        );


        expect(
            $pago->requiere_revision
        )->toBeFalse();
    }
);

test(
    'reconciliador recupera pago aprobado cuando webhook no llego',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Preparar Pago pendiente
        |--------------------------------------------------------------------------
        */

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        /*
         * El reconciliador solamente consulta
         * pagos con cierta antigüedad.
         */
        $pago->forceFill([
            'created_at' =>
                now()
                    ->subMinutes(5),

            'ultima_verificacion_en' =>
                null,
        ])->saveQuietly();


        /*
        |--------------------------------------------------------------------------
        | Simulamos Mercado Pago APROBADO
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        |
        | No enviamos ningún Webhook.
        |
        */

        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processed',
                    'accredited'
                ),
                200
            ),
        ]);


        /*
        |--------------------------------------------------------------------------
        | Ejecutar reconciliador
        |--------------------------------------------------------------------------
        */

        $codigoSalida =
            Artisan::call(
                'pagos:reconciliar-pendientes',
                [
                    '--antiguedad' =>
                        1,

                    '--limite' =>
                        100,
                ]
            );


        expect(
            $codigoSalida
        )->toBe(
            0
        );


        /*
        |--------------------------------------------------------------------------
        | Pago
        |--------------------------------------------------------------------------
        */

        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->proveedor_payment_id
        )->not->toBeNull();


        expect(
            $pago->aprobado_en
        )->not->toBeNull();


        expect(
            $pago->requiere_revision
        )->toBeFalse();


        /*
        |--------------------------------------------------------------------------
        | Reserva
        |--------------------------------------------------------------------------
        */

        $reserva =
            $pago
                ->reserva
                ->fresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBe(
            $pago->id
        );


        /*
        |--------------------------------------------------------------------------
        | Ocupaciones
        |--------------------------------------------------------------------------
        */

        $ocupaciones =
            $reserva
                ->ocupaciones()
                ->get();


        expect(
            $ocupaciones
                ->every(
                    fn ($ocupacion) =>
                        $ocupacion
                            ->estado
                        ===
                        EstadoOcupacionAsiento::CONFIRMADO
                )
        )->toBeTrue();


        /*
        |--------------------------------------------------------------------------
        | La prueba clave:
        |--------------------------------------------------------------------------
        |
        | NO existe EventoWebhookPago.
        |
        | Por tanto la recuperación ocurrió
        | únicamente mediante reconciliación.
        |
        */

        expect(
            EventoWebhookPago::query()
                ->where(
                    'data_id',
                    $pago->proveedor_order_id
                )
                ->exists()
        )->toBeFalse();
    }
);



test(
    'reconciliador conserva pendiente cuando mercado pago falla temporalmente',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $pago->forceFill([
            'created_at' =>
                now()
                    ->subMinutes(5),

            'ultima_verificacion_en' =>
                null,
        ])->saveQuietly();


        /*
         * Mercado Pago está temporalmente caído.
         */
        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                [
                    'message' =>
                        'Internal Server Error',
                ],
                500
            ),
        ]);


        $codigoSalida =
            Artisan::call(
                'pagos:reconciliar-pendientes',
                [
                    '--antiguedad' =>
                        1,
                ]
            );


        /*
         * El comando informa que hubo problemas.
         */
        expect(
            $codigoSalida
        )->toBe(
            1
        );


        $pago->refresh();


        /*
         * CRÍTICO:
         *
         * 500 de Mercado Pago NO significa
         * pago rechazado.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::PENDIENTE
        );


        expect(
            $pago->ultima_verificacion_en
        )->not->toBeNull();


        expect(
            $pago->error_codigo
        )->not->toBeNull();


        expect(
            $pago->error_mensaje
        )->not->toBeNull();


        /*
         * La Reserva tampoco puede confirmarse.
         */
        expect(
            $pago
                ->reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);


test(
    'pago aprobado sin payment id requiere revision y no confirma reserva',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processed',
                    'accredited',
                    null,
                    null,
                    'PEN',

                    /*
                     * Mercado Pago informa aprobación
                     * pero no proporciona payment_id.
                     */
                    null
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-APROBADO-SIN-PAYMENT-ID'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        /*
         * Financiera y externamente fue informado
         * como aprobado.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        /*
         * Pero no es seguro confirmarlo.
         */
        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            $pago->proveedor_payment_id
        )->toBeNull();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'payment_id'
        );


        /*
         * La reserva no puede confirmarse.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();
    }
);

test(
    'pago aprobado con moneda diferente requiere revision',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processed',
                    'accredited',
                    null,
                    null,

                    /*
                     * Nuestra operación esperaba PEN.
                     */
                    'USD'
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-MONEDA-INCORRECTA'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'moneda'
        );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);



test(
    'pago aprobado sin moneda requiere revision',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processed',
                    'accredited',
                    null,
                    null,

                    /*
                     * Mercado Pago no informa moneda.
                     */
                    null
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-SIN-MONEDA'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'moneda'
        );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);

test(
    'payment id asociado a otro pago requiere revision sin violar unique',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        /*
        |--------------------------------------------------------------------------
        | Otro Pago ya posee ese payment_id
        |--------------------------------------------------------------------------
        */

        Pago::query()->create([

            'reserva_id' =>
                $pago->reserva_id,

            'iniciado_por_usuario_id' =>
                $pago->iniciado_por_usuario_id,

            'proveedor' =>
                $pago->proveedor,

            'canal' =>
                $pago->canal,

            'idempotency_key' =>
                (string)
                Str::uuid(),

            'external_reference' =>
                'PAY-DUP-'
                .Str::upper(
                    Str::random(20)
                ),

            'proveedor_order_id' =>
                'ORDER-DUP-'
                .Str::uuid(),

            /*
             * El mismo ID que luego informará
             * Mercado Pago para nuestro Pago objetivo.
             */
            'proveedor_payment_id' =>
                'PAY01RF08WEBHOOK',

            'monto' =>
                $pago->monto,

            'moneda' =>
                $pago->moneda,

            'estado' =>
                EstadoPago::APROBADO,

            'estado_proveedor' =>
                'processed',

            'detalle_estado' =>
                'accredited',

            'requiere_revision' =>
                false,

            'aprobado_en' =>
                now(),
        ]);


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                respuestaConsultaOrdenWebhookRf08(
                    $pago,
                    'processed',
                    'accredited'
                ),
                200
            ),
        ]);


        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-PAYMENT-ID-DUPLICADO'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        /*
         * No hubo violación UNIQUE ni error 500.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        /*
         * No copiamos el payment_id conflictivo.
         */
        expect(
            $pago->proveedor_payment_id
        )->toBeNull();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'ya está asociado a otro intento'
        );


        /*
         * Tampoco confirmamos la reserva.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);

test(
    'pago aprobado no regresa a pendiente por evento posterior',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        Auth::forgetGuards();


        /*
        |--------------------------------------------------------------------------
        | Dos consultas consecutivas
        |--------------------------------------------------------------------------
        */

        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::sequence()

                /*
                 * Primera notificación.
                 */
                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processed',
                        'accredited'
                    ),
                    200
                )

                /*
                 * Evento posterior obsoleto/anómalo.
                 */
                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processing',
                        'processing'
                    ),
                    200
                ),
        ]);


        /*
         * Primera notificación.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-APROBADO-INICIAL'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        /*
         * Segundo evento.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-PROCESSING-TARDIO',
            'order.updated'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        /*
         * CRÍTICO:
         *
         * Nunca APROBADO -> PENDIENTE.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        /*
         * Registramos la anomalía.
         */
        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'regresión'
        );


        /*
         * La reserva tampoco retrocede.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBe(
            $pago->id
        );
    }
);


test(
    'reembolso parcial requiere revision pero conserva reserva confirmada',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::sequence()

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processed',
                        'accredited'
                    ),
                    200
                )

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processed',
                        'partially_refunded'
                    ),
                    200
                ),
        ]);


        /*
         * Pago normal.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-ANTES-REEMBOLSO-PARCIAL'
        )
            ->assertOk();


        expect(
            $pago
                ->fresh()
                ->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $reserva
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        /*
         * Reembolso parcial.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-REEMBOLSO-PARCIAL',
            'order.refunded'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'reembolso parcial'
        );


        /*
         * NO cancelamos automáticamente.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reserva
                ->ocupaciones()
                ->get()
                ->every(
                    fn ($ocupacion) =>
                        $ocupacion->estado
                        ===
                        EstadoOcupacionAsiento::CONFIRMADO
                )
        )->toBeTrue();
    }
);


test(
    'contracargo requiere revision y no cancela automaticamente la reserva',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );


        $pago =
            $datos['pago'];


        $reserva =
            $datos['reserva'];


        Auth::forgetGuards();


        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::sequence()

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processed',
                        'accredited'
                    ),
                    200
                )

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'charged_back',
                        'charged_back'
                    ),
                    200
                ),
        ]);


        /*
         * Primero el pago fue válido.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-ANTES-CONTRACARGO'
        )
            ->assertOk();


        /*
         * Después aparece el contracargo.
         */
        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-CONTRACARGO',
            'order.updated'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();


        /*
         * Conservamos el registro del cobro
         * pero levantamos alerta operacional.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'contracargo'
        );


        /*
         * No destruimos automáticamente
         * una reserva posiblemente ya utilizada.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reserva
                ->ocupaciones()
                ->get()
                ->every(
                    fn ($ocupacion) =>
                        $ocupacion->estado
                        ===
                        EstadoOcupacionAsiento::CONFIRMADO
                )
        )->toBeTrue();
    }
);

test(
    'pago reembolsado no puede volver a aprobado por evento tardio',
    function () {

        $datos =
            crearPagoPendienteParaWebhookRf08(
                $this
            );

        $pago =
            $datos['pago'];

        $reserva =
            $datos['reserva'];

        Auth::forgetGuards();


        /*
        |--------------------------------------------------------------------------
        | Mercado Pago responderá tres estados
        |--------------------------------------------------------------------------
        |
        | 1. Pago acreditado.
        | 2. Reembolso total.
        | 3. Evento antiguo que vuelve a decir acreditado.
        |
        */

        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::sequence()

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processed',
                        'accredited'
                    ),
                    200
                )

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'refunded',
                        'refunded'
                    ),
                    200
                )

                ->push(
                    respuestaConsultaOrdenWebhookRf08(
                        $pago,
                        'processed',
                        'accredited'
                    ),
                    200
                ),
        ]);


        /*
        |--------------------------------------------------------------------------
        | 1. Aprobar
        |--------------------------------------------------------------------------
        */

        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-APROBADO-TERMINAL'
        )
            ->assertOk();


        $pago->refresh();
        $reserva->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        /*
        |--------------------------------------------------------------------------
        | 2. Reembolsar
        |--------------------------------------------------------------------------
        */

        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-REEMBOLSO-TERMINAL',
            'order.refunded'
        )
            ->assertOk();


        $pago->refresh();
        $reserva->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::REEMBOLSADO
        );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );


        /*
        |--------------------------------------------------------------------------
        | 3. Llega un evento viejo
        |--------------------------------------------------------------------------
        |
        | Aunque Mercado Pago responda nuevamente
        | processed/accredited, nuestro estado
        | REEMBOLSADO es terminal.
        |
        */

        enviarWebhookOrdenRf08(
            $this,
            $pago,
            'EVENTO-APROBADO-TARDIO-DESPUES-REEMBOLSO',
            'order.updated'
        )
            ->assertOk();


        $pago->refresh();
        $reserva->refresh();


        /*
         * Nunca:
         *
         * REEMBOLSADO -> APROBADO
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::REEMBOLSADO
        );


        /*
         * Registramos que recibimos
         * una secuencia inconsistente.
         */
        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            (string)
            $pago->motivo_revision
        )->toContain(
            'regresión'
        );


        /*
         * Tampoco revivimos la reserva.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );
    }
);

