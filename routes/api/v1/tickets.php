<?php

use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;


Route::middleware(
    'auth:sanctum'
)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Listado por Reserva
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reservas/{reserva}/tickets',
        [
            TicketController::class,
            'porReserva',
        ]
    )
        ->middleware(
            'permiso:tickets.ver'
        );


    /*
    |--------------------------------------------------------------------------
    | Validación
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/tickets/validar',
        [
            TicketController::class,
            'validar',
        ]
    )
        ->middleware(
            'permiso:tickets.validar'
        );


    /*
    |--------------------------------------------------------------------------
    | QR gráfico
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/tickets/{ticket:codigo}/qr',
        [
            TicketController::class,
            'qr',
        ]
    )
        ->middleware(
            'permiso:tickets.ver'
        );


    /*
    |--------------------------------------------------------------------------
    | Detalle
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/tickets/{ticket:codigo}',
        [
            TicketController::class,
            'show',
        ]
    )
        ->middleware(
            'permiso:tickets.ver'
        );
});
