<?php

use App\Actions\Tickets\EmitirTicketsReserva;
use App\Actions\Pagos\AplicarPagoAReserva;
use App\Enums\CanalPago;
use App\Enums\EstadoPago;
use App\Enums\ProveedorPago;
use App\Models\Pago;
use Illuminate\Support\Str;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTicket;
use App\Enums\TipoAsiento;
use App\Exceptions\OperacionTicketInvalidaException;
use App\Models\Asiento;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use App\Models\Ticket;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Helpers\CrearViajeProgramable;

uses(RefreshDatabase::class);


/*
|--------------------------------------------------------------------------
| Preparación RF-09
|--------------------------------------------------------------------------
*/

beforeEach(function () {

    $this->seed(
        TestingSeeder::class
    );


    /*
     * Viaje BORRADOR inicial.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * Agregamos A2 para probar múltiples pasajeros.
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
     * BORRADOR -> PROGRAMADO.
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
| Helper
|--------------------------------------------------------------------------
*/

function crearReservaPendienteRf09(
    $test,
    $cliente,
    array $asientoViajeIds
): Reserva {

    $test->actingAs(
        $cliente,
        'sanctum'
    );


    $ocupaciones = [];


    foreach (
        $asientoViajeIds
        as $asientoViajeId
    ) {

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


        $ocupaciones[] =
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
    }


    $pasajeros = [];


    foreach (
        $ocupaciones
        as $indice => $ocupacion
    ) {

        $pasajeros[] = [

            'ocupacion_id' =>
                $ocupacion->id,

            'tipo_documento' =>
                'DNI',

            'numero_documento' =>
                str_pad(
                    (string) (
                        70000001
                        + $indice
                    ),
                    8,
                    '0',
                    STR_PAD_LEFT
                ),

            'nombres' =>
                'Pasajero '
                .($indice + 1),

            'apellidos' =>
                'Ticket',
        ];
    }


    $response =
        $test->postJson(
            '/api/v1/reservas',
            [
                'viaje_id' =>
                    $test->viaje->id,

                'telefono_contacto' =>
                    '999888777',

                'pasajeros' =>
                    $pasajeros,
            ]
        );


    $response->assertCreated();


    return Reserva::query()
        ->findOrFail(
            $response->json(
                'data.id'
            )
        );
}


/*
|--------------------------------------------------------------------------
| 1. No emitir antes de confirmar
|--------------------------------------------------------------------------
*/

test(
    'reserva pendiente no puede emitir tickets',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaPendienteRf09(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            fn () =>
                app(
                    EmitirTicketsReserva::class
                )->ejecutar(
                    $reserva->id
                )
        )->toThrow(
            OperacionTicketInvalidaException::class
        );


        expect(
            Ticket::query()->count()
        )->toBe(0);
    }
);


/*
|--------------------------------------------------------------------------
| 2. Emisión automática
|--------------------------------------------------------------------------
*/

test(
    'confirmar reserva genera automaticamente un ticket por pasajero',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaPendienteRf09(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        expect(
            Ticket::query()->count()
        )->toBe(0);


        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->patchJson(
            "/api/v1/reservas/{$reserva->id}/confirmar"
        )
            ->assertOk();


        $reserva->refresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        $pasajero =
            $reserva
                ->pasajeros()
                ->firstOrFail();


        $ticket =
            Ticket::query()
                ->where(
                    'pasajero_reserva_id',
                    $pasajero->id
                )
                ->firstOrFail();


        expect(
            $ticket->reserva_id
        )->toBe(
            $reserva->id
        );


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticket->emitido_en
        )->not->toBeNull();


        expect(
            $ticket->codigo
        )->toStartWith(
            'TKT-'
        );


        /*
         * TKT- + ULID de 26 caracteres.
         */
        expect(
            strlen(
                $ticket->codigo
            )
        )->toBe(
            30
        );
    }
);


/*
|--------------------------------------------------------------------------
| 3. Un ticket por pasajero
|--------------------------------------------------------------------------
*/

test(
    'reserva con varios pasajeros genera un ticket diferente para cada uno',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaPendienteRf09(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                    $this->asientoA2->id,
                ]
            );


        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->patchJson(
            "/api/v1/reservas/{$reserva->id}/confirmar"
        )
            ->assertOk();


        $pasajeros =
            $reserva
                ->pasajeros()
                ->orderBy('id')
                ->get();


        $tickets =
            Ticket::query()

                ->where(
                    'reserva_id',
                    $reserva->id
                )

                ->orderBy('id')

                ->get();


        expect(
            $pasajeros->count()
        )->toBe(2);


        expect(
            $tickets->count()
        )->toBe(2);


        /*
         * Cada pasajero posee exactamente
         * un ticket.
         */
        expect(
            $tickets
                ->pluck(
                    'pasajero_reserva_id'
                )
                ->sort()
                ->values()
                ->all()
        )->toBe(
            $pasajeros
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );


        /*
         * Los códigos no pueden repetirse.
         */
        expect(
            $tickets
                ->pluck('codigo')
                ->unique()
                ->count()
        )->toBe(2);
    }
);


