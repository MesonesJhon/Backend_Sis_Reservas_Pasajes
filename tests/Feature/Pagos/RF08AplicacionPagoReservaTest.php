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
use Illuminate\Support\Facades\DB;
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

    /*
     * Roles, permisos y datos mínimos
     * requeridos por los RF anteriores.
     */
    $this->seed(
        TestingSeeder::class
    );


    /*
    |--------------------------------------------------------------------------
    | Mercado Pago falso
    |--------------------------------------------------------------------------
    |
    | Ningún test de este archivo realizará
    | llamadas reales a Internet.
    |
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
        'services.mercadopago.webhook_secret',
        'SECRET_TEST_APLICACION_RF08'
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
    |--------------------------------------------------------------------------
    | Crear viaje
    |--------------------------------------------------------------------------
    */

    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * Programamos el viaje utilizando
     * el endpoint real del RF-04.
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
     * Ningún test comienza autenticado
     * accidentalmente como administrador.
     */
    Auth::forgetGuards();


    /*
    |--------------------------------------------------------------------------
    | Datos consolidados
    |--------------------------------------------------------------------------
    */

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
| Helper: respuesta al crear Order
|--------------------------------------------------------------------------
|
| Esta respuesta simula:
|
| POST https://api.mercadopago.com/v1/orders
|
*/

function respuestaCreacionOrdenAplicacionRf08(): array
{
    return [

        'id' =>
            'ORDAPLICACIONRF08001',

        'status' =>
            'created',

        'status_detail' =>
            'created',

        'checkout_url' =>
            'https://www.mercadopago.com.pe/checkout/v1/redirect'
            .'?order_id=ORDAPLICACIONRF08001',
    ];
}


/*
|--------------------------------------------------------------------------
| Helper: crear escenario completo
|--------------------------------------------------------------------------
|
| Genera:
|
| RF-06
| BLOQUEADO
|
| RF-07
| Reserva PENDIENTE_PAGO
| Ocupación RESERVADO
|
| RF-08
| Pago PENDIENTE
| Order de Mercado Pago creada
|
*/

function crearEscenarioPagoRf08Aplicacion(
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


    $test->actingAs(
        $cliente,
        'sanctum'
    );


    /*
    |--------------------------------------------------------------------------
    | 2. RF-06 - Bloquear asiento
    |--------------------------------------------------------------------------
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
    )
        ->assertSuccessful();


    $ocupacion =
        OcupacionAsiento::query()

            ->where(
                'usuario_id',
                $cliente->id
            )

            ->where(
                'viaje_id',
                $test->viaje->id
            )

            ->where(
                'asiento_viaje_id',
                $test->asiento->id
            )

            ->latest()

            ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | 3. RF-07 - Crear Reserva
    |--------------------------------------------------------------------------
    */

    $responseReserva =
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


    $responseReserva
        ->assertCreated();


    $reserva =
        Reserva::findOrFail(
            $responseReserva->json(
                'data.id'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | 4. Mercado Pago - crear Order falsa
    |--------------------------------------------------------------------------
    */

    Http::fake([
        'https://api.mercadopago.com/v1/orders'
            =>
        Http::response(
            respuestaCreacionOrdenAplicacionRf08(),
            201
        ),
    ]);


    /*
    |--------------------------------------------------------------------------
    | 5. RF-08 - iniciar Pago
    |--------------------------------------------------------------------------
    */

    $idempotencyKey =
        (string)
        Str::uuid();


    $responsePago =
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


    $responsePago
        ->assertCreated()

        ->assertJsonPath(
            'data.estado',
            EstadoPago::PENDIENTE->value
        );


    $pago =
        Pago::findOrFail(
            $responsePago->json(
                'data.id'
            )
        );


    /*
    |--------------------------------------------------------------------------
    | 6. Comprobar escenario inicial
    |--------------------------------------------------------------------------
    */

    expect(
        $reserva
            ->fresh()
            ->estado
    )->toBe(
        EstadoReserva::PENDIENTE_PAGO
    );


    expect(
        $ocupacion
            ->fresh()
            ->estado
    )->toBe(
        EstadoOcupacionAsiento::RESERVADO
    );


    expect(
        $pago->estado
    )->toBe(
        EstadoPago::PENDIENTE
    );


    return [

        'cliente' =>
            $cliente,

        'reserva' =>
            $reserva->fresh(),

        'ocupacion' =>
            $ocupacion->fresh(),

        'pago' =>
            $pago->fresh(),
    ];
}


/*
|--------------------------------------------------------------------------
| Helper: respuesta oficial GET /v1/orders/{id}
|--------------------------------------------------------------------------
*/

function respuestaConsultaOrdenAplicacionRf08(
    Pago $pago,
    string $estado = 'processed',
    ?string $detalleEstado = 'accredited',
    array $sobrescribir = []
): array {

    $respuesta = [

        'id' =>
            $pago->proveedor_order_id,

        'status' =>
            $estado,

        'status_detail' =>
            $detalleEstado,

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
                        'PAYAPLICACIONRF08001',

                    'status' =>
                        $estado,

                    'status_detail' =>
                        $detalleEstado,
                ],
            ],
        ],
    ];


    /*
     * Permite modificar datos específicos
     * para simular inconsistencias.
     */
    return array_replace_recursive(
        $respuesta,
        $sobrescribir
    );
}


