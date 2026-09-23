<?php

use App\Enums\EstadoOcupacionAsiento;
use App\Models\OcupacionAsiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Database\Seeders\TestingSeeder;
use Tests\Helpers\CrearViajeProgramable;

use App\Enums\TipoPunto;
use App\Models\Punto;
use App\Models\PuntoViaje;

uses(RefreshDatabase::class);


/*
|--------------------------------------------------------------------------
| Preparación común de las pruebas
|--------------------------------------------------------------------------
|
| RF-06 trabaja sobre un viaje PROGRAMADO.
|
| CrearViajeProgramable crea inicialmente el viaje en BORRADOR.
| Por lo tanto, antes de cada prueba:
|
| 1. Creamos el viaje.
| 2. Autenticamos temporalmente como administrador.
| 3. Lo programamos mediante el endpoint real.
| 4. Eliminamos la autenticación del administrador.
|
| De esta manera cada test decide qué usuario necesita.
|
*/

beforeEach(function () {

    /*
     * Cargamos la configuración necesaria:
     *
     * - roles
     * - permisos
     * - tipos de vehículo
     * - configuraciones necesarias
     */
    $this->seed(
        TestingSeeder::class
    );


    /*
     * Creamos el viaje en BORRADOR.
     */
    $this->viaje =
        CrearViajeProgramable::ejecutar();


    /*
     * Para pasar de BORRADOR a PROGRAMADO
     * necesitamos un administrador.
     */
    autenticarAdministrador();


    /*
     * Utilizamos el endpoint real de cambio de estado.
     *
     * Esto debe ejecutar la consolidación de:
     *
     * - puntos_viaje
     * - asientos_viaje
     */
    $this->patchJson(
        "/api/v1/viajes/{$this->viaje->id}/estado",
        [
            'estado' => 'PROGRAMADO',
        ]
    )->assertOk();


    /*
     * Refrescamos el viaje.
     */
    $this->viaje =
        $this->viaje->fresh();


    /*
     * IMPORTANTE:
     *
     * No dejamos autenticado al administrador.
     *
     * Esto permite que el test:
     *
     * "una petición sin autenticación recibe 401"
     *
     * realmente sea una petición anónima.
     */
    Auth::forgetGuards();
});


/*
|--------------------------------------------------------------------------
| 1. Autenticación
|--------------------------------------------------------------------------
*/

test(
    'una petición sin autenticación recibe 401',
    function () {

        /*
         * Obtenemos los puntos consolidados.
         */
        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        /*
         * Verificamos que el viaje realmente quedó
         * preparado para RF-06.
         */
        expect($puntos->count())
            ->toBeGreaterThanOrEqual(2);


        /*
         * No autenticamos ningún usuario.
         */
        $response = $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        );


        $response->assertUnauthorized();
    }
);


/*
|--------------------------------------------------------------------------
| 2. Bloqueo temporal
|--------------------------------------------------------------------------
*/

test(
    'permite bloquear un asiento disponible',
    function () {

        /*
         * El cliente es quien inicia la compra.
         */
        $cliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        $response = $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        );


        $response->assertSuccessful();


        /*
         * Verificamos que se creó una ocupación.
         */
        $this->assertDatabaseHas(
            'ocupaciones_asientos',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'estado' =>
                    EstadoOcupacionAsiento::BLOQUEADO->value,
            ]
        );


        /*
         * El bloqueo debe tener una fecha
         * de expiración.
         */
        $ocupacion =
            OcupacionAsiento::query()
                ->where(
                    'viaje_id',
                    $this->viaje->id
                )
                ->where(
                    'asiento_viaje_id',
                    1
                )
                ->latest()
                ->firstOrFail();


        expect(
            $ocupacion->expira_en
        )->not->toBeNull();
    }
);


/*
|--------------------------------------------------------------------------
| 3. No permitir doble bloqueo del mismo segmento
|--------------------------------------------------------------------------
*/