/*
|--------------------------------------------------------------------------
| 4. Idempotencia
|--------------------------------------------------------------------------
*/

test(
    'emitir tickets repetidamente no genera duplicados',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaPendienteRf09(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        /*
         * Primera emisión ocurre automáticamente
         * al confirmar.
         */
        $this->patchJson(
            "/api/v1/reservas/{$reserva->id}/confirmar"
        )
            ->assertOk();


        $ticketOriginal =
            Ticket::query()
                ->where(
                    'reserva_id',
                    $reserva->id
                )
                ->firstOrFail();


        /*
         * Ejecutamos explícitamente dos veces más.
         */
        $accion =
            app(
                EmitirTicketsReserva::class
            );


        $accion->ejecutar(
            $reserva->id
        );


        $accion->ejecutar(
            $reserva->id
        );


        expect(
            Ticket::query()
                ->where(
                    'reserva_id',
                    $reserva->id
                )
                ->count()
        )->toBe(1);


        /*
         * Incluso conservamos exactamente
         * el mismo código.
         */
        expect(
            Ticket::query()
                ->where(
                    'reserva_id',
                    $reserva->id
                )
                ->value(
                    'codigo'
                )
        )->toBe(
            $ticketOriginal->codigo
        );
    }
);
/*
|--------------------------------------------------------------------------
| 5. Emisión automática mediante pago aprobado
|--------------------------------------------------------------------------
*/

test(
    'pago aprobado genera automaticamente los tickets de la reserva',
    function () {

        /*
        |--------------------------------------------------------------------------
        | 1. Crear cliente y reserva pendiente
        |--------------------------------------------------------------------------
        */

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $reserva =
            crearReservaPendienteRf09(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        /*
         * Todavía no debe existir ningún ticket.
         */
        expect(
            Ticket::query()
                ->where(
                    'reserva_id',
                    $reserva->id
                )
                ->count()
        )->toBe(0);


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        /*
        |--------------------------------------------------------------------------
        | 2. Obtener pasajero y ocupación
        |--------------------------------------------------------------------------
        */

        $pasajero =
            $reserva
                ->pasajeros()
                ->firstOrFail();


        $ocupacion =
            $pasajero
                ->ocupacionAsiento()
                ->firstOrFail();


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );


        /*
        |--------------------------------------------------------------------------
        | 3. Simular Pago ya aprobado financieramente
        |--------------------------------------------------------------------------
        |
        | RF-08 ya prueba:
        |
        | Mercado Pago
        |     ↓
        | Webhook
        |     ↓
        | sincronización
        |     ↓
        | Pago APROBADO
        |
        | Aquí RF-09 solamente necesita comprobar
        | qué ocurre desde Pago APROBADO en adelante.
        |
        */

        $pago =
            Pago::query()->create([

                'reserva_id' =>
                    $reserva->id,

                'iniciado_por_usuario_id' =>
                    $cliente->id,

                'proveedor' =>
                    ProveedorPago::MERCADO_PAGO,

                'canal' =>
                    CanalPago::WEB,

                'idempotency_key' =>
                    (string) Str::uuid(),

                'external_reference' =>
                    'PAY-'.Str::ulid(),

                'proveedor_order_id' =>
                    'ORDER-RF09-'.Str::ulid(),

                'proveedor_payment_id' =>
                    'PAYMENT-RF09-'.Str::ulid(),

                'monto' =>
                    $reserva->total,

                'moneda' =>
                    'PEN',

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


        /*
        |--------------------------------------------------------------------------
        | 4. Aplicar el pago a la reserva
        |--------------------------------------------------------------------------
        */

        $resultado =
            app(
                AplicarPagoAReserva::class
            )->ejecutar(
                $pago->id
            );


        expect(
            $resultado
        )->toBeTrue();


        /*
        |--------------------------------------------------------------------------
        | 5. Recargar estado real de BD
        |--------------------------------------------------------------------------
        */

        $reserva->refresh();

        $ocupacion->refresh();

        $pago->refresh();


        /*
        |--------------------------------------------------------------------------
        | 6. Comprobar confirmación comercial
        |--------------------------------------------------------------------------
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


        /*
        |--------------------------------------------------------------------------
        | 7. Comprobar asiento confirmado
        |--------------------------------------------------------------------------
        */

        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        expect(
            $ocupacion->expira_en
        )->toBeNull();


        /*
        |--------------------------------------------------------------------------
        | 8. Comprobar emisión automática del ticket
        |--------------------------------------------------------------------------
        */

        $ticket =
            Ticket::query()

                ->where(
                    'reserva_id',
                    $reserva->id
                )

                ->where(
                    'pasajero_reserva_id',
                    $pasajero->id
                )

                ->firstOrFail();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticket->codigo
        )->toStartWith(
            'TKT-'
        );


        expect(
            $ticket->emitido_en
        )->not->toBeNull();


        /*
         * Solamente debe existir un ticket
         * para este pasajero.
         */
        expect(
            Ticket::query()

                ->where(
                    'pasajero_reserva_id',
                    $pasajero->id
                )

                ->count()
        )->toBe(1);
    }
);