/*
|--------------------------------------------------------------------------
| Helper: firma Webhook
|--------------------------------------------------------------------------
*/

function firmaWebhookAplicacionRf08(
    string $dataId,
    string $requestId,
    string $secret,
    string $timestamp
): string {

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


/*
|--------------------------------------------------------------------------
| Helper: enviar Webhook
|--------------------------------------------------------------------------
*/

function enviarWebhookAplicacionRf08(
    $test,
    Pago $pago,
    string $eventoId,
    ?array $respuestaOrden = null,
    string $accion = 'order.processed'
) {

    /*
     * Mercado Pago no utiliza Sanctum.
     */
    Auth::forgetGuards();


    $secret =
        'SECRET_TEST_APLICACION_RF08';


    config()->set(
        'services.mercadopago.webhook_secret',
        $secret
    );


    /*
    |--------------------------------------------------------------------------
    | Simular consulta server-to-server
    |--------------------------------------------------------------------------
    |
    | Normalmente el helper configura la respuesta.
    |
    | Cuando $respuestaOrden es null significa que
    | el propio test ya preparó una secuencia HTTP.
    |
    */

    if (
        $respuestaOrden !== null
    ) {

        Http::fake([
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}"
                =>
            Http::response(
                $respuestaOrden,
                200
            ),
        ]);
    }


    $requestId =
        'request-'.$eventoId;


    $timestamp =
        (string)
        now()->timestamp;


    $firma =
        firmaWebhookAplicacionRf08(
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


/*
|--------------------------------------------------------------------------
| 1. Pago aprobado válido
|--------------------------------------------------------------------------
*/

test(
    'pago aprobado valido confirma automaticamente la reserva',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        $pago =
            $datos['pago'];


        $response =
            enviarWebhookAplicacionRf08(

                $this,

                $pago,

                'EVENTO-APLICACION-APROBADO',

                respuestaConsultaOrdenAplicacionRf08(
                    $pago,
                    'processed',
                    'accredited'
                )
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'recibido',
                true
            );


        $pago =
            $pago->fresh();


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupacion']
                ->fresh();


        /*
         * Financiero.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeFalse();


        expect(
            $pago->aprobado_en
        )->not->toBeNull();


        /*
         * Comercial.
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


        expect(
            $reserva->confirmada_en
        )->not->toBeNull();


        expect(
            $reserva->expira_en
        )->toBeNull();


        /*
         * Inventario.
         */
        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        expect(
            $ocupacion->expira_en
        )->toBeNull();
    }
);


/*
|--------------------------------------------------------------------------
| 2. Monto inconsistente
|--------------------------------------------------------------------------
*/

test(
    'pago aprobado con monto inconsistente no confirma reserva',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        $pago =
            $datos['pago'];


        $respuesta =
            respuestaConsultaOrdenAplicacionRf08(
                $pago,
                'processed',
                'accredited',
                [
                    'total_amount' =>
                        '1.00',

                    'total_paid_amount' =>
                        '1.00',
                ]
            );


        enviarWebhookAplicacionRf08(
            $this,
            $pago,
            'EVENTO-APLICACION-MONTO',
            $respuesta
        )
            ->assertOk();


        $pago =
            $pago->fresh();


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupacion']
                ->fresh();


        /*
         * Mercado Pago sí dijo aprobado.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        /*
         * Pero encontramos inconsistencia.
         */
        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            $pago->motivo_revision
        )->not->toBeNull();


        /*
         * Por seguridad NO confirmamos
         * la operación comercial.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 3. Pago aprobado después del vencimiento
|--------------------------------------------------------------------------
*/

test(
    'pago aprobado despues del vencimiento no recupera la reserva',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        $pago =
            $datos['pago'];


        /*
        |--------------------------------------------------------------------------
        | Simular vencimiento
        |--------------------------------------------------------------------------
        |
        | El scheduler todavía NO corrió.
        |
        */

        $datos['reserva']->update([
            'expira_en' =>
                now()->subMinute(),
        ]);


        $datos['ocupacion']->update([
            'expira_en' =>
                now()->subMinute(),
        ]);


        /*
         * El proveedor informa pago aprobado.
         */
        enviarWebhookAplicacionRf08(

            $this,

            $pago,

            'EVENTO-APLICACION-TARDIO',

            respuestaConsultaOrdenAplicacionRf08(
                $pago,
                'processed',
                'accredited'
            )
        )
            ->assertOk();


        $pago =
            $pago->fresh();


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupacion']
                ->fresh();


        /*
         * El dinero fue aprobado.
         */
        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        /*
         * Pero requiere revisión/reembolso.
         */
        expect(
            $pago->requiere_revision
        )->toBeTrue();


        expect(
            $pago->motivo_revision
        )->toContain(
            'vencimiento'
        );


        /*
         * La reserva NO revive.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::EXPIRADA
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();


        /*
         * El asiento queda libre.
         */
        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );


        expect(
            $ocupacion->expira_en
        )->toBeNull();
    }
);


