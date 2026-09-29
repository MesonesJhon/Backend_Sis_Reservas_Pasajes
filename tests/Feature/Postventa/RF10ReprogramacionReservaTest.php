<?php

use App\Enums\EstadoReprogramacion;
use App\Enums\EstadoReserva;
use App\Models\ReprogramacionReserva;
use App\Models\Reserva;
use App\Models\Usuario;
use Database\Seeders\TestingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Helpers\CrearViajeProgramable;
use App\Actions\Postventa\CotizarReprogramacionReserva;
use App\Actions\Viajes\CambiarEstadoViaje;
use App\Enums\EstadoOcupacionAsiento;
use App\Enums\EstadoTicket;
use App\Enums\EstadoViaje;
use App\Exceptions\OperacionReprogramacionInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Models\OcupacionAsiento;
use App\Models\PasajeroReserva;
use App\Models\Rol;
use App\Models\TarifaViaje;
use App\Models\Ticket;
use App\Models\Viaje;
use App\Actions\Asientos\BloquearAsiento;
use App\Actions\Postventa\PrepararReprogramacionReserva;
use App\Actions\Postventa\ConsolidarReprogramacionReserva;
use App\Actions\Reservas\CancelarReserva;
use App\Enums\OrigenOperacionPostventa;
use App\Enums\TipoOperacionPostventa;
use App\Exceptions\OperacionReservaInvalidaException;
use App\Models\OperacionPostventa;

uses(RefreshDatabase::class);


beforeEach(function () {

    $this->seed(
        TestingSeeder::class
    );
});


