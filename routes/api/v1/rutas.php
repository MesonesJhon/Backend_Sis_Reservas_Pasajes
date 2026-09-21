<?php

use App\Http\Controllers\Api\V1\PuntoController;
use App\Http\Controllers\Api\V1\RutaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gestión administrativa de puntos
|--------------------------------------------------------------------------
|
| Los clientes no utilizan directamente estos endpoints.
| Son endpoints administrativos/operativos.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/puntos', [
        PuntoController::class,
        'index',
    ])->middleware('permiso:puntos.ver');

    Route::post('/puntos', [
        PuntoController::class,
        'store',
    ])->middleware('permiso:puntos.crear');

    Route::get('/puntos/{punto}', [
        PuntoController::class,
        'show',
    ])->middleware('permiso:puntos.ver');

    Route::put('/puntos/{punto}', [
        PuntoController::class,
        'update',
    ])->middleware('permiso:puntos.editar');

    Route::patch('/puntos/{punto}/activo', [
        PuntoController::class,
        'cambiarActividad',
    ])->middleware('permiso:puntos.cambiar_estado');

    Route::get('/rutas', [
        RutaController::class,
        'index',
    ])->middleware('permiso:rutas.ver');

    Route::post('/rutas', [
        RutaController::class,
        'store',
    ])->middleware('permiso:rutas.crear');

    Route::get('/rutas/{ruta}', [
        RutaController::class,
        'show',
    ])->middleware('permiso:rutas.ver');

    Route::put('/rutas/{ruta}', [
        RutaController::class,
        'update',
    ])->middleware('permiso:rutas.editar');

    Route::patch('/rutas/{ruta}/activo', [
        RutaController::class,
        'cambiarActividad',
    ])->middleware('permiso:rutas.cambiar_estado');

    Route::put('/rutas/{ruta}/recorrido', [
        RutaController::class,
        'configurarRecorrido',
    ])->middleware('permiso:rutas.configurar_recorrido');
});
