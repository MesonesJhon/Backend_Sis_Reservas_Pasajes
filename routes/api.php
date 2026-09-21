<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1
|--------------------------------------------------------------------------
|
| Punto de entrada para la primera versión de la API REST.
|
*/

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/v1/autenticacion.php';
    require __DIR__.'/api/v1/administracion.php';
    require __DIR__.'/api/v1/vehiculos.php';
    require __DIR__.'/api/v1/rutas.php';
});