test(
    'no permite bloquear el mismo asiento en el mismo segmento',
    function () {

        $cliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        $datos = [
            'viaje_id' =>
                $this->viaje->id,

            'asiento_viaje_id' =>
                1,

            'punto_origen_id' =>
                $puntos->first()->punto_id,

            'punto_destino_id' =>
                $puntos->last()->punto_id,
        ];


        /*
         * Primer bloqueo.
         */
        $this->postJson(
            '/api/v1/asientos/bloquear',
            $datos
        )->assertSuccessful();


        /*
         * Segundo intento sobre el mismo asiento
         * y exactamente el mismo segmento.
         */
        $response = $this->postJson(
            '/api/v1/asientos/bloquear',
            $datos
        );


        /*
         * La regla de negocio debe impedir
         * la segunda operación.
         */
        $response->assertUnprocessable();
    }
);


/*
|--------------------------------------------------------------------------
| 4. Reutilización por segmentos
|--------------------------------------------------------------------------
*/

test(
    'permite reutilizar el asiento en un tramo que no se cruza',
    function () {

        $cliente = usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        /*
         * --------------------------------------------------------------------------
         * Obtener los dos puntos que ya tiene el viaje.
         * --------------------------------------------------------------------------
         */

        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();

        expect($puntos->count())
            ->toBe(2);


        /*
         * --------------------------------------------------------------------------
         * Creamos dos puntos adicionales únicamente para este test.
         *
         * El viaje tendrá:
         *
         * Punto 1
         *    ↓
         * Punto 2
         *    ↓
         * Punto 3
         *    ↓
         * Punto 4
         *
         * Esto nos permite probar dos segmentos independientes.
         * --------------------------------------------------------------------------
         */

        $punto3 = \App\Models\Punto::factory()->create([
            'nombre' => 'Terminal Cajamarca',
            'tipo' => \App\Enums\TipoPunto::TERMINAL,
            'activo' => true,
        ]);

        $punto4 = \App\Models\Punto::factory()->create([
            'nombre' => 'Terminal Chota',
            'tipo' => \App\Enums\TipoPunto::TERMINAL,
            'activo' => true,
        ]);


        /*
         * --------------------------------------------------------------------------
         * Agregamos los puntos al recorrido consolidado del viaje.
         * --------------------------------------------------------------------------
         */

        \App\Models\PuntoViaje::create([
            'viaje_id' => $this->viaje->id,
            'punto_id' => $punto3->id,
            'orden' => 3,
            'permite_embarque' => true,
            'permite_desembarque' => false,
            'minutos_desde_origen' => 180,
        ]);

        \App\Models\PuntoViaje::create([
            'viaje_id' => $this->viaje->id,
            'punto_id' => $punto4->id,
            'orden' => 4,
            'permite_embarque' => false,
            'permite_desembarque' => true,
            'minutos_desde_origen' => 360,
        ]);


        /*
         * --------------------------------------------------------------------------
         * Volvemos a consultar los puntos del viaje.
         * --------------------------------------------------------------------------
         */

        $puntos = $this->viaje
            ->fresh()
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        expect($puntos->count())
            ->toBe(4);


        /*
         * --------------------------------------------------------------------------
         * Primer segmento:
         *
         * Punto 1 → Punto 2
         *
         * El asiento quedará bloqueado para este tramo.
         * --------------------------------------------------------------------------
         */

        $primerOrigen =
            $puntos->get(0)->punto_id;

        $primerDestino =
            $puntos->get(1)->punto_id;


        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $primerOrigen,

                'punto_destino_id' =>
                    $primerDestino,
            ]
        )->assertSuccessful();


        /*
         * --------------------------------------------------------------------------
         * Segundo segmento:
         *
         * Punto 3 → Punto 4
         *
         * Este segmento NO se cruza con:
         *
         * Punto 1 → Punto 2
         *
         * Por lo tanto, el mismo asiento puede reutilizarse.
         * --------------------------------------------------------------------------
         */

        $segundoOrigen =
            $puntos->get(2)->punto_id;

        $segundoDestino =
            $puntos->get(3)->punto_id;


        $response = $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $segundoOrigen,

                'punto_destino_id' =>
                    $segundoDestino,
            ]
        );


        $response->assertSuccessful();


        /*
         * --------------------------------------------------------------------------
         * Deben existir dos ocupaciones BLOQUEADAS
         * para el mismo asiento.
         * --------------------------------------------------------------------------
         */

        expect(
            OcupacionAsiento::query()
                ->where(
                    'viaje_id',
                    $this->viaje->id
                )
                ->where(
                    'asiento_viaje_id',
                    1
                )
                ->where(
                    'estado',
                    EstadoOcupacionAsiento::BLOQUEADO
                )
                ->count()
        )->toBe(2);
    }
);