function crearReservasParaReprogramacionRf10(
    $test
): array {

    $usuario =
        Usuario::factory()
            ->create([
                'activo' =>
                    true,
            ]);


    /*
    |--------------------------------------------------------------------------
    | Viaje original
    |--------------------------------------------------------------------------
    */

    $viajeOrigen =
        CrearViajeProgramable::ejecutar();


    /*
    |--------------------------------------------------------------------------
    | Nuevo viaje
    |--------------------------------------------------------------------------
    */

    $viajeDestino =
        CrearViajeProgramable::ejecutar();


    /*
    |--------------------------------------------------------------------------
    | Reserva original
    |--------------------------------------------------------------------------
    */

    $reservaOrigen =
        Reserva::query()
            ->create([

                'codigo' =>
                    'RSV-'.Str::ulid(),

                'viaje_id' =>
                    $viajeOrigen->id,

                'cliente_usuario_id' =>
                    $usuario->id,

                'creado_por_usuario_id' =>
                    $usuario->id,

                'estado' =>
                    EstadoReserva::CONFIRMADA,

                'telefono_contacto' =>
                    '999888777',

                'total' =>
                    '50.00',

                'confirmada_en' =>
                    now(),
            ]);


    /*
    |--------------------------------------------------------------------------
    | Reserva destino simulada
    |--------------------------------------------------------------------------
    |
    | Por ahora solamente sirve para probar
    | la estructura.
    |
    | En 10.2-C será creada por la Action real.
    |
    */

    $reservaDestino =
        Reserva::query()
            ->create([

                'codigo' =>
                    'RSV-'.Str::ulid(),

                'viaje_id' =>
                    $viajeDestino->id,

                'cliente_usuario_id' =>
                    $usuario->id,

                'creado_por_usuario_id' =>
                    $usuario->id,

                'estado' =>
                    EstadoReserva::PENDIENTE_PAGO,

                'telefono_contacto' =>
                    '999888777',

                'total' =>
                    '70.00',
            ]);


    return [
        $usuario,
        $viajeOrigen,
        $viajeDestino,
        $reservaOrigen,
        $reservaDestino,
    ];
}
function prepararReprogramacionSinDiferenciaRf10(
    $test
): array {

    [
        $cliente,
        $viajeOrigen,
        $viajeDestino,
        $reservaOrigen,
        $ocupacionOrigen,
        $pasajeroOrigen,
        $ticketOrigen,
    ] =
        crearContextoCotizacionReprogramacionRf10(
            $test,
            '50.00'
        );


    $asientoDestino =
        $viajeDestino
            ->asientosViaje()
            ->where(
                'codigo',
                'A1'
            )
            ->firstOrFail();


    $reprogramacion =
        app(
            PrepararReprogramacionReserva::class
        )
            ->ejecutar(
                usuario:
                    $cliente,

                reservaOrigen:
                    $reservaOrigen,

                viajeDestino:
                    $viajeDestino,

                asignaciones: [
                    [
                        'pasajero_reserva_id' =>
                            $pasajeroOrigen->id,

                        'asiento_viaje_id' =>
                            $asientoDestino->id,
                    ],
                ],

                motivo:
                    'Cambio de fecha'
            );


    return [

        $cliente,

        $viajeOrigen,

        $viajeDestino,

        $reservaOrigen,

        $ocupacionOrigen,

        $pasajeroOrigen,

        $ticketOrigen,

        $reprogramacion,
    ];
}
function crearContextoCotizacionReprogramacionRf10(
    $test,
    string $precioDestino = '70.00'
): array {

    /*
    |--------------------------------------------------------------------------
    | Viaje original
    |--------------------------------------------------------------------------
    */

    $viajeOrigen =
        CrearViajeProgramable::ejecutar(
            salida:
                now()
                    ->addDays(5)
                    ->setTime(
                        8,
                        0
                    )
                    ->toDateTimeString()
        );


    $viajeOrigen =
        app(
            CambiarEstadoViaje::class
        )
            ->ejecutar(
                $viajeOrigen,
                EstadoViaje::PROGRAMADO
            );


    $puntos =
        $viajeOrigen
            ->puntosViaje()
            ->orderBy(
                'orden'
            )
            ->get();


    $puntoOrigen =
        $puntos->first();


    $puntoDestino =
        $puntos->last();


    /*
    |--------------------------------------------------------------------------
    | Viaje destino
    |--------------------------------------------------------------------------
    |
    | CrearViajeProgramable crea su propia ruta.
    |
    | Para este escenario cambiamos la ruta ANTES
    | de programarlo para que comparta exactamente
    | los mismos puntos físicos que el viaje original.
    |
    */

    $viajeDestino =
        CrearViajeProgramable::ejecutar(
            salida:
                now()
                    ->addDays(7)
                    ->setTime(
                        8,
                        0
                    )
                    ->toDateTimeString()
        );


    $viajeDestino->update([
        'ruta_id' =>
            $viajeOrigen->ruta_id,
    ]);


    /*
     * Eliminamos la tarifa que creó
     * el helper para su ruta original.
     */
    $viajeDestino
        ->tarifas()
        ->delete();


    /*
     * Configuramos la tarifa exacta
     * del segmento reprogramado.
     */
    TarifaViaje::query()
        ->create([

            'viaje_id' =>
                $viajeDestino->id,

            'punto_origen_id' =>
                $puntoOrigen->punto_id,

            'punto_destino_id' =>
                $puntoDestino->punto_id,

            'precio' =>
                $precioDestino,

            'activo' =>
                true,
        ]);


    $viajeDestino =
        app(
            CambiarEstadoViaje::class
        )
            ->ejecutar(
                $viajeDestino,
                EstadoViaje::PROGRAMADO
            );


    /*
    |--------------------------------------------------------------------------
    | Cliente
    |--------------------------------------------------------------------------
    */

    $cliente =
        Usuario::factory()
            ->create([
                'activo' =>
                    true,
            ]);


    $rolCliente =
        Rol::query()
            ->where(
                'nombre',
                'CLIENTE'
            )
            ->firstOrFail();


    $cliente
        ->roles()
        ->attach(
            $rolCliente->id
        );


    /*
    |--------------------------------------------------------------------------
    | Reserva original confirmada
    |--------------------------------------------------------------------------
    */

    $reserva =
        Reserva::query()
            ->create([

                'codigo' =>
                    'RSV-'
                    .Str::upper(
                        (string) Str::ulid()
                    ),

                'viaje_id' =>
                    $viajeOrigen->id,

                'cliente_usuario_id' =>
                    $cliente->id,

                'creado_por_usuario_id' =>
                    $cliente->id,

                'estado' =>
                    EstadoReserva::CONFIRMADA,

                'telefono_contacto' =>
                    '999888777',

                'total' =>
                    '50.00',

                'confirmada_en' =>
                    now(),
            ]);


    /*
    |--------------------------------------------------------------------------
    | Ocupación original
    |--------------------------------------------------------------------------
    */

    $asientoOrigen =
        $viajeOrigen
            ->asientosViaje()
            ->where(
                'codigo',
                'A1'
            )
            ->firstOrFail();


    $ocupacion =
        OcupacionAsiento::query()
            ->create([

                'usuario_id' =>
                    $cliente->id,

                'reserva_id' =>
                    $reserva->id,

                'viaje_id' =>
                    $viajeOrigen->id,

                'asiento_viaje_id' =>
                    $asientoOrigen->id,

                'punto_origen_id' =>
                    $puntoOrigen->punto_id,

                'punto_destino_id' =>
                    $puntoDestino->punto_id,

                'estado' =>
                    EstadoOcupacionAsiento::CONFIRMADO,

                'expira_en' =>
                    null,
            ]);


    /*
    |--------------------------------------------------------------------------
    | Pasajero
    |--------------------------------------------------------------------------
    */

    $pasajero =
        PasajeroReserva::query()
            ->create([

                'reserva_id' =>
                    $reserva->id,

                'ocupacion_asiento_id' =>
                    $ocupacion->id,

                'tipo_documento' =>
                    'DNI',

                'numero_documento' =>
                    '70000001',

                'nombres' =>
                    'Pasajero',

                'apellidos' =>
                    'Reprogramacion',

                'precio' =>
                    '50.00',
            ]);


    /*
    |--------------------------------------------------------------------------
    | Ticket vigente
    |--------------------------------------------------------------------------
    */

    $ticket =
        Ticket::query()
            ->create([

                'codigo' =>
                    'TKT-'
                    .Str::upper(
                        (string) Str::ulid()
                    ),

                'reserva_id' =>
                    $reserva->id,

                'pasajero_reserva_id' =>
                    $pasajero->id,

                'estado' =>
                    EstadoTicket::VIGENTE,

                'emitido_en' =>
                    now(),
            ]);


    return [

        $cliente,

        $viajeOrigen,

        $viajeDestino,

        $reserva,

        $ocupacion,

        $pasajero,

        $ticket,
    ];
}


