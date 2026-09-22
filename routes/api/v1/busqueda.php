<?php

use App\Http\Controllers\Api\V1\BusquedaViajeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DisponibilidadAsientoController;


/*
|--------------------------------------------------------------------------
| Búsqueda comercial de viajes
|--------------------------------------------------------------------------
|
| Este endpoint será utilizado por:
| - Vue
| - Flutter
|
*/

Route::get(
    '/busqueda/viajes',
    [
        BusquedaViajeController::class,
        'index'
    ]
);

Route::middleware('auth:sanctum')
    ->get(
        '/busqueda/viajes/{viaje}/asientos',
        [
            DisponibilidadAsientoController::class,
            'index'
        ]
);
