<?php

use App\Http\Controllers\Api\V1\ViajeController;
use App\Http\Controllers\Api\V1\PersonalViajeController;
use App\Http\Controllers\Api\V1\TarifaViajeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/viajes', [ViajeController::class, 'index'])
        ->middleware('permiso:viajes.ver');

    Route::post('/viajes', [ViajeController::class, 'store'])
        ->middleware('permiso:viajes.crear');

    Route::get('/viajes/{viaje}', [ViajeController::class, 'show'])
        ->middleware('permiso:viajes.ver');

    Route::put('/viajes/{viaje}', [ViajeController::class, 'update'])
        ->middleware('permiso:viajes.editar');

    Route::get('/viajes/{viaje}/personal', [PersonalViajeController::class, 'index'])
    ->middleware('permiso:viajes.ver');

    Route::put('/viajes/{viaje}/personal', [PersonalViajeController::class, 'update'])
    ->middleware('permiso:viajes.asignar_personal');

    Route::get('/viajes/{viaje}/tarifas', [TarifaViajeController::class, 'index'])
    ->middleware('permiso:tarifas.ver');

    Route::put('/viajes/{viaje}/tarifas', [TarifaViajeController::class, 'update'])
    ->middleware('permiso:tarifas.configurar');

    Route::patch('/viajes/{viaje}/estado', [ViajeController::class, 'cambiarEstado'])
        ->middleware('permiso:viajes.cambiar_estado');

});