test(
    'puede registrar la relacion entre reserva origen y destino',
    function () {

        [
            $usuario,
            ,
            ,
            $reservaOrigen,
            $reservaDestino,
        ] =
            crearReservasParaReprogramacionRf10(
                $this
            );


        $reprogramacion =
            ReprogramacionReserva::query()
                ->create([

                    'codigo' =>
                        'RPG-'.Str::ulid(),

                    'reserva_origen_id' =>
                        $reservaOrigen->id,

                    'reserva_destino_id' =>
                        $reservaDestino->id,

                    'estado' =>
                        EstadoReprogramacion::SOLICITADA,

                    'solicitada_por_usuario_id' =>
                        $usuario->id,

                    'motivo' =>
                        'Cambio de fecha',

                    'solicitada_en' =>
                        now(),
                ]);


        expect(
            $reprogramacion->estado
        )->toBe(
            EstadoReprogramacion::SOLICITADA
        );


        expect(
            $reprogramacion
                ->reservaOrigen
                ->id
        )->toBe(
            $reservaOrigen->id
        );


        expect(
            $reprogramacion
                ->reservaDestino
                ->id
        )->toBe(
            $reservaDestino->id
        );


        expect(
            $reprogramacion
                ->solicitadaPor
                ->id
        )->toBe(
            $usuario->id
        );


        expect(
            $reservaOrigen
                ->reprogramacionesComoOrigen()
                ->count()
        )->toBe(1);


        expect(
            $reservaDestino
                ->reprogramacionComoDestino
                ->id
        )->toBe(
            $reprogramacion->id
        );
    }
);

test(
    'estado de reprogramacion respeta sus transiciones',
    function () {

        expect(
            EstadoReprogramacion::SOLICITADA
                ->puedeCambiarA(
                    EstadoReprogramacion::PENDIENTE_AJUSTE
                )
        )->toBeTrue();


        expect(
            EstadoReprogramacion::SOLICITADA
                ->puedeCambiarA(
                    EstadoReprogramacion::COMPLETADA
                )
        )->toBeTrue();


        expect(
            EstadoReprogramacion::PENDIENTE_AJUSTE
                ->puedeCambiarA(
                    EstadoReprogramacion::COMPLETADA
                )
        )->toBeTrue();


        expect(
            EstadoReprogramacion::COMPLETADA
                ->puedeCambiarA(
                    EstadoReprogramacion::SOLICITADA
                )
        )->toBeFalse();


        expect(
            EstadoReprogramacion::CANCELADA
                ->puedeCambiarA(
                    EstadoReprogramacion::COMPLETADA
                )
        )->toBeFalse();
    }
);

test(
    'una reserva destino no puede pertenecer a dos reprogramaciones',
    function () {

        [
            $usuario,
            ,
            ,
            $reservaOrigen,
            $reservaDestino,
        ] =
            crearReservasParaReprogramacionRf10(
                $this
            );


        ReprogramacionReserva::query()
            ->create([

                'codigo' =>
                    'RPG-'.Str::ulid(),

                'reserva_origen_id' =>
                    $reservaOrigen->id,

                'reserva_destino_id' =>
                    $reservaDestino->id,

                'estado' =>
                    EstadoReprogramacion::SOLICITADA,

                'solicitada_por_usuario_id' =>
                    $usuario->id,

                'solicitada_en' =>
                    now(),
            ]);


        /*
         * Segundo origen distinto.
         */
        $otroViaje =
            CrearViajeProgramable::ejecutar();


        $otraReserva =
            Reserva::query()
                ->create([

                    'codigo' =>
                        'RSV-'.Str::ulid(),

                    'viaje_id' =>
                        $otroViaje->id,

                    'cliente_usuario_id' =>
                        $usuario->id,

                    'creado_por_usuario_id' =>
                        $usuario->id,

                    'estado' =>
                        EstadoReserva::CONFIRMADA,

                    'telefono_contacto' =>
                        '999888777',

                    'total' =>
                        '60.00',

                    'confirmada_en' =>
                        now(),
                ]);


        expect(
            fn () =>
                ReprogramacionReserva::query()
                    ->create([

                        'codigo' =>
                            'RPG-'.Str::ulid(),

                        'reserva_origen_id' =>
                            $otraReserva->id,

                        /*
                         * Intentamos reutilizar
                         * exactamente el mismo destino.
                         */
                        'reserva_destino_id' =>
                            $reservaDestino->id,

                        'estado' =>
                            EstadoReprogramacion::SOLICITADA,

                        'solicitada_por_usuario_id' =>
                            $usuario->id,

                        'solicitada_en' =>
                            now(),
                    ])
        )->toThrow(
            QueryException::class
        );
    }
);


