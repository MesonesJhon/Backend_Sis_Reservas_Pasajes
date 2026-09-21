<?php

use App\Http\Controllers\Api\V1\AutenticacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('autenticacion')->group(function () {

    Route::post(
        '/registrar',
        [AutenticacionController::class, 'registrar']
    );

    Route::post(
        '/iniciar-sesion',
        [AutenticacionController::class, 'iniciarSesion']
    );

    Route::middleware('auth:sanctum')->group(function () {

        Route::get(
            '/usuario',
            [AutenticacionController::class, 'usuario']
        );

        Route::post(
            '/cerrar-sesion',
            [AutenticacionController::class, 'cerrarSesion']
        );
    });
});