/*
|--------------------------------------------------------------------------
| 4. Pago aprobado después de cancelar
|--------------------------------------------------------------------------
*/

test(
    'pago aprobado despues de cancelar requiere revision y no revive reserva',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        /*
         * El helper todavía dejó autenticado
         * al cliente propietario.
         */
        $this->actingAs(
            $datos['cliente'],
            'sanctum'
        );


        /*
        |--------------------------------------------------------------------------
        | Cancelar reserva
        |--------------------------------------------------------------------------
        */

        $this->patchJson(
            "/api/v1/reservas/{$datos['reserva']->id}/cancelar",
            [
                'motivo' =>
                    'El cliente decidió cancelar.',
            ]
        )
            ->assertOk();


        expect(
            $datos['reserva']
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );


        /*
        |--------------------------------------------------------------------------
        | Mercado Pago informa después un pago aprobado
        |--------------------------------------------------------------------------
        */

        enviarWebhookAplicacionRf08(

            $this,

            $datos['pago'],

            'EVENTO-APLICACION-CANCELADA',

            respuestaConsultaOrdenAplicacionRf08(
                $datos['pago'],
                'processed',
                'accredited'
            )
        )
            ->assertOk();


        $pago =
            $datos['pago']
                ->fresh();


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupacion']
                ->fresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::APROBADO
        );


        expect(
            $pago->requiere_revision
        )->toBeTrue();


        /*
         * CANCELADA nunca vuelve a CONFIRMADA.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 5. Pago rechazado
|--------------------------------------------------------------------------
*/