test(
    'cotiza una reprogramacion usando las tarifas del nuevo viaje',
    function () {

        [
            $cliente,
            $viajeOrigen,
            $viajeDestino,
            $reserva,
            ,
            $pasajero,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this,
                '70.00'
            );


        $cotizacion =
            app(
                CotizarReprogramacionReserva::class
            )
                ->ejecutar(
                    $cliente,
                    $reserva,
                    $viajeDestino
                );


        expect(
            $cotizacion[
                'reserva_origen_id'
            ]
        )->toBe(
            $reserva->id
        );


        expect(
            $cotizacion[
                'viaje_origen_id'
            ]
        )->toBe(
            $viajeOrigen->id
        );


        expect(
            $cotizacion[
                'viaje_destino_id'
            ]
        )->toBe(
            $viajeDestino->id
        );


        expect(
            $cotizacion[
                'total_original'
            ]
        )->toBe(
            '50.00'
        );


        expect(
            $cotizacion[
                'total_nuevo'
            ]
        )->toBe(
            '70.00'
        );


        expect(
            $cotizacion[
                'diferencia'
            ]
        )->toBe(
            '20.00'
        );


        expect(
            $cotizacion[
                'direccion_diferencia'
            ]
        )->toBe(
            'A_PAGAR'
        );


        expect(
            $cotizacion[
                'pasajeros'
            ]
        )->toHaveCount(1);


        expect(
            $cotizacion[
                'pasajeros'
            ][0][
                'pasajero_reserva_id'
            ]
        )->toBe(
            $pasajero->id
        );


        /*
         * Cotizar NO crea todavía
         * una reprogramación.
         */
        expect(
            ReprogramacionReserva::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'cotizacion identifica cuando el nuevo viaje tiene menor tarifa',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reserva,
            ,
            ,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this,
                '40.00'
            );


        $cotizacion =
            app(
                CotizarReprogramacionReserva::class
            )
                ->ejecutar(
                    $cliente,
                    $reserva,
                    $viajeDestino
                );


        expect(
            $cotizacion[
                'total_original'
            ]
        )->toBe(
            '50.00'
        );


        expect(
            $cotizacion[
                'total_nuevo'
            ]
        )->toBe(
            '40.00'
        );


        expect(
            $cotizacion[
                'diferencia'
            ]
        )->toBe(
            '-10.00'
        );


        expect(
            $cotizacion[
                'direccion_diferencia'
            ]
        )->toBe(
            'A_FAVOR'
        );
    }
);

test(
    'no permite reprogramar cuando un ticket ya fue utilizado',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reserva,
            ,
            ,
            $ticket,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        $ticket->update([
            'estado' =>
                EstadoTicket::UTILIZADO,

            'validado_en' =>
                now(),

            'validado_por_usuario_id' =>
                $cliente->id,
        ]);


        expect(
            fn () =>
                app(
                    CotizarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reserva,
                        $viajeDestino
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class,
            'No se puede reprogramar una reserva con tickets ya utilizados.'
        );
    }
);

test(
    'solo una reserva confirmada puede cotizar reprogramacion',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reserva,
            ,
            ,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        $reserva->update([
            'estado' =>
                EstadoReserva::PENDIENTE_PAGO,
        ]);


        expect(
            fn () =>
                app(
                    CotizarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reserva,
                        $viajeDestino
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class
        );
    }
);

test(
    'viaje destino debe estar programado',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reserva,
            ,
            ,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        Viaje::query()
            ->whereKey(
                $viajeDestino->id
            )
            ->update([
                'estado' =>
                    EstadoViaje::BORRADOR->value,
            ]);


        $viajeDestino->refresh();


        expect(
            fn () =>
                app(
                    CotizarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reserva,
                        $viajeDestino
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class,
            'El viaje destino debe encontrarse PROGRAMADO.'
        );
    }
);

test(
    'reserva con reprogramacion activa no puede iniciar otra',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reserva,
            ,
            ,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        ReprogramacionReserva::query()
            ->create([

                'codigo' =>
                    'RPG-'
                    .Str::upper(
                        (string) Str::ulid()
                    ),

                'reserva_origen_id' =>
                    $reserva->id,

                'estado' =>
                    EstadoReprogramacion::SOLICITADA,

                'solicitada_por_usuario_id' =>
                    $cliente->id,

                'motivo' =>
                    'Cambio de fecha',

                'solicitada_en' =>
                    now(),
            ]);


        expect(
            fn () =>
                app(
                    CotizarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reserva,
                        $viajeDestino
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class,
            'La reserva ya posee una reprogramación activa.'
        );
    }
);

