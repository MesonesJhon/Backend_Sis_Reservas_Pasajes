<?php

use App\Http\Controllers\Api\V1\BloqueoAsientoController;
use App\Http\Controllers\Api\V1\ConfirmacionReservaAsientoController;
use App\Http\Controllers\Api\V1\ReservaAsientoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Bloqueo temporal
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/asientos/bloquear',
        [
            BloqueoAsientoController::class,
            'store',
        ]
    )
        ->middleware('permiso:reservas.crear');


    /*
    |--------------------------------------------------------------------------
    | Conversión del bloqueo en reserva
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/asientos/reservar',
        [
            ReservaAsientoController::class,
            'store',
        ]
    )
        ->middleware('permiso:reservas.crear');


    /*
    |--------------------------------------------------------------------------
    | Confirmación de la reserva
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/asientos/confirmar',
        [
            ConfirmacionReservaAsientoController::class,
            'store',
        ]
    )
        ->middleware('permiso:reservas.crear');

});
