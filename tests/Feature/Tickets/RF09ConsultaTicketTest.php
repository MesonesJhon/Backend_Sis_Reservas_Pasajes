<?php

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
     * Programar viaje.
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


    /*
     * Segmento completo.
     */
    $this->puntos =
        $this
            ->viaje
            ->puntosViaje()
            ->orderBy(
                'orden'
            )
            ->get();


    /*
     * Asiento A1.
     */
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


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

/**
 * Crea:
 *
 * CLIENTE
 *   ↓
 * bloqueo
 *   ↓
 * reserva
 *   ↓
 * OPERADOR confirma
 *   ↓
 * Ticket VIGENTE
 */
function crearTicketConsultableRf09(
    $test,
    $cliente
): array {

    /*
    |--------------------------------------------------------------------------
    | Cliente
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Recuperar ocupación
    |--------------------------------------------------------------------------
    */

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
                            'Consulta',
                    ],
                ],
            ]
        );


    $response
        ->assertCreated();


    $reserva =
        Reserva::query()
            ->findOrFail(
                $response->json(
                    'data.id'
                )
            );


    /*
    |--------------------------------------------------------------------------
    | Operador confirma
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


    /*
    |--------------------------------------------------------------------------
    | Ticket emitido automáticamente
    |--------------------------------------------------------------------------
    */

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


/*
|--------------------------------------------------------------------------
| Cliente consulta su propio ticket
|--------------------------------------------------------------------------
*/

test(
    'cliente puede consultar un ticket de su propia reserva',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            $reserva,
            $ticket,
        ] =
            crearTicketConsultableRf09(
                $this,
                $cliente
            );


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $this->getJson(
            "/api/v1/tickets/{$ticket->codigo}"
        )

            ->assertOk()

            ->assertJsonPath(
                'data.codigo',
                $ticket->codigo
            )

            ->assertJsonPath(
                'data.reserva.codigo',
                $reserva->codigo
            )

            ->assertJsonPath(
                'data.viaje.codigo',
                $this->viaje->codigo
            )

            ->assertJsonPath(
                'data.asiento.codigo',
                'A1'
            )

            ->assertJsonPath(
                'data.pasajero.nombres',
                'Pasajero'
            )

            ->assertJsonPath(
                'data.pasajero.apellidos',
                'Consulta'
            )

            /*
             * Esta reserva fue confirmada manualmente,
             * por lo tanto no posee pago electrónico.
             */
            ->assertJsonPath(
                'data.pago',
                null
            )

            /*
             * No exponemos IDs internos.
             */
            ->assertJsonMissingPath(
                'data.id'
            )

            ->assertJsonMissingPath(
                'data.pasajero_reserva_id'
            )

            ->assertJsonMissingPath(
                'data.reserva_id'
            );
    }
);


/*
|--------------------------------------------------------------------------
| Cliente no puede consultar ticket ajeno
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede consultar ticket de otra reserva',
    function () {

        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketConsultableRf09(
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


        $this->getJson(
            "/api/v1/tickets/{$ticket->codigo}"
        )
            ->assertForbidden();
    }
);


/*
|--------------------------------------------------------------------------
| Operador
|--------------------------------------------------------------------------
*/

test(
    'operador puede consultar un ticket',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketConsultableRf09(
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


        $this->getJson(
            "/api/v1/tickets/{$ticket->codigo}"
        )

            ->assertOk()

            ->assertJsonPath(
                'data.codigo',
                $ticket->codigo
            );
    }
);


/*
|--------------------------------------------------------------------------
| Listado por reserva
|--------------------------------------------------------------------------
*/

test(
    'cliente puede listar tickets de su propia reserva',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            $reserva,
            $ticket,
        ] =
            crearTicketConsultableRf09(
                $this,
                $cliente
            );


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $this->getJson(
            "/api/v1/reservas/{$reserva->id}/tickets"
        )

            ->assertOk()

            ->assertJsonCount(
                1,
                'data'
            )

            ->assertJsonPath(
                'data.0.codigo',
                $ticket->codigo
            );
    }
);


/*
|--------------------------------------------------------------------------
| Listado ajeno
|--------------------------------------------------------------------------
*/

test(
    'cliente no puede listar tickets de una reserva ajena',
    function () {

        $propietario =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            $reserva,
        ] =
            crearTicketConsultableRf09(
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


        $this->getJson(
            "/api/v1/reservas/{$reserva->id}/tickets"
        )
            ->assertForbidden();
    }
);


/*
|--------------------------------------------------------------------------
| Anónimo
|--------------------------------------------------------------------------
*/

test(
    'usuario anonimo no puede consultar tickets',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketConsultableRf09(
                $this,
                $cliente
            );


        Auth::forgetGuards();


        $this->getJson(
            "/api/v1/tickets/{$ticket->codigo}"
        )
            ->assertUnauthorized();
    }
);


/*
|--------------------------------------------------------------------------
| Conductor
|--------------------------------------------------------------------------
*/

test(
    'conductor no puede consultar detalle porque solo posee permiso de validacion',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketConsultableRf09(
                $this,
                $cliente
            );


        $conductor =
            usuarioConRolViajes(
                'CONDUCTOR'
            );


        $this->actingAs(
            $conductor,
            'sanctum'
        );


        $this->getJson(
            "/api/v1/tickets/{$ticket->codigo}"
        )
            ->assertForbidden();
    }
);


/*
|--------------------------------------------------------------------------
| Identificador público
|--------------------------------------------------------------------------
*/

test(
    'ticket se consulta por codigo publico y no por id interno',
    function () {

        $cliente =
            usuarioConRolViajes(
                'CLIENTE'
            );


        [
            ,
            $ticket,
        ] =
            crearTicketConsultableRf09(
                $this,
                $cliente
            );


        $this->actingAs(
            $cliente,
            'sanctum'
        );


        /*
         * La ruta busca por:
         *
         * codigo
         *
         * no por ID.
         */
        $this->getJson(
            "/api/v1/tickets/{$ticket->id}"
        )
            ->assertNotFound();
    }
);
