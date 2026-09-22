<?php

use App\Http\Controllers\Api\V1\BusquedaViajeController;
use Illuminate\Support\Facades\Route;


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