test(
    'pago rechazado no modifica la reserva ni los asientos',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        enviarWebhookAplicacionRf08(

            $this,

            $datos['pago'],

            'EVENTO-APLICACION-RECHAZADO',

            respuestaConsultaOrdenAplicacionRf08(
                $datos['pago'],
                'failed',
                'payment_rejected'
            ),

            'order.failed'
        )
            ->assertOk();


        $pago =
            $datos['pago']
                ->fresh();


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupacion']
                ->fresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::RECHAZADO
        );


        expect(
            $pago->rechazado_en
        )->not->toBeNull();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Webhook repetido
|--------------------------------------------------------------------------
*/

test(
    'webhook aprobado repetido no confirma dos veces la reserva',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        $pago =
            $datos['pago'];


        $respuestaOrden =
            respuestaConsultaOrdenAplicacionRf08(
                $pago,
                'processed',
                'accredited'
            );


        /*
         * Primera notificación.
         */
        enviarWebhookAplicacionRf08(

            $this,

            $pago,

            'EVENTO-APLICACION-IDEMPOTENTE',

            $respuestaOrden
        )
            ->assertOk();


        $reservaPrimera =
            $datos['reserva']
                ->fresh();


        expect(
            $reservaPrimera->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        $fechaConfirmacion =
            $reservaPrimera
                ->confirmada_en
                ?->toDateTimeString();


        /*
         * Mercado Pago reenvía exactamente
         * la misma notificación.
         */
        enviarWebhookAplicacionRf08(

            $this,

            $pago,

            'EVENTO-APLICACION-IDEMPOTENTE',

            $respuestaOrden
        )
            ->assertOk();


        $reservaSegunda =
            $datos['reserva']
                ->fresh();


        /*
         * Sigue confirmada por el mismo pago.
         */
        expect(
            $reservaSegunda->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reservaSegunda->pago_confirmacion_id
        )->toBe(
            $pago->id
        );


        /*
         * No debe existir un segundo evento
         * para el mismo evento_id.
         */
        expect(
            DB::table(
                'eventos_webhook_pago'
            )
                ->where(
                    'evento_id',
                    'EVENTO-APLICACION-IDEMPOTENTE'
                )
                ->count()
        )->toBe(1);


        /*
         * La confirmación original no se reemplaza.
         */
        expect(
            $reservaSegunda
                ->confirmada_en
                ?->toDateTimeString()
        )->toBe(
            $fechaConfirmacion
        );
    }
);


/*
|--------------------------------------------------------------------------
| 7. Reserva confirmada previamente de manera manual
|--------------------------------------------------------------------------
*/

