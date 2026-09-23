<?php

use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
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
     * Crear viaje BORRADOR.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * Programarlo mediante el flujo real.
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
| Genera:
|
| BLOQUEADO
|    ↓
| Reserva PENDIENTE_PAGO
|    ↓
| Ocupación RESERVADO
|
*/

function crearReservaPendienteRf07Etapa4(
    $test,
    $cliente
): array {

    $test->actingAs(
        $cliente,
        'sanctum'
    );


    /*
     * RF-06:
     * bloqueo real.
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

            ->where(
                'asiento_viaje_id',
                $test->asiento->id
            )

            ->latest()

            ->firstOrFail();


    /*
     * RF-07:
     * reserva.
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


    $reserva =
        Reserva::findOrFail(
            $response->json(
                'data.id'
            )
        );


    return [
        'reserva' =>
            $reserva,

        'ocupacion' =>
            $ocupacion->fresh(),
    ];
}


/*
|--------------------------------------------------------------------------
| 1. Cancelación
|--------------------------------------------------------------------------
*/

test(
    'cliente puede cancelar su reserva pendiente y liberar el asiento',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa4(
                $this,
                $cliente
            );


        $reserva =
            $datos['reserva'];

        $ocupacion =
            $datos['ocupacion'];


        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );


        $response =
            $this->patchJson(
                "/api/v1/reservas/{$reserva->id}/cancelar",
                [
                    'motivo' =>
                        'Cambio de planes',
                ]
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'data.estado',
                EstadoReserva::CANCELADA->value
            )

            ->assertJsonPath(
                'data.motivo_cancelacion',
                'Cambio de planes'
            );


        $reserva->refresh();

        $ocupacion->refresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );


        expect(
            $reserva->cancelada_en
        )->not->toBeNull();


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
| 2. Seguridad
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede cancelar reserva ajena',
    function () {

        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa4(
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


        $this->patchJson(
            "/api/v1/reservas/{$datos['reserva']->id}/cancelar",
            [
                'motivo' =>
                    'Intento no autorizado',
            ]
        )
            ->assertForbidden();


        expect(
            $datos['reserva']
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $datos['ocupacion']
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 3. No cancelar dos veces
|--------------------------------------------------------------------------
*/

test(
    'una reserva cancelada no puede cancelarse nuevamente',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa4(
                $this,
                $cliente
            );


        $url =
            "/api/v1/reservas/"
            .$datos['reserva']->id
            .'/cancelar';


        $this->patchJson(
            $url,
            [
                'motivo' =>
                    'Primera cancelación',
            ]
        )->assertOk();


        $this->patchJson(
            $url,
            [
                'motivo' =>
                    'Segunda cancelación',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'mensaje',
                'La reserva no puede cancelarse desde su estado actual.'
            );
    }
);


/*
|--------------------------------------------------------------------------
| 4. Expiración automática
|--------------------------------------------------------------------------
*/

test(
    'comando expira reserva pendiente vencida y libera asiento',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa4(
                $this,
                $cliente
            );


        /*
         * Simulamos vencimiento.
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
         * Ejecutamos el comando real.
         */
        $this->artisan(
            'reservas:expirar-pendientes'
        )
            ->assertSuccessful();


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupacion']
                ->fresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::EXPIRADA
        );


        expect(
            $reserva->expirada_en
        )->not->toBeNull();


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
| 5. No expirar antes de tiempo
|--------------------------------------------------------------------------
*/

test(
    'comando no expira reserva pendiente vigente',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa4(
                $this,
                $cliente
            );


        expect(
            $datos['reserva']
                ->expira_en
                ->isFuture()
        )->toBeTrue();


        $this->artisan(
            'reservas:expirar-pendientes'
        )
            ->assertSuccessful();


        expect(
            $datos['reserva']
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $datos['ocupacion']
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Disponibilidad inmediata
|--------------------------------------------------------------------------
*/

test(
    'reserva pendiente vencida libera disponibilidad sin esperar scheduler',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa4(
                $this,
                $cliente
            );


        /*
         * Vencemos la reserva y la ocupación,
         * pero NO ejecutamos el comando.
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
        |--------------------------------------------------------------------------
        | Consulta de disponibilidad
        |--------------------------------------------------------------------------
        */

        $response =
            $this->getJson(
                "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos"
                .'?punto_origen_id='
                .$this->puntos->first()->punto_id
                .'&punto_destino_id='
                .$this->puntos->last()->punto_id
            );


        $response->assertOk();


        $asiento =
            collect(
                $response->json(
                    'data'
                )
            )
                ->firstWhere(
                    'id',
                    $this->asiento->id
                );


        expect(
            $asiento
        )->not->toBeNull();


        expect(
            $asiento['disponible']
        )->toBeTrue();


        /*
        |--------------------------------------------------------------------------
        | También debe poder bloquearse realmente
        |--------------------------------------------------------------------------
        |
        | No basta con mostrar disponible.
        |
        */

        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    $this->asiento->id,

                'punto_origen_id' =>
                    $this
                        ->puntos
                        ->first()
                        ->punto_id,

                'punto_destino_id' =>
                    $this
                        ->puntos
                        ->last()
                        ->punto_id,
            ]
        )->assertSuccessful();
    }
);
