<?php

use App\Http\Controllers\Api\V1\ReservaController;
use Illuminate\Support\Facades\Route;

Route::middleware(
    'auth:sanctum'
)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Consultar reservas
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reservas',
        [
            ReservaController::class,
            'index',
        ]
    )
        ->middleware(
            'permiso:reservas.ver'
        );


    /*
    |--------------------------------------------------------------------------
    | Crear reserva
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/reservas',
        [
            ReservaController::class,
            'store',
        ]
    )
        ->middleware(
            'permiso:reservas.crear'
        );


    /*
    |--------------------------------------------------------------------------
    | Detalle de reserva
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reservas/{reserva}',
        [
            ReservaController::class,
            'show',
        ]
    )
        ->middleware(
            'permiso:reservas.ver'
        );



    /*
    |--------------------------------------------------------------------------
    | Cancelar reserva
    |--------------------------------------------------------------------------
    */

    Route::patch(
        '/reservas/{reserva}/cancelar',
        [
            ReservaController::class,
            'cancelar',
        ]
    )
        ->middleware(
            'permiso:reservas.cancelar'
        );


    /*
    |--------------------------------------------------------------------------
    | Confirmar reserva
    |--------------------------------------------------------------------------
    |
    | RF-07:
    |
    | PENDIENTE_PAGO -> CONFIRMADA
    |
    | Esta operación NO está disponible directamente
    | para el rol CLIENTE.
    |
    */

    Route::patch(
        '/reservas/{reserva}/confirmar',
        [
            ReservaController::class,
            'confirmar',
        ]
    )
        ->middleware(
            'permiso:reservas.confirmar'
        );
});