test(
    'pago aprobado para reserva ya confirmada manualmente requiere revision',
    function () {

        $datos =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Confirmación manual RF-07
        |--------------------------------------------------------------------------
        */

        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->patchJson(
            "/api/v1/reservas/{$datos['reserva']->id}/confirmar"
        )
            ->assertOk();


        $reserva =
            $datos['reserva']
                ->fresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        /*
         * Como fue confirmación manual,
         * todavía no existe pago_confirmacion_id.
         */
        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();


        /*
        |--------------------------------------------------------------------------
        | Mercado Pago posteriormente informa APROBADO
        |--------------------------------------------------------------------------
        */

        enviarWebhookAplicacionRf08(

            $this,

            $datos['pago'],

            'EVENTO-APLICACION-YA-CONFIRMADA',

            respuestaConsultaOrdenAplicacionRf08(
                $datos['pago'],
                'processed',
                'accredited'
            )
        )
            ->assertOk();


        $pago =
            $datos['pago']
                ->fresh();


        $reserva =
            $datos['reserva']
                ->fresh();


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
            'confirmada'
        );


        /*
         * No reemplazamos una confirmación
         * previamente realizada.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reserva->pago_confirmacion_id
        )->toBeNull();
    }
);

/*
|--------------------------------------------------------------------------
| Reembolso total
|--------------------------------------------------------------------------
*/

test(
    'reembolso total cancela reserva confirmada y libera asiento',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Preparar escenario
        |--------------------------------------------------------------------------
        */

        $escenario =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        $pago =
            $escenario[
                'pago'
            ];


        $reserva =
            $escenario[
                'reserva'
            ];


        $ocupacion =
            $escenario[
                'ocupacion'
            ];



        $urlConsulta =
            "https://api.mercadopago.com/v1/orders/{$pago->proveedor_order_id}";


        $respuestaAprobada =
            respuestaConsultaOrdenAplicacionRf08(
                $pago,
                'processed',
                'accredited'
            );


        $respuestaReembolsada =
            respuestaConsultaOrdenAplicacionRf08(
                $pago,
                'refunded',
                'refunded'
            );


        /*
        |--------------------------------------------------------------------------
        | Secuencia oficial de Mercado Pago
        |--------------------------------------------------------------------------
        |
        | Primera consulta:
        | processed / accredited
        |
        | Segunda consulta:
        | refunded / refunded
        |
        */

        Http::fake([
            $urlConsulta
                =>
            Http::sequence()

                ->push(
                    $respuestaAprobada,
                    200
                )

                ->push(
                    $respuestaReembolsada,
                    200
                ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Primero aprobar el pago
        |--------------------------------------------------------------------------
        */

        enviarWebhookAplicacionRf08(
            $this,

            $pago,

            'EVENTO-APROBADO-ANTES-REEMBOLSO',

            null,

            'order.processed'
        )
            ->assertOk();


        $pago->refresh();

        $reserva->refresh();

        $ocupacion->refresh();


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


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        /*
        |--------------------------------------------------------------------------
        | Mercado Pago informa reembolso total
        |--------------------------------------------------------------------------
        */

        enviarWebhookAplicacionRf08(
            $this,

            $pago,

            'EVENTO-REEMBOLSO-TOTAL',

            null,

            'order.refunded'
        )
            ->assertOk();


        /*
        |--------------------------------------------------------------------------
        | Resultado financiero
        |--------------------------------------------------------------------------
        */

        $pago->refresh();


        expect(
            $pago->estado
        )->toBe(
            EstadoPago::REEMBOLSADO
        );


        expect(
            $pago->reembolsado_en
        )->not->toBeNull();


        /*
        |--------------------------------------------------------------------------
        | Resultado comercial
        |--------------------------------------------------------------------------
        */

        $reserva->refresh();

        $ocupacion->refresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );


        expect(
            $ocupacion->expira_en
        )->toBeNull();



        Http::assertSentCount(
            2
        );
    }
);

test(
    'reserva con pago aprobado no puede cancelarse sin reembolso',
    function () {

        $escenario =
            crearEscenarioPagoRf08Aplicacion(
                $this
            );


        $cliente =
            $escenario[
                'cliente'
            ];


        $pago =
            $escenario[
                'pago'
            ];


        $reserva =
            $escenario[
                'reserva'
            ];


        $ocupacion =
            $escenario[
                'ocupacion'
            ];


        /*
         * Aprobar pago.
         */
        enviarWebhookAplicacionRf08(
            $this,

            $pago,

            'EVENTO-APROBADO-CANCELACION',

            respuestaConsultaOrdenAplicacionRf08(
                $pago,
                'processed',
                'accredited'
            )
        )
            ->assertOk();


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        /*
         * Intentar cancelar sin devolver dinero.
         */
        $this->patchJson(
            "/api/v1/reservas/{$reserva->id}/cancelar",
            [
                'motivo' =>
                    'Ya no deseo viajar.',
            ]
        )
            ->assertUnprocessable();


        /*
         * Nada debe cambiar.
         */
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


        expect(
            $ocupacion
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );
    }
);
