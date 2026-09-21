<?php

use App\Http\Controllers\Api\V1\TipoVehiculoController;
use App\Http\Controllers\Api\V1\VehiculoController;
use App\Http\Controllers\Api\V1\AsientoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gestión de vehículos
|--------------------------------------------------------------------------
|
| Todas las operaciones requieren autenticación.
| Los permisos determinan qué acciones puede ejecutar cada usuario.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tipos-vehiculo', [
        TipoVehiculoController::class,
        'index',
    ])->middleware('permiso:tipos_vehiculo.ver');

    Route::get('/vehiculos', [
        VehiculoController::class,
        'index',
    ])->middleware('permiso:vehiculos.ver');

    Route::post('/vehiculos', [
        VehiculoController::class,
        'store',
    ])->middleware('permiso:vehiculos.crear');

    Route::get('/vehiculos/{vehiculo}', [
        VehiculoController::class,
        'show',
    ])->middleware('permiso:vehiculos.ver');

    Route::put('/vehiculos/{vehiculo}', [
        VehiculoController::class,
        'update',
    ])->middleware('permiso:vehiculos.editar');

    Route::patch('/vehiculos/{vehiculo}/estado', [
        VehiculoController::class,
        'cambiarEstado',
    ])->middleware('permiso:vehiculos.cambiar_estado');

    Route::patch('/vehiculos/{vehiculo}/activo', [
        VehiculoController::class,
        'cambiarActividad',
    ])->middleware('permiso:vehiculos.cambiar_estado');

    Route::get('/vehiculos/{vehiculo}/asientos', [
        AsientoController::class,
        'index',
    ])->middleware('permiso:asientos.ver');

    Route::put('/vehiculos/{vehiculo}/configuracion-asientos', [
        AsientoController::class,
        'configurar',
    ])->middleware('permiso:asientos.configurar');
});
