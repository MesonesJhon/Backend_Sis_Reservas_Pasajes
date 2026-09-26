<?php

use App\Domain\Tickets\GeneradorQrTicket;
use App\Enums\EstadoTicket;
use App\Models\OcupacionAsiento;
use App\Models\Reserva;
use App\Models\Ticket;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoReserva;
use App\Enums\FuncionPersonalViaje;
use App\Models\PersonalViaje;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Helpers\CrearViajeProgramable;
use Illuminate\Support\Str;

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


    $this->asientoA1 =
        $this
            ->viaje
            ->asientosViaje()
            ->where(
                'codigo',
                'A1'
            )
            ->firstOrFail();
});


/**
 * Crea una reserva confirmada
 * y devuelve su ticket vigente.
 */
function crearTicketParaValidacionRf09(
    $test
): array {

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
    | Bloqueo
    |--------------------------------------------------------------------------
    */

    $test->postJson(
        '/api/v1/asientos/bloquear',
        [
            'viaje_id' =>
                $test->viaje->id,

            'asiento_viaje_id' =>
                $test->asientoA1->id,

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
                $test->asientoA1->id
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
                            'Validacion',
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
    | Confirmación administrativa
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


    /*
    |--------------------------------------------------------------------------
    | Ticket
    |--------------------------------------------------------------------------
    */

    $ticket =
        Ticket::query()

            ->where(
                'reserva_id',
                $reserva->id
            )

            ->firstOrFail();


    /*
    |--------------------------------------------------------------------------
    | Ticket
    |--------------------------------------------------------------------------
    */

    $ticket =
        Ticket::query()

            ->where(
                'reserva_id',
                $reserva->id
            )

            ->firstOrFail();


    $token =
        app(
            GeneradorQrTicket::class
        )
            ->generar(
                $ticket
            );


    return [
        $cliente,
        $operador,
        $reserva,
        $ocupacion->fresh(),
        $ticket,
        $token,
    ];
}

test(
    'operador puede validar un ticket vigente',
    function () {

        [
            ,
            $operador,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )

            ->assertOk()

            ->assertJsonPath(
                'data.codigo',
                $ticket->codigo
            )

            ->assertJsonPath(
                'data.estado',
                EstadoTicket::UTILIZADO->value
            );


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::UTILIZADO
        );


        expect(
            $ticket->validado_en
        )->not->toBeNull();


        expect(
            $ticket->validado_por_usuario_id
        )->toBe(
            $operador->id
        );
    }
);


test(
    'ticket no puede utilizarse dos veces',
    function () {

        [
            ,
            $operador,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        /*
         * Primer escaneo.
         */
        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertOk();


        /*
         * Segundo escaneo.
         */
        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnprocessable();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::UTILIZADO
        );
    }
);


test(
    'endpoint rechaza un token qr manipulado',
    function () {

        [
            ,
            $operador,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        /*
        |--------------------------------------------------------------------------
        | Separar token
        |--------------------------------------------------------------------------
        |
        | Formato:
        |
        | v1:TKT-<ULID>:<firma>
        |
        */

        [
            $version,
            $codigo,
            $firma,
        ] =
            explode(
                ':',
                $token
            );


        /*
        |--------------------------------------------------------------------------
        | Manipular firma de forma garantizada
        |--------------------------------------------------------------------------
        |
        | Nunca utilizamos simplemente:
        |
        | substr($firma, 0, -1).'0'
        |
        | porque si la firma ya terminaba en 0
        | el token quedaba exactamente igual.
        |
        */

        $primerCaracterManipulado =
            $firma[0] === 'a'
                ? 'b'
                : 'a';


        $firmaManipulada =
            $primerCaracterManipulado
            .substr(
                $firma,
                1
            );


        $tokenManipulado =
            $version
            .':'
            .$codigo
            .':'
            .$firmaManipulada;


        /*
         * Defensa del propio test:
         *
         * garantiza que realmente hemos alterado
         * el token antes de llamar a la API.
         */
        expect(
            $tokenManipulado
        )->not->toBe(
            $token
        );


        /*
        |--------------------------------------------------------------------------
        | Intentar validar
        |--------------------------------------------------------------------------
        */

        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $tokenManipulado,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnprocessable();


        /*
        |--------------------------------------------------------------------------
        | El intento inválido no debe afectar el Ticket
        |--------------------------------------------------------------------------
        */

        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticket->validado_en
        )->toBeNull();


        expect(
            $ticket->validado_por_usuario_id
        )->toBeNull();
    }
);

test(
    'conductor asignado al viaje puede validar ticket',
    function () {

        [
            ,
            ,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Recuperar conductor realmente asignado al viaje
        |--------------------------------------------------------------------------
        */

        $asignacion =
            PersonalViaje::query()

                ->where(
                    'viaje_id',
                    $this->viaje->id
                )

                ->where(
                    'funcion',
                    FuncionPersonalViaje::CONDUCTOR
                )

                ->with(
                    'usuario'
                )

                ->firstOrFail();


        $conductor =
            $asignacion->usuario;


        $this->actingAs(
            $conductor,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )

            ->assertOk()

            ->assertJsonPath(
                'data.codigo',
                $ticket->codigo
            )

            ->assertJsonPath(
                'data.estado',
                EstadoTicket::UTILIZADO->value
            );


        $ticket->refresh();


        expect(
            $ticket->validado_por_usuario_id
        )->toBe(
            $conductor->id
        );
    }
);

test(
    'conductor no asignado al viaje no puede validar ticket',
    function () {

        [
            ,
            ,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        /*
         * Creamos otro conductor que NO pertenece
         * al viaje actual.
         */
        $otroConductor =
            usuarioConRolViajes(
                'CONDUCTOR'
            );


        $this->actingAs(
            $otroConductor,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertForbidden();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticket->validado_en
        )->toBeNull();
    }
);

test(
    'cliente no puede validar tickets',
    function () {

        [
            $cliente,
            ,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertForbidden();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );
    }
);


test(
    'ticket anulado no puede validarse',
    function () {

        [
            ,
            $operador,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Simular ticket previamente anulado
        |--------------------------------------------------------------------------
        */

        $ticket->estado =
            EstadoTicket::ANULADO;

        $ticket->anulado_en =
            now();

        $ticket->motivo_anulacion =
            'Anulación de prueba';

        $ticket->save();


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnprocessable();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::ANULADO
        );


        expect(
            $ticket->validado_en
        )->toBeNull();
    }
);


test(
    'ticket no puede validarse si la reserva no esta confirmada',
    function () {

        [
            ,
            $operador,
            $reserva,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Estado inconsistente simulado
        |--------------------------------------------------------------------------
        |
        | Normalmente:
        |
        | CONFIRMADA -> PENDIENTE_PAGO
        |
        | no es una transición permitida.
        |
        | La forzamos directamente para comprobar que la validación
        | del ticket no confía solamente en que los datos sean correctos.
        |
        */

        Reserva::query()

            ->whereKey(
                $reserva->id
            )

            ->update([
                'estado' =>
                    EstadoReserva::PENDIENTE_PAGO->value,
            ]);


        $reserva->refresh();


        /*
         * Verificamos primero que el escenario de prueba
         * haya quedado realmente preparado.
         */
        expect(
            $reserva->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnprocessable();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticket->validado_en
        )->toBeNull();
    }
);


test(
    'ticket no puede validarse si la ocupacion no esta confirmada',
    function () {

        [
            ,
            $operador,
            ,
            $ocupacion,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Simular pérdida de confirmación del asiento
        |--------------------------------------------------------------------------
        */

        $ocupacion->estado =
            EstadoOcupacionAsiento::RESERVADO;

        $ocupacion->save();


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnprocessable();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticket->validado_en
        )->toBeNull();
    }
);


test(
    'ticket no puede validarse usando otro viaje',
    function () {

        [
            ,
            $operador,
            ,
            ,
            $ticket,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        /*
        |--------------------------------------------------------------------------
        | Crear otro viaje real
        |--------------------------------------------------------------------------
        */

        $otroViaje =
            CrearViajeProgramable::ejecutar();


        expect(
            $otroViaje->id
        )->not->toBe(
            $this->viaje->id
        );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $otroViaje->id,
            ]
        )
            ->assertUnprocessable();


        $ticket->refresh();


        expect(
            $ticket->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );
    }
);


test(
    'token valido de ticket inexistente es rechazado',
    function () {

        $operador =
            usuarioConRolViajes(
                'OPERADOR'
            );


        /*
        |--------------------------------------------------------------------------
        | Ticket solamente en memoria
        |--------------------------------------------------------------------------
        |
        | Tiene código válido pero NO se persiste.
        |
        */

        $ticketInexistente =
            new Ticket([
                'codigo' =>
                    'TKT-'.\Illuminate\Support\Str::ulid(),
            ]);


        $token =
            app(
                GeneradorQrTicket::class
            )
                ->generar(
                    $ticketInexistente
                );


        $this->actingAs(
            $operador,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnprocessable();
    }
);


test(
    'usuario anonimo no puede validar tickets',
    function () {

        [
            ,
            ,
            ,
            ,
            ,
            $token,
        ] =
            crearTicketParaValidacionRf09(
                $this
            );


        Auth::forgetGuards();


        $this->postJson(
            '/api/v1/tickets/validar',
            [
                'token' =>
                    $token,

                'viaje_id' =>
                    $this->viaje->id,
            ]
        )
            ->assertUnauthorized();
    }
);