/*
|--------------------------------------------------------------------------
| 5. Bloqueo -> Reserva
|--------------------------------------------------------------------------
*/

test(
    'un bloqueo puede convertirse en reserva',
    function () {

        $cliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        /*
         * Creamos el bloqueo.
         */
        $bloqueoResponse =
            $this->postJson(
                '/api/v1/asientos/bloquear',
                [
                    'viaje_id' =>
                        $this->viaje->id,

                    'asiento_viaje_id' =>
                        1,

                    'punto_origen_id' =>
                        $puntos->first()->punto_id,

                    'punto_destino_id' =>
                        $puntos->last()->punto_id,
                ]
            );


        $bloqueoResponse
            ->assertSuccessful();


        /*
         * Obtenemos la ocupación creada.
         */
        $ocupacion =
            OcupacionAsiento::query()
                ->where(
                    'viaje_id',
                    $this->viaje->id
                )
                ->where(
                    'asiento_viaje_id',
                    1
                )
                ->where(
                    'estado',
                    EstadoOcupacionAsiento::BLOQUEADO
                )
                ->latest()
                ->firstOrFail();


        /*
         * Convertimos el bloqueo en reserva.
         */
        $response = $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        );


        $response->assertSuccessful();


        /*
         * Verificamos el nuevo estado.
         */
        expect(
            $ocupacion
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 6. Reserva -> Confirmación
|--------------------------------------------------------------------------
*/

test(
    'una reserva puede confirmarse',
    function () {

        $cliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        /*
         * Creamos bloqueo.
         */
        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $ocupacion =
            OcupacionAsiento::query()
                ->where(
                    'viaje_id',
                    $this->viaje->id
                )
                ->where(
                    'asiento_viaje_id',
                    1
                )
                ->where(
                    'estado',
                    EstadoOcupacionAsiento::BLOQUEADO
                )
                ->latest()
                ->firstOrFail();


        /*
         * BLOQUEADO -> RESERVADO
         */
        $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        )->assertSuccessful();


        /*
         * RESERVADO -> CONFIRMADO
         */
        $response = $this->postJson(
            '/api/v1/asientos/confirmar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        );


        $response->assertSuccessful();


        expect(
            $ocupacion
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 7. No permitir BLOQUEADO -> CONFIRMADO directamente
|--------------------------------------------------------------------------
*/

test(
    'no permite confirmar directamente un bloqueo',
    function () {

        $cliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        /*
         * Creamos solamente un bloqueo.
         */
        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $ocupacion =
            OcupacionAsiento::query()
                ->where(
                    'viaje_id',
                    $this->viaje->id
                )
                ->where(
                    'asiento_viaje_id',
                    1
                )
                ->where(
                    'estado',
                    EstadoOcupacionAsiento::BLOQUEADO
                )
                ->latest()
                ->firstOrFail();


        /*
         * Intentamos saltarnos RESERVADO.
         */
        $response = $this->postJson(
            '/api/v1/asientos/confirmar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        );


        $response->assertUnprocessable();


        /*
         * Debe continuar BLOQUEADO.
         */
        expect(
            $ocupacion
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::BLOQUEADO
        );
    }
);


/*
|--------------------------------------------------------------------------
| 8. No permitir reservar una ocupación confirmada
|--------------------------------------------------------------------------
*/

test(
    'no permite reservar una ocupacion confirmada',
    function () {

        $cliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        /*
         * BLOQUEADO
         */
        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $ocupacion =
            OcupacionAsiento::query()
                ->where(
                    'viaje_id',
                    $this->viaje->id
                )
                ->where(
                    'asiento_viaje_id',
                    1
                )
                ->where(
                    'estado',
                    EstadoOcupacionAsiento::BLOQUEADO
                )
                ->latest()
                ->firstOrFail();


        /*
         * BLOQUEADO -> RESERVADO
         */
        $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        )->assertSuccessful();


        /*
         * RESERVADO -> CONFIRMADO
         */
        $this->postJson(
            '/api/v1/asientos/confirmar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        )->assertSuccessful();


        /*
         * Intentamos reservar nuevamente
         * una ocupación ya confirmada.
         */
        $response = $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        );


        $response->assertUnprocessable();


        /*
         * El estado debe continuar CONFIRMADO.
         */
        expect(
            $ocupacion
                ->fresh()
                ->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );
    }
);


test(
    'un bloqueo expirado se libera y no puede reservarse',
    function () {

        $cliente = usuarioConRolViajes(
            'CLIENTE'
        );

        $this->actingAs(
            $cliente,
            'sanctum'
        );


        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();


        /*
         * Creamos normalmente el bloqueo.
         */
        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    1,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $ocupacion = OcupacionAsiento::query()
            ->where(
                'viaje_id',
                $this->viaje->id
            )
            ->where(
                'asiento_viaje_id',
                1
            )
            ->latest()
            ->firstOrFail();


        /*
         * Simulamos que pasaron los 10 minutos.
         */
        $ocupacion->update([
            'expira_en' =>
                now()->subMinute(),
        ]);


        /*
         * Intentamos reservar un bloqueo vencido.
         */
        $response = $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        );


        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'mensaje',
                'El bloqueo del asiento expiró.'
            );


        /*
         * Lo más importante de esta prueba:
         *
         * comprobar que LIBERADO quedó realmente
         * persistido y no fue revertido por rollback.
         */
        expect(
            $ocupacion->fresh()->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );


        expect(
            $ocupacion->fresh()->expira_en
        )->toBeNull();
    }
);

test(
    'el bloqueo queda asociado al cliente autenticado',
    function () {

        $cliente = usuarioConRolViajes(
            'CLIENTE'
        );

        $this->actingAs(
            $cliente,
            'sanctum'
        );

        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();

        $asientoViajeId = $this->viaje
            ->asientosViaje()
            ->value('id');


        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    $asientoViajeId,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $this->assertDatabaseHas(
            'ocupaciones_asientos',
            [
                'usuario_id' =>
                    $cliente->id,

                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    $asientoViajeId,
            ]
        );
    }
);



test(
    'otro cliente no puede reservar un bloqueo ajeno',
    function () {

        $clientePropietario =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $clientePropietario,
            'sanctum'
        );

        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();

        $asientoViajeId = $this->viaje
            ->asientosViaje()
            ->value('id');


        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    $asientoViajeId,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $ocupacion = OcupacionAsiento::query()
            ->where(
                'usuario_id',
                $clientePropietario->id
            )
            ->latest()
            ->firstOrFail();


        $otroCliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $otroCliente,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        )->assertForbidden();


        expect(
            $ocupacion->fresh()->estado
        )->toBe(
            EstadoOcupacionAsiento::BLOQUEADO
        );
    }
);



test(
    'otro cliente no puede confirmar una reserva ajena',
    function () {

        $clientePropietario =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $clientePropietario,
            'sanctum'
        );

        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();

        $asientoViajeId = $this->viaje
            ->asientosViaje()
            ->value('id');


        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    $asientoViajeId,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        $ocupacion = OcupacionAsiento::query()
            ->where(
                'usuario_id',
                $clientePropietario->id
            )
            ->latest()
            ->firstOrFail();


        $this->postJson(
            '/api/v1/asientos/reservar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        )->assertSuccessful();


        $otroCliente =
            usuarioConRolViajes('CLIENTE');

        $this->actingAs(
            $otroCliente,
            'sanctum'
        );


        $this->postJson(
            '/api/v1/asientos/confirmar',
            [
                'ocupacion_id' =>
                    $ocupacion->id,
            ]
        )->assertForbidden();


        expect(
            $ocupacion->fresh()->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );
    }
);


test(
    'un asiento liberado puede bloquearse nuevamente en el mismo segmento',
    function () {

        $cliente = usuarioConRolViajes(
            'CLIENTE'
        );

        $this->actingAs(
            $cliente,
            'sanctum'
        );

        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();

        $asientoViajeId = $this->viaje
            ->asientosViaje()
            ->value('id');


        $datos = [
            'viaje_id' =>
                $this->viaje->id,

            'asiento_viaje_id' =>
                $asientoViajeId,

            'punto_origen_id' =>
                $puntos->first()->punto_id,

            'punto_destino_id' =>
                $puntos->last()->punto_id,
        ];


        $this->postJson(
            '/api/v1/asientos/bloquear',
            $datos
        )->assertSuccessful();


        $ocupacion = OcupacionAsiento::query()
            ->latest()
            ->firstOrFail();


        $ocupacion->update([
            'estado' =>
                EstadoOcupacionAsiento::LIBERADO,

            'expira_en' =>
                null,
        ]);


        /*
         * El mismo asiento y exactamente el
         * mismo segmento deben poder utilizarse otra vez.
         */
        $this->postJson(
            '/api/v1/asientos/bloquear',
            $datos
        )->assertSuccessful();


        expect(
            OcupacionAsiento::query()
                ->where(
                    'asiento_viaje_id',
                    $asientoViajeId
                )
                ->where(
                    'punto_origen_id',
                    $puntos->first()->punto_id
                )
                ->where(
                    'punto_destino_id',
                    $puntos->last()->punto_id
                )
                ->count()
        )->toBe(2);
    }
);



test(
    'un bloqueo expirado aparece disponible sin esperar al scheduler',
    function () {

        $cliente = usuarioConRolViajes(
            'CLIENTE'
        );

        $this->actingAs(
            $cliente,
            'sanctum'
        );

        $puntos = $this->viaje
            ->puntosViaje()
            ->orderBy('orden')
            ->get();

        $asientoViajeId = $this->viaje
            ->asientosViaje()
            ->value('id');


        $this->postJson(
            '/api/v1/asientos/bloquear',
            [
                'viaje_id' =>
                    $this->viaje->id,

                'asiento_viaje_id' =>
                    $asientoViajeId,

                'punto_origen_id' =>
                    $puntos->first()->punto_id,

                'punto_destino_id' =>
                    $puntos->last()->punto_id,
            ]
        )->assertSuccessful();


        /*
         * Expiramos el bloqueo,
         * pero NO ejecutamos el scheduler.
         */
        OcupacionAsiento::query()
            ->latest()
            ->firstOrFail()
            ->update([
                'expira_en' =>
                    now()->subMinute(),
            ]);


        $response = $this->getJson(
            "/api/v1/busqueda/viajes/{$this->viaje->id}/asientos"
            .'?punto_origen_id='
            .$puntos->first()->punto_id
            .'&punto_destino_id='
            .$puntos->last()->punto_id
        );


        $response->assertOk();


        $asiento = collect(
            $response->json('data')
        )->firstWhere(
            'id',
            $asientoViajeId
        );


        expect($asiento)
            ->not
            ->toBeNull();


        expect(
            $asiento['disponible']
        )->toBeTrue();
    }
);
