<?php

use App\Http\Controllers\Api\V1\PermisoController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas administrativas
|--------------------------------------------------------------------------
|
| Todas las rutas de este archivo requieren autenticación mediante
| Sanctum. Cada operación aplica además el permiso correspondiente.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Usuarios
    |--------------------------------------------------------------------------
    |
    | Cada operación requiere un permiso específico.
    |
    */

    Route::get(
        '/usuarios',
        [UsuarioController::class, 'index']
    )->middleware('permiso:usuarios.ver');

    Route::post(
        '/usuarios',
        [UsuarioController::class, 'store']
    )->middleware('permiso:usuarios.crear');

    Route::get(
        '/usuarios/{usuario}',
        [UsuarioController::class, 'show']
    )->middleware('permiso:usuarios.ver');

    Route::put(
        '/usuarios/{usuario}',
        [UsuarioController::class, 'update']
    )->middleware('permiso:usuarios.editar');

    Route::patch(
        '/usuarios/{usuario}/estado',
        [UsuarioController::class, 'cambiarEstado']
    )->middleware('permiso:usuarios.desactivar');

    Route::put(
        '/usuarios/{usuario}/rol',
        [UsuarioController::class, 'asignarRol']
    )->middleware('permiso:roles.asignar');

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/roles',
        [RolController::class, 'index']
    )->middleware('permiso:roles.ver');

    /*
    |--------------------------------------------------------------------------
    | Permisos
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/permisos',
        [PermisoController::class, 'index']
    )->middleware('permiso:roles.ver');
});