test(
    'cliente no puede cotizar reprogramacion de reserva ajena',
    function () {

        [
            ,
            ,
            $viajeDestino,
            $reserva,
            ,
            ,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        $otroCliente =
            Usuario::factory()
                ->create([
                    'activo' =>
                        true,
                ]);


        $rolCliente =
            Rol::query()
                ->where(
                    'nombre',
                    'CLIENTE'
                )
                ->firstOrFail();


        $otroCliente
            ->roles()
            ->attach(
                $rolCliente->id
            );


        expect(
            fn () =>
                app(
                    CotizarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $otroCliente,
                        $reserva,
                        $viajeDestino
                    )
        )->toThrow(
            OperacionUsuarioNoPermitidaException::class
        );
    }
);

test(
    'prepara una nueva reserva destino sin modificar la reserva original',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            $ocupacionOrigen,
            $pasajeroOrigen,
            $ticketOrigen,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this,
                '70.00'
            );


        $asientoDestino =
            $viajeDestino
                ->asientosViaje()
                ->where(
                    'codigo',
                    'A1'
                )
                ->firstOrFail();


        $reprogramacion =
            app(
                PrepararReprogramacionReserva::class
            )
                ->ejecutar(
                    usuario:
                        $cliente,

                    reservaOrigen:
                        $reservaOrigen,

                    viajeDestino:
                        $viajeDestino,

                    asignaciones: [
                        [
                            'pasajero_reserva_id' =>
                                $pasajeroOrigen->id,

                            'asiento_viaje_id' =>
                                $asientoDestino->id,
                        ],
                    ],

                    motivo:
                        'Cambio de fecha'
                );


        /*
        |--------------------------------------------------------------------------
        | Reprogramación
        |--------------------------------------------------------------------------
        */

        expect(
            $reprogramacion->estado
        )->toBe(
            EstadoReprogramacion::PENDIENTE_AJUSTE
        );


        expect(
            $reprogramacion->reserva_origen_id
        )->toBe(
            $reservaOrigen->id
        );


        expect(
            $reprogramacion->reserva_destino_id
        )->not->toBeNull();


        expect(
            $reprogramacion->motivo
        )->toBe(
            'Cambio de fecha'
        );


        /*
        |--------------------------------------------------------------------------
        | Reserva destino
        |--------------------------------------------------------------------------
        */

        $reservaDestino =
            $reprogramacion
                ->reservaDestino;


        expect(
            $reservaDestino->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $reservaDestino->viaje_id
        )->toBe(
            $viajeDestino->id
        );


        expect(
            $reservaDestino->total
        )->toBe(
            '70.00'
        );


        expect(
            $reservaDestino->cliente_usuario_id
        )->toBe(
            $cliente->id
        );


        /*
        |--------------------------------------------------------------------------
        | Pasajero copiado
        |--------------------------------------------------------------------------
        */

        expect(
            $reservaDestino
                ->pasajeros
        )->toHaveCount(1);


        $pasajeroDestino =
            $reservaDestino
                ->pasajeros
                ->first();


        expect(
            $pasajeroDestino
                ->numero_documento
        )->toBe(
            $pasajeroOrigen
                ->numero_documento
        );


        expect(
            $pasajeroDestino
                ->nombres
        )->toBe(
            $pasajeroOrigen
                ->nombres
        );


        /*
         * El precio NO fue copiado.
         *
         * Se obtuvo nuevamente desde
         * la tarifa del viaje destino.
         */
        expect(
            $pasajeroDestino->precio
        )->toBe(
            '70.00'
        );


        /*
        |--------------------------------------------------------------------------
        | Nueva ocupación
        |--------------------------------------------------------------------------
        */

        $ocupacionDestino =
            $pasajeroDestino
                ->ocupacionAsiento;


        expect(
            $ocupacionDestino->viaje_id
        )->toBe(
            $viajeDestino->id
        );


        expect(
            $ocupacionDestino->asiento_viaje_id
        )->toBe(
            $asientoDestino->id
        );


        expect(
            $ocupacionDestino->estado
        )->toBe(
            EstadoOcupacionAsiento::RESERVADO
        );


        /*
         * El segmento se conserva.
         */
        expect(
            $ocupacionDestino->punto_origen_id
        )->toBe(
            $ocupacionOrigen->punto_origen_id
        );


        expect(
            $ocupacionDestino->punto_destino_id
        )->toBe(
            $ocupacionOrigen->punto_destino_id
        );


        /*
        |--------------------------------------------------------------------------
        | Todavía NO hay ticket nuevo
        |--------------------------------------------------------------------------
        */

        expect(
            $reservaDestino
                ->tickets()
                ->count()
        )->toBe(0);


        /*
        |--------------------------------------------------------------------------
        | Reserva origen intacta
        |--------------------------------------------------------------------------
        */

        $reservaOrigen->refresh();

        $ocupacionOrigen->refresh();

        $ticketOrigen->refresh();


        expect(
            $reservaOrigen->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reservaOrigen->total
        )->toBe(
            '50.00'
        );


        expect(
            $ocupacionOrigen->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        expect(
            $ticketOrigen->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );
    }
);

test(
    'operador que prepara reprogramacion conserva al cliente propietario original',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            ,
            $pasajeroOrigen,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this,
                '70.00'
            );


        $operador =
            Usuario::factory()
                ->create([
                    'activo' =>
                        true,
                ]);


        $rolOperador =
            Rol::query()
                ->where(
                    'nombre',
                    'OPERADOR'
                )
                ->firstOrFail();


        $operador
            ->roles()
            ->attach(
                $rolOperador->id
            );


        $asientoDestino =
            $viajeDestino
                ->asientosViaje()
                ->where(
                    'codigo',
                    'A1'
                )
                ->firstOrFail();


        $reprogramacion =
            app(
                PrepararReprogramacionReserva::class
            )
                ->ejecutar(
                    usuario:
                        $operador,

                    reservaOrigen:
                        $reservaOrigen,

                    viajeDestino:
                        $viajeDestino,

                    asignaciones: [
                        [
                            'pasajero_reserva_id' =>
                                $pasajeroOrigen->id,

                            'asiento_viaje_id' =>
                                $asientoDestino->id,
                        ],
                    ],

                    motivo:
                        'Atención en counter'
                );


        $reservaDestino =
            $reprogramacion
                ->reservaDestino;


        /*
         * Propietario comercial.
         */
        expect(
            $reservaDestino->cliente_usuario_id
        )->toBe(
            $cliente->id
        );


        /*
         * Usuario que realmente ejecutó
         * la creación.
         */
        expect(
            $reservaDestino->creado_por_usuario_id
        )->toBe(
            $operador->id
        );


        /*
         * También queda registrado en
         * el proceso de reprogramación.
         */
        expect(
            $reprogramacion
                ->solicitada_por_usuario_id
        )->toBe(
            $operador->id
        );


        /*
         * El bloqueo nuevo fue ejecutado
         * por el operador.
         */
        expect(
            $reservaDestino
                ->ocupaciones()
                ->firstOrFail()
                ->usuario_id
        )->toBe(
            $operador->id
        );
    }
);

