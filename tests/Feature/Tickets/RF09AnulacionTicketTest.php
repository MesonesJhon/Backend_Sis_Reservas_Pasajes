<?php

use App\Actions\Tickets\AnularTicketsReserva;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTicket;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use App\Models\Ticket;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Helpers\CrearViajeProgramable;

uses(RefreshDatabase::class);


beforeEach(function () {

    $this->seed(
        TestingSeeder::class
    );


    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
    |--------------------------------------------------------------------------
    | Programar viaje
    |--------------------------------------------------------------------------
    */

    autenticarAdministrador();


    $this->patchJson(
        "/api/v1/viajes/{$this->viaje->id}/estado",
        [
            'estado' =>
                'PROGRAMADO',
        ]
    )
        ->assertOk();


    $this->viaje =
        $this->viaje->fresh();


    Auth::forgetGuards();


    $this->puntos =
        $this
            ->viaje
            ->puntosViaje()
            ->orderBy(
                'orden'
            )
            ->get();


    $this->asiento =
        $this
            ->viaje
            ->asientosViaje()
            ->firstOrFail();
});


function crearReservaConfirmadaRf09Etapa6(
    $test
): array {

    /*
    |--------------------------------------------------------------------------
    | Cliente
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
    | Bloquear asiento
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
                'asiento_viaje_id',
                $test->asiento->id
            )

            ->latest()

            ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | Crear reserva
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

                'pasajeros' => [
                    [
                        'ocupacion_id' =>
                            $ocupacion->id,

                        'tipo_documento' =>
                            'DNI',

                        'numero_documento' =>
                            '70000001',

                        'nombres' =>
                            'Pasajero',

                        'apellidos' =>
                            'Anulacion',
                    ],
                ],
            ]
        );


    $response->assertCreated();


    $reserva =
        Reserva::query()
            ->findOrFail(
                $response->json(
                    'data.id'
                )
            );


    /*
    |--------------------------------------------------------------------------
    | Confirmar
    |--------------------------------------------------------------------------
    */

    $operador =
        usuarioConRolViajes(
            'OPERADOR'
        );


    $test->actingAs(
        $operador,
        'sanctum'
    );


    $test->patchJson(
        "/api/v1/reservas/{$reserva->id}/confirmar"
    )
        ->assertOk();


    $reserva->refresh();

    $ocupacion->refresh();


    $ticket =
        Ticket::query()

            ->where(
                'reserva_id',
                $reserva->id
            )

            ->firstOrFail();


    return [
        $cliente,
        $operador,
        $reserva,
        $ocupacion,
        $ticket,
    ];
}


test(
    'cancelar una reserva confirmada anula su ticket vigente',
    function () {

        [
            $cliente,
            ,
            $reserva,
            $ocupacion,
            $ticket,
        ] =
            crearReservaConfirmadaRf09Etapa6(
                $this
            );


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        /*
        |--------------------------------------------------------------------------
        | Cliente cancela su reserva
        |--------------------------------------------------------------------------
        */

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $this->patchJson(
            "/api/v1/reservas/{$reserva->id}/cancelar",
            [
                'motivo' =>
                    'Cambio de planes',
            ]
        )
            ->assertOk();


        /*
        |--------------------------------------------------------------------------
        | Recargar
        |--------------------------------------------------------------------------
        */

        $reserva->refresh();

        $ocupacion->refresh();

        $ticket->refresh();


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
            $ticket->estado
        )->toBe(
            EstadoTicket::ANULADO
        );


        expect(
            $ticket->anulado_en
        )->not->toBeNull();


        expect(
            $ticket->motivo_anulacion
        )->toContain(
            'Cambio de planes'
        );


        expect(
            $ticket->validado_en
        )->toBeNull();
    }
);

test(
    'anular tickets de una reserva es idempotente',
    function () {

        [
            ,
            ,
            $reserva,
            ,
            $ticket,
        ] =
            crearReservaConfirmadaRf09Etapa6(
                $this
            );


        $action =
            app(
                AnularTicketsReserva::class
            );


        /*
         * Primera ejecución.
         */
        $primeraCantidad =
            $action->ejecutar(
                $reserva->id,
                'Prueba de anulación'
            );


        expect(
            $primeraCantidad
        )->toBe(1);


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::ANULADO
        );


        $primeraFecha =
            $ticket
                ->anulado_en
                ?->timestamp;


        /*
         * Segunda ejecución.
         */
        $segundaCantidad =
            $action->ejecutar(
                $reserva->id,
                'Segundo intento'
            );


        expect(
            $segundaCantidad
        )->toBe(0);


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::ANULADO
        );


        /*
         * No reescribimos la auditoría original.
         */
        expect(
            $ticket
                ->anulado_en
                ?->timestamp
        )->toBe(
            $primeraFecha
        );


        expect(
            $ticket->motivo_anulacion
        )->toBe(
            'Prueba de anulación'
        );
    }
);


test(
    'ticket utilizado no se convierte en anulado',
    function () {

        [
            ,
            $operador,
            $reserva,
            ,
            $ticket,
        ] =
            crearReservaConfirmadaRf09Etapa6(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Simular ticket ya utilizado
        |--------------------------------------------------------------------------
        */

        $ticket->update([
            'estado' =>
                EstadoTicket::UTILIZADO,

            'validado_en' =>
                now(),

            'validado_por_usuario_id' =>
                $operador->id,
        ]);


        $validadoEnOriginal =
            $ticket
                ->fresh()
                ->validado_en
                ->timestamp;


        /*
        |--------------------------------------------------------------------------
        | Intentar anular tickets de la reserva
        |--------------------------------------------------------------------------
        */

        $cantidad =
            app(
                AnularTicketsReserva::class
            )
                ->ejecutar(
                    $reserva->id,
                    'Reserva cancelada'
                );


        expect(
            $cantidad
        )->toBe(0);


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::UTILIZADO
        );


        expect(
            $ticket->anulado_en
        )->toBeNull();


        expect(
            $ticket->motivo_anulacion
        )->toBeNull();


        expect(
            $ticket
                ->validado_en
                ->timestamp
        )->toBe(
            $validadoEnOriginal
        );


        expect(
            $ticket->validado_por_usuario_id
        )->toBe(
            $operador->id
        );
    }
);




