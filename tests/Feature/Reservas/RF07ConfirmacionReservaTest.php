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
     * Viaje inicial en BORRADOR.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * Crear segundo asiento antes de programar.
     *
     * Nos permitirá comprobar confirmaciones
     * de varios pasajeros.
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


    /*
     * Snapshot del recorrido.
     */
    $this->puntos =
        $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


    /*
     * Snapshot de asientos.
     */
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
|
| Genera una reserva real utilizando:
|
| RF-06:
| asiento -> BLOQUEADO
|
| RF-07:
| reserva -> PENDIENTE_PAGO
| asiento -> RESERVADO
|
*/

function crearReservaPendienteRf07Etapa5(
    $test,
    $cliente,
    array $asientoViajeIds
): array {

    $test->actingAs(
        $cliente,
        'sanctum'
    );


    $ocupaciones = [];


    foreach (
        $asientoViajeIds
        as $indice => $asientoViajeId
    ) {

        /*
        |--------------------------------------------------------------------------
        | Bloqueo RF-06
        |--------------------------------------------------------------------------
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


    /*
    |--------------------------------------------------------------------------
    | Construir pasajeros
    |--------------------------------------------------------------------------
    */

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
                        12345678
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
                'Prueba',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Reserva RF-07
    |--------------------------------------------------------------------------
    */

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


    return [

        'reserva' =>
            Reserva::findOrFail(
                $response->json(
                    'data.id'
                )
            ),

        'ocupaciones' =>
            collect(
                $ocupaciones
            ),
    ];
}


/*
|--------------------------------------------------------------------------
| 1. Autenticación
|--------------------------------------------------------------------------
*/

test(
    'confirmar reserva requiere autenticacion',
    function () {

        $this->patchJson(
            '/api/v1/reservas/1/confirmar'
        )
            ->assertUnauthorized();
    }
);


/*
|--------------------------------------------------------------------------
| 2. Cliente no confirma directamente
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede confirmar directamente su reserva',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa5(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        /*
         * El helper dejó autenticado al cliente.
         *
         * El middleware reservas.confirmar
         * debe rechazar la operación.
         */
        $this->patchJson(
            "/api/v1/reservas/{$datos['reserva']->id}/confirmar"
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
            $datos['ocupaciones']
                ->first()
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 3. Operador confirma reserva
|--------------------------------------------------------------------------
*/

test(
    'operador puede confirmar una reserva pendiente',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa5(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        /*
         * Cambiamos al operador.
         */
        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $response =
            $this->patchJson(
                "/api/v1/reservas/{$datos['reserva']->id}/confirmar"
            );


        $response
            ->assertOk()

            ->assertJsonPath(
                'data.estado',
                EstadoReserva::CONFIRMADA->value
            );


        $reserva =
            $datos['reserva']
                ->fresh();


        $ocupacion =
            $datos['ocupaciones']
                ->first()
                ->fresh();


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reserva->confirmada_en
        )->not->toBeNull();


        /*
         * Ya no está pendiente de vencer.
         */
        expect(
            $reserva->expira_en
        )->toBeNull();


        /*
         * RESERVADO -> CONFIRMADO
         */
        expect(
            $ocupacion->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        /*
         * Una ocupación confirmada tampoco
         * tiene expiración temporal.
         */
        expect(
            $ocupacion->expira_en
        )->toBeNull();
    }
);


/*
|--------------------------------------------------------------------------
| 4. Confirmación múltiple
|--------------------------------------------------------------------------
*/

test(
    'confirmar reserva confirma todos los pasajeros y asientos',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa5(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                    $this->asientoA2->id,
                ]
            );


        expect(
            $datos['ocupaciones']
                ->count()
        )->toBe(2);


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


        expect(
            $datos['reserva']
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        foreach (
            $datos['ocupaciones']
            as $ocupacion
        ) {

            expect(
                $ocupacion
                    ->fresh()
                    ->estado
            )->toBe(
                EstadoOcupacionAsiento::CONFIRMADO
            );
        }
    }
);


/*
|--------------------------------------------------------------------------
| 5. No confirmar dos veces
|--------------------------------------------------------------------------
*/

test(
    'una reserva confirmada no puede confirmarse nuevamente',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa5(
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


        $url =
            "/api/v1/reservas/"
            .$datos['reserva']->id
            .'/confirmar';


        /*
         * Primera confirmación.
         */
        $this->patchJson(
            $url
        )->assertOk();


        /*
         * Segunda confirmación.
         */
        $this->patchJson(
            $url
        )
            ->assertUnprocessable()

            ->assertJsonPath(
                'mensaje',
                'La reserva no puede confirmarse desde su estado actual.'
            );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Reserva vencida
|--------------------------------------------------------------------------
*/

test(
    'una reserva vencida no puede confirmarse y queda expirada',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa5(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                ]
            );


        /*
         * Vencemos tanto reserva como ocupación.
         *
         * No ejecutamos el scheduler.
         */
        $datos['reserva']->update([
            'expira_en' =>
                now()->subMinute(),
        ]);


        foreach (
            $datos['ocupaciones']
            as $ocupacion
        ) {
            $ocupacion->update([
                'expira_en' =>
                    now()->subMinute(),
            ]);
        }


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
            ->assertUnprocessable()

            ->assertJsonPath(
                'mensaje',
                'La reserva ya expiró y no puede confirmarse.'
            );


        /*
         * Muy importante:
         *
         * aunque recibimos 422, la expiración
         * debe haber quedado persistida.
         */
        expect(
            $datos['reserva']
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::EXPIRADA
        );


        expect(
            $datos['reserva']
                ->fresh()
                ->expirada_en
        )->not->toBeNull();


        expect(
            $datos['ocupaciones']
                ->first()
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 7. Atomicidad
|--------------------------------------------------------------------------
*/

test(
    'fallo en una ocupacion impide confirmacion parcial',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        $datos =
            crearReservaPendienteRf07Etapa5(
                $this,
                $cliente,
                [
                    $this->asientoA1->id,
                    $this->asientoA2->id,
                ]
            );


        $ocupacionUno =
            $datos['ocupaciones']
                ->get(0);


        $ocupacionDos =
            $datos['ocupaciones']
                ->get(1);


        /*
         * Simulamos inconsistencia antes
         * de la confirmación.
         */
        $ocupacionDos->update([
            'estado' =>
                EstadoOcupacionAsiento::LIBERADO,

            'expira_en' =>
                null,
        ]);


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
            ->assertUnprocessable();


        /*
         * Reserva continúa pendiente.
         */
        expect(
            $datos['reserva']
                ->fresh()
                ->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        /*
         * El asiento válido NO debe haberse
         * confirmado parcialmente.
         */
        expect(
            $ocupacionUno
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );


        /*
         * Conservamos la modificación que simuló
         * el problema.
         */
        expect(
            $ocupacionDos
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );
    }
);