test(
    'preparar reprogramacion exige un nuevo asiento para cada pasajero',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            ,
            ,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        $reservasAntes =
            Reserva::query()
                ->count();


        expect(
            fn () =>
                app(
                    PrepararReprogramacionReserva::class
                )
                    ->ejecutar(
                        usuario:
                            $cliente,

                        reservaOrigen:
                            $reservaOrigen,

                        viajeDestino:
                            $viajeDestino,

                        asignaciones:
                            []
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class,
            'Debe seleccionar un asiento para cada pasajero.'
        );


        /*
         * No se creó nada parcialmente.
         */
        expect(
            Reserva::query()
                ->count()
        )->toBe(
            $reservasAntes
        );


        expect(
            ReprogramacionReserva::query()
                ->count()
        )->toBe(0);


        expect(
            OcupacionAsiento::query()

                ->where(
                    'viaje_id',
                    $viajeDestino->id
                )

                ->count()
        )->toBe(0);
    }
);

test(
    'si el nuevo asiento deja de estar disponible no crea una reprogramacion parcial',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            ,
            $pasajeroOrigen,
            ,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this
            );


        $asientoDestino =
            $viajeDestino
                ->asientosViaje()
                ->where(
                    'codigo',
                    'A1'
                )
                ->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | Otro usuario toma temporalmente el asiento
        |--------------------------------------------------------------------------
        */

        $otroUsuario =
            Usuario::factory()
                ->create([
                    'activo' =>
                        true,
                ]);


        $bloqueoAjeno =
            app(
                BloquearAsiento::class
            )
                ->ejecutar(
                    $viajeDestino,

                    $asientoDestino->id,

                    $reservaOrigen
                        ->ocupaciones()
                        ->firstOrFail()
                        ->punto_origen_id,

                    $reservaOrigen
                        ->ocupaciones()
                        ->firstOrFail()
                        ->punto_destino_id,

                    $otroUsuario->id
                );


        expect(
            $bloqueoAjeno->estado
        )->toBe(
            EstadoOcupacionAsiento::BLOQUEADO
        );


        $reservasAntes =
            Reserva::query()
                ->count();


        $ocupacionesDestinoAntes =
            OcupacionAsiento::query()

                ->where(
                    'viaje_id',
                    $viajeDestino->id
                )

                ->count();


        /*
        |--------------------------------------------------------------------------
        | Nuestro cliente intenta tomar el mismo asiento
        |--------------------------------------------------------------------------
        */

        expect(
            fn () =>
                app(
                    PrepararReprogramacionReserva::class
                )
                    ->ejecutar(
                        usuario:
                            $cliente,

                        reservaOrigen:
                            $reservaOrigen,

                        viajeDestino:
                            $viajeDestino,

                        asignaciones: [
                            [
                                'pasajero_reserva_id' =>
                                    $pasajeroOrigen->id,

                                'asiento_viaje_id' =>
                                    $asientoDestino->id,
                            ],
                        ]
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class
        );


        /*
        |--------------------------------------------------------------------------
        | No se creó Reserva destino
        |--------------------------------------------------------------------------
        */

        expect(
            Reserva::query()
                ->count()
        )->toBe(
            $reservasAntes
        );


        /*
        |--------------------------------------------------------------------------
        | No apareció otro bloqueo
        |--------------------------------------------------------------------------
        |
        | El único que continúa es el bloqueo ajeno
        | creado antes de ejecutar nuestra Action.
        |
        */

        expect(
            OcupacionAsiento::query()

                ->where(
                    'viaje_id',
                    $viajeDestino->id
                )

                ->count()
        )->toBe(
            $ocupacionesDestinoAntes
        );


        expect(
            ReprogramacionReserva::query()
                ->count()
        )->toBe(0);
    }
);

