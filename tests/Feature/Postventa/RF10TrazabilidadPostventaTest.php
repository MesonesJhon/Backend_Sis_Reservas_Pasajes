<?php

use App\Actions\Postventa\RegistrarOperacionPostventa;
use App\Enums\CanalPago;
use App\Enums\EstadoPago;
use App\Enums\EstadoReserva;
use App\Enums\OrigenOperacionPostventa;
use App\Enums\ProveedorPago;
use App\Enums\TipoOperacionPostventa;
use App\Exceptions\OperacionPostventaInvalidaException;
use App\Models\OperacionPostventa;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Usuario;
use Database\Seeders\TestingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Helpers\CrearViajeProgramable;

uses(RefreshDatabase::class);



beforeEach(function () {

    $this->seed(
        TestingSeeder::class
    );
});

function crearContextoPostventaRf10(
    $test
): array {

    $usuario =
        Usuario::factory()
            ->create([
                'activo' =>
                    true,
            ]);


    $viaje =
        CrearViajeProgramable::ejecutar();


    $reserva =
        Reserva::query()
            ->create([
                'codigo' =>
                    'RSV-'.Str::ulid(),

                'viaje_id' =>
                    $viaje->id,

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


    $pago =
        Pago::query()
            ->create([
                'reserva_id' =>
                    $reserva->id,

                'iniciado_por_usuario_id' =>
                    $usuario->id,

                'proveedor' =>
                    ProveedorPago::MERCADO_PAGO,

                'canal' =>
                    CanalPago::WEB,

                'idempotency_key' =>
                    (string) Str::uuid(),

                'external_reference' =>
                    'PAY-'.Str::ulid(),

                'monto' =>
                    '50.00',

                'moneda' =>
                    'PEN',

                'estado' =>
                    EstadoPago::APROBADO,

                'aprobado_en' =>
                    now(),
            ]);


    return [
        $usuario,
        $viaje,
        $reserva,
        $pago,
    ];
}


test(
    'puede registrar una operacion de trazabilidad postventa',
    function () {

        $usuario =
            Usuario::factory()
                ->create();


        $operacion =
            OperacionPostventa::query()
                ->create([
                    'tipo' =>
                        TipoOperacionPostventa::CANCELACION,

                    'origen' =>
                        OrigenOperacionPostventa::USUARIO,

                    'ejecutado_por_usuario_id' =>
                        $usuario->id,

                    'motivo' =>
                        'Prueba de trazabilidad',

                    'datos' => [
                        'estado_anterior' =>
                            'CONFIRMADA',

                        'estado_nuevo' =>
                            'CANCELADA',
                    ],

                    'ocurrio_en' =>
                        now(),
                ]);


        expect(
            $operacion->exists
        )->toBeTrue();


        expect(
            $operacion->tipo
        )->toBe(
            TipoOperacionPostventa::CANCELACION
        );


        expect(
            $operacion->origen
        )->toBe(
            OrigenOperacionPostventa::USUARIO
        );


        expect(
            $operacion->datos
        )->toBeArray();


        expect(
            $operacion->datos[
                'estado_nuevo'
            ]
        )->toBe(
            'CANCELADA'
        );


        expect(
            $operacion->ejecutadoPor->id
        )->toBe(
            $usuario->id
        );
    }
);


test(
    'clave de idempotencia no permite duplicar una misma operacion',
    function () {

        OperacionPostventa::query()
            ->create([
                'tipo' =>
                    TipoOperacionPostventa::REEMBOLSO,

                'origen' =>
                    OrigenOperacionPostventa::PROVEEDOR,

                'clave_idempotencia' =>
                    'test:reembolso:001',

                'ocurrio_en' =>
                    now(),
            ]);


        expect(
            fn () =>
                OperacionPostventa::query()
                    ->create([
                        'tipo' =>
                            TipoOperacionPostventa::REEMBOLSO,

                        'origen' =>
                            OrigenOperacionPostventa::PROVEEDOR,

                        'clave_idempotencia' =>
                            'test:reembolso:001',

                        'ocurrio_en' =>
                            now(),
                    ])
        )->toThrow(
            QueryException::class
        );
    }
);


test(
    'registrar operacion infiere reserva y viaje desde el pago',
    function () {

        [
            ,
            $viaje,
            $reserva,
            $pago,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        $operacion =
            app(
                RegistrarOperacionPostventa::class
            )
                ->ejecutar(
                    tipo:
                        TipoOperacionPostventa::REEMBOLSO,

                    origen:
                        OrigenOperacionPostventa::PROVEEDOR,

                    pagoId:
                        $pago->id,

                    monto:
                        '50',

                    moneda:
                        'pen',

                    claveIdempotencia:
                        'rf10:test:reembolso:'
                        .$pago->id
                );


        expect(
            $operacion->pago_id
        )->toBe(
            $pago->id
        );


        expect(
            $operacion->reserva_id
        )->toBe(
            $reserva->id
        );


        expect(
            $operacion->viaje_id
        )->toBe(
            $viaje->id
        );


        expect(
            $operacion->monto
        )->toBe(
            '50.00'
        );


        expect(
            $operacion->moneda
        )->toBe(
            'PEN'
        );
    }
);

test(
    'rechaza entidades que pertenecen a reservas diferentes',
    function () {

        [
            $usuario,
            ,
            $reservaUno,
            ,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        [
            ,
            ,
            ,
            $pagoDos,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        expect(
            fn () =>
                app(
                    RegistrarOperacionPostventa::class
                )
                    ->ejecutar(
                        tipo:
                            TipoOperacionPostventa::REEMBOLSO,

                        origen:
                            OrigenOperacionPostventa::USUARIO,

                        reservaId:
                            $reservaUno->id,

                        pagoId:
                            $pagoDos->id,

                        ejecutadoPorUsuarioId:
                            $usuario->id
                    )
        )->toThrow(
            OperacionPostventaInvalidaException::class,
            'Las entidades indicadas pertenecen a reservas diferentes.'
        );
    }
);

test(
    'operacion originada por usuario exige responsable',
    function () {

        [
            ,
            ,
            $reserva,
            ,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        expect(
            fn () =>
                app(
                    RegistrarOperacionPostventa::class
                )
                    ->ejecutar(
                        tipo:
                            TipoOperacionPostventa::CANCELACION,

                        origen:
                            OrigenOperacionPostventa::USUARIO,

                        reservaId:
                            $reserva->id
                    )
        )->toThrow(
            OperacionPostventaInvalidaException::class
        );
    }
);

test(
    'monto requiere moneda',
    function () {

        [
            ,
            ,
            $reserva,
            ,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        expect(
            fn () =>
                app(
                    RegistrarOperacionPostventa::class
                )
                    ->ejecutar(
                        tipo:
                            TipoOperacionPostventa::PENALIDAD,

                        origen:
                            OrigenOperacionPostventa::SISTEMA,

                        reservaId:
                            $reserva->id,

                        monto:
                            '15.00'
                    )
        )->toThrow(
            OperacionPostventaInvalidaException::class
        );
    }
);

test(
    'repetir la misma clave idempotente devuelve la misma operacion',
    function () {

        [
            $usuario,
            ,
            $reserva,
            ,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        $action =
            app(
                RegistrarOperacionPostventa::class
            );


        $primera =
            $action->ejecutar(
                tipo:
                    TipoOperacionPostventa::CANCELACION,

                origen:
                    OrigenOperacionPostventa::USUARIO,

                reservaId:
                    $reserva->id,

                ejecutadoPorUsuarioId:
                    $usuario->id,

                motivo:
                    'Cambio de planes',

                claveIdempotencia:
                    'cancelacion:'
                    .$reserva->id
            );


        $segunda =
            $action->ejecutar(
                tipo:
                    TipoOperacionPostventa::CANCELACION,

                origen:
                    OrigenOperacionPostventa::USUARIO,

                reservaId:
                    $reserva->id,

                ejecutadoPorUsuarioId:
                    $usuario->id,

                motivo:
                    'Cambio de planes',

                claveIdempotencia:
                    'cancelacion:'
                    .$reserva->id
            );


        expect(
            $segunda->id
        )->toBe(
            $primera->id
        );


        expect(
            OperacionPostventa::query()
                ->where(
                    'clave_idempotencia',
                    'cancelacion:'
                    .$reserva->id
                )
                ->count()
        )->toBe(1);
    }
);

test(
    'misma clave idempotente no puede reutilizarse para otra operacion',
    function () {

        [
            $usuarioUno,
            ,
            $reservaUno,
            ,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        [
            $usuarioDos,
            ,
            $reservaDos,
            ,
        ] =
            crearContextoPostventaRf10(
                $this
            );


        $action =
            app(
                RegistrarOperacionPostventa::class
            );


        $clave =
            'rf10:clave:compartida';


        $action->ejecutar(
            tipo:
                TipoOperacionPostventa::CANCELACION,

            origen:
                OrigenOperacionPostventa::USUARIO,

            reservaId:
                $reservaUno->id,

            ejecutadoPorUsuarioId:
                $usuarioUno->id,

            motivo:
                'Primera operación',

            claveIdempotencia:
                $clave
        );


        expect(
            fn () =>
                $action->ejecutar(
                    tipo:
                        TipoOperacionPostventa::CANCELACION,

                    origen:
                        OrigenOperacionPostventa::USUARIO,

                    reservaId:
                        $reservaDos->id,

                    ejecutadoPorUsuarioId:
                        $usuarioDos->id,

                    motivo:
                        'Segunda operación',

                    claveIdempotencia:
                        $clave
                )
        )->toThrow(
            OperacionPostventaInvalidaException::class,
            'La clave de idempotencia ya fue utilizada para una operación diferente.'
        );
    }
);

