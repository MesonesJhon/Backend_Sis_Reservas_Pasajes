<?php

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
            ->where(
                'codigo',
                'A1'
            )
            ->firstOrFail();
});

function crearTicketQrGraficoRf09(
    $test,
    $cliente
): array {

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
    | Reserva
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
                            'QR',
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


    $ticket =
        Ticket::query()

            ->where(
                'reserva_id',
                $reserva->id
            )

            ->firstOrFail();


    return [
        $reserva,
        $ticket,
    ];
}

test(
    'cliente puede obtener el qr grafico de su ticket',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketQrGraficoRf09(
                $this,
                $cliente
            );


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $response =
            $this->get(
                "/api/v1/tickets/{$ticket->codigo}/qr"
            );


        $response
            ->assertOk()

            ->assertHeader(
                'Content-Type',
                'image/svg+xml; charset=UTF-8'
            )

            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );


        expect(
            $response->getContent()
        )->toContain(
            '<svg'
        );


        expect(
            $response->getContent()
        )->toContain(
            '</svg>'
        );
    }
);


test(
    'qr grafico no contiene datos personales en texto plano',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketQrGraficoRf09(
                $this,
                $cliente
            );


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $response =
            $this->get(
                "/api/v1/tickets/{$ticket->codigo}/qr"
            )
                ->assertOk();


        $svg =
            $response
                ->getContent();


        expect(
            $svg
        )->not->toContain(
            '70000001'
        );


        expect(
            $svg
        )->not->toContain(
            'Pasajero'
        );


        expect(
            $svg
        )->not->toContain(
            '999888777'
        );
    }
);


test(
    'cliente no puede obtener qr de ticket ajeno',
    function () {

        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketQrGraficoRf09(
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


        $this->get(
            "/api/v1/tickets/{$ticket->codigo}/qr"
        )
            ->assertForbidden();
    }
);

test(
    'operador puede obtener qr grafico de un ticket',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketQrGraficoRf09(
                $this,
                $cliente
            );


        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $response =
            $this->get(
                "/api/v1/tickets/{$ticket->codigo}/qr"
            );


        $response->assertOk();


        expect(
            $response->getContent()
        )->toContain(
            '<svg'
        );
    }
);

test(
    'usuario anonimo no puede obtener qr grafico',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketQrGraficoRf09(
                $this,
                $cliente
            );


        /*
        |--------------------------------------------------------------------------
        | Eliminar autenticación
        |--------------------------------------------------------------------------
        */

        Auth::forgetGuards();


        /*
        |--------------------------------------------------------------------------
        | Petición API
        |--------------------------------------------------------------------------
        |
        | Usamos getJson para que Laravel trate correctamente
        | al consumidor como cliente de API.
        |
        | De esta manera auth:sanctum responde 401
        | en lugar de intentar redirigir a route('login').
        |
        */

        $this->getJson(
            "/api/v1/tickets/{$ticket->codigo}/qr"
        )
            ->assertUnauthorized();
    }
);