test(
    'consolida reprogramacion sin diferencia tarifaria de forma atomica',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            $ocupacionOrigen,
            ,
            $ticketOrigen,
            $reprogramacion,
        ] =
            prepararReprogramacionSinDiferenciaRf10(
                $this
            );


        $reservaDestinoId =
            $reprogramacion
                ->reserva_destino_id;


        /*
        |--------------------------------------------------------------------------
        | Antes
        |--------------------------------------------------------------------------
        */

        expect(
            $reservaOrigen->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $ticketOrigen->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $reprogramacion->estado
        )->toBe(
            EstadoReprogramacion::PENDIENTE_AJUSTE
        );


        /*
        |--------------------------------------------------------------------------
        | Consolidar
        |--------------------------------------------------------------------------
        */

        $resultado =
            app(
                ConsolidarReprogramacionReserva::class
            )
                ->ejecutar(
                    $cliente,
                    $reprogramacion
                );


        /*
        |--------------------------------------------------------------------------
        | Reprogramación
        |--------------------------------------------------------------------------
        */

        expect(
            $resultado->estado
        )->toBe(
            EstadoReprogramacion::COMPLETADA
        );


        expect(
            $resultado
                ->completada_por_usuario_id
        )->toBe(
            $cliente->id
        );


        expect(
            $resultado
                ->completada_en
        )->not->toBeNull();


        /*
        |--------------------------------------------------------------------------
        | Reserva origen
        |--------------------------------------------------------------------------
        */

        $reservaOrigen->refresh();

        $ocupacionOrigen->refresh();

        $ticketOrigen->refresh();


        expect(
            $reservaOrigen->estado
        )->toBe(
            EstadoReserva::CANCELADA
        );


        expect(
            $reservaOrigen
                ->cancelada_en
        )->not->toBeNull();


        expect(
            $reservaOrigen
                ->motivo_cancelacion
        )->toContain(
            'reprogramación'
        );


        expect(
            $ocupacionOrigen->estado
        )->toBe(
            EstadoOcupacionAsiento::LIBERADO
        );


        expect(
            $ticketOrigen->estado
        )->toBe(
            EstadoTicket::ANULADO
        );


        expect(
            $ticketOrigen
                ->anulado_en
        )->not->toBeNull();


        /*
        |--------------------------------------------------------------------------
        | Reserva destino
        |--------------------------------------------------------------------------
        */

        $reservaDestino =
            Reserva::query()
                ->findOrFail(
                    $reservaDestinoId
                );


        expect(
            $reservaDestino->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reservaDestino
                ->confirmada_en
        )->not->toBeNull();


        expect(
            $reservaDestino->expira_en
        )->toBeNull();


        expect(
            $reservaDestino->viaje_id
        )->toBe(
            $viajeDestino->id
        );


        /*
        |--------------------------------------------------------------------------
        | Nueva ocupación
        |--------------------------------------------------------------------------
        */

        $ocupacionDestino =
            $reservaDestino
                ->ocupaciones()
                ->firstOrFail();


        expect(
            $ocupacionDestino->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        expect(
            $ocupacionDestino->expira_en
        )->toBeNull();


        /*
        |--------------------------------------------------------------------------
        | Nuevo ticket
        |--------------------------------------------------------------------------
        */

        $ticketDestino =
            $reservaDestino
                ->tickets()
                ->firstOrFail();


        expect(
            $ticketDestino->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $ticketDestino->codigo
        )->not->toBe(
            $ticketOrigen->codigo
        );
    }
);

test(
    'reprogramacion completada registra trazabilidad consolidada',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            ,
            ,
            ,
            $reprogramacion,
        ] =
            prepararReprogramacionSinDiferenciaRf10(
                $this
            );


        app(
            ConsolidarReprogramacionReserva::class
        )
            ->ejecutar(
                $cliente,
                $reprogramacion
            );


        $operacion =
            OperacionPostventa::query()

                ->where(
                    'tipo',
                    TipoOperacionPostventa::REPROGRAMACION->value
                )

                ->where(
                    'reserva_id',
                    $reservaOrigen->id
                )

                ->firstOrFail();


        expect(
            $operacion->origen
        )->toBe(
            OrigenOperacionPostventa::USUARIO
        );


        expect(
            $operacion
                ->ejecutado_por_usuario_id
        )->toBe(
            $cliente->id
        );


        expect(
            $operacion->datos[
                'reprogramacion_id'
            ]
        )->toBe(
            $reprogramacion->id
        );


        expect(
            $operacion->datos[
                'reserva_origen_id'
            ]
        )->toBe(
            $reservaOrigen->id
        );


        expect(
            $operacion->datos[
                'reserva_destino_id'
            ]
        )->toBe(
            $reprogramacion
                ->reserva_destino_id
        );


        expect(
            $operacion->datos[
                'viaje_destino_id'
            ]
        )->toBe(
            $viajeDestino->id
        );


        expect(
            $operacion->datos[
                'diferencia_tarifaria'
            ]
        )->toBe(
            '0.00'
        );
    }
);

test(
    'consolidar dos veces no duplica tickets ni trazabilidad',
    function () {

        [
            $cliente,
            ,
            ,
            ,
            ,
            ,
            ,
            $reprogramacion,
        ] =
            prepararReprogramacionSinDiferenciaRf10(
                $this
            );


        $action =
            app(
                ConsolidarReprogramacionReserva::class
            );


        $primera =
            $action->ejecutar(
                $cliente,
                $reprogramacion
            );


        $segunda =
            $action->ejecutar(
                $cliente,
                $reprogramacion
            );


        expect(
            $segunda->id
        )->toBe(
            $primera->id
        );


        expect(
            $segunda->estado
        )->toBe(
            EstadoReprogramacion::COMPLETADA
        );


        expect(
            Ticket::query()

                ->where(
                    'reserva_id',
                    $segunda
                        ->reserva_destino_id
                )

                ->count()
        )->toBe(1);


        expect(
            OperacionPostventa::query()

                ->where(
                    'tipo',
                    TipoOperacionPostventa::REPROGRAMACION->value
                )

                ->where(
                    'reserva_id',
                    $segunda
                        ->reserva_origen_id
                )

                ->count()
        )->toBe(1);
    }
);

test(
    'no consolida mientras exista diferencia tarifaria pendiente',
    function () {

        [
            $cliente,
            ,
            $viajeDestino,
            $reservaOrigen,
            $ocupacionOrigen,
            $pasajeroOrigen,
            $ticketOrigen,
        ] =
            crearContextoCotizacionReprogramacionRf10(
                $this,
                '70.00'
            );


        $asientoDestino =
            $viajeDestino
                ->asientosViaje()
                ->where(
                    'codigo',
                    'A1'
                )
                ->firstOrFail();


        $reprogramacion =
            app(
                PrepararReprogramacionReserva::class
            )
                ->ejecutar(
                    usuario:
                        $cliente,

                    reservaOrigen:
                        $reservaOrigen,

                    viajeDestino:
                        $viajeDestino,

                    asignaciones: [
                        [
                            'pasajero_reserva_id' =>
                                $pasajeroOrigen->id,

                            'asiento_viaje_id' =>
                                $asientoDestino->id,
                        ],
                    ]
                );


        expect(
            fn () =>
                app(
                    ConsolidarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reprogramacion
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class,
            'La reprogramación posee una diferencia tarifaria pendiente de resolver.'
        );


        /*
        |--------------------------------------------------------------------------
        | Todo permanece intacto
        |--------------------------------------------------------------------------
        */

        $reservaOrigen->refresh();

        $ocupacionOrigen->refresh();

        $ticketOrigen->refresh();

        $reprogramacion->refresh();


        expect(
            $reservaOrigen->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $ocupacionOrigen->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        expect(
            $ticketOrigen->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );


        expect(
            $reprogramacion->estado
        )->toBe(
            EstadoReprogramacion::PENDIENTE_AJUSTE
        );


        $reservaDestino =
            $reprogramacion
                ->reservaDestino;


        expect(
            $reservaDestino->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );


        expect(
            $reservaDestino
                ->tickets()
                ->count()
        )->toBe(0);
    }
);

test(
    'no consolida si un ticket original fue utilizado despues de preparar la reprogramacion',
    function () {

        [
            $cliente,
            ,
            ,
            $reservaOrigen,
            $ocupacionOrigen,
            ,
            $ticketOrigen,
            $reprogramacion,
        ] =
            prepararReprogramacionSinDiferenciaRf10(
                $this
            );


        /*
         * Simulamos un cambio concurrente
         * posterior a la preparación.
         */
        $ticketOrigen->update([
            'estado' =>
                EstadoTicket::UTILIZADO,

            'validado_en' =>
                now(),

            'validado_por_usuario_id' =>
                $cliente->id,
        ]);


        expect(
            fn () =>
                app(
                    ConsolidarReprogramacionReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reprogramacion
                    )
        )->toThrow(
            OperacionReprogramacionInvalidaException::class
        );


        $reservaOrigen->refresh();

        $ocupacionOrigen->refresh();

        $ticketOrigen->refresh();


        expect(
            $reservaOrigen->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $ocupacionOrigen->estado
        )->toBe(
            EstadoOcupacionAsiento::CONFIRMADO
        );


        expect(
            $ticketOrigen->estado
        )->toBe(
            EstadoTicket::UTILIZADO
        );


        $reservaDestino =
            Reserva::query()
                ->findOrFail(
                    $reprogramacion
                        ->reserva_destino_id
                );


        expect(
            $reservaDestino->estado
        )->toBe(
            EstadoReserva::PENDIENTE_PAGO
        );
    }
);

test(
    'reserva confirmada proveniente de reprogramacion no usa cancelacion tradicional',
    function () {

        [
            $cliente,
            ,
            ,
            ,
            ,
            ,
            ,
            $reprogramacion,
        ] =
            prepararReprogramacionSinDiferenciaRf10(
                $this
            );


        $reprogramacion =
            app(
                ConsolidarReprogramacionReserva::class
            )
                ->ejecutar(
                    $cliente,
                    $reprogramacion
                );


        $reservaDestino =
            $reprogramacion
                ->reservaDestino;


        expect(
            $reservaDestino->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            fn () =>
                app(
                    CancelarReserva::class
                )
                    ->ejecutar(
                        $cliente,
                        $reservaDestino,
                        'Ya no viajaré'
                    )
        )->toThrow(
            OperacionReservaInvalidaException::class,
            'Una reserva confirmada proveniente de una reprogramación debe gestionarse mediante el flujo de postventa.'
        );


        $reservaDestino->refresh();


        expect(
            $reservaDestino->estado
        )->toBe(
            EstadoReserva::CONFIRMADA
        );


        expect(
            $reservaDestino
                ->tickets()
                ->firstOrFail()
                ->estado
        )->toBe(
            EstadoTicket::VIGENTE
        );
    }
);

