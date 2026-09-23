<?php

use App\Exceptions\ConfiguracionAsientosInvalidaException;
use App\Exceptions\OperacionAsientoInvalidaException;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use App\Exceptions\ProgramacionViajeInvalidaException;
use App\Exceptions\RecorridoRutaInvalidoException;
use App\Exceptions\OperacionReservaInvalidaException;
use App\Http\Middleware\VerificarPermiso;
use App\Http\Middleware\VerificarRol;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Alias de autorización
        |--------------------------------------------------------------------------
        |
        | Permiten proteger las rutas utilizando nombres
        | expresivos en lugar de las clases directamente.
        |
        */

        $middleware->alias([
            'permiso' => VerificarPermiso::class,
            'rol' => VerificarRol::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Respuestas JSON para la API
        |--------------------------------------------------------------------------
        */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*')
                || $request->expectsJson()
        );


        /*
        |--------------------------------------------------------------------------
        | Operaciones de usuarios
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            OperacionUsuarioNoPermitidaException $exception,
            Request $request
        ) {
            return response()->json([
                'mensaje' => $exception->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        });


        /*
        |--------------------------------------------------------------------------
        | Configuración de asientos
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            ConfiguracionAsientosInvalidaException $exception,
            Request $request
        ) {
            return response()->json([
                'mensaje' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });


        /*
        |--------------------------------------------------------------------------
        | Configuración del recorrido de una ruta
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        |
        | La clase real se llama:
        |
        | RecorridoRutaInvalidoException
        |
        | No:
        |
        | RecorridoRutaInvalidaException
        |
        */

        $exceptions->render(function (
            RecorridoRutaInvalidoException $exception,
            Request $request
        ) {
            return response()->json([
                'mensaje' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });


        /*
        |--------------------------------------------------------------------------
        | Programación de viajes
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            ProgramacionViajeInvalidaException $exception,
            Request $request
        ) {
            return response()->json([
                'mensaje' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });


        /*
        |--------------------------------------------------------------------------
        | RF-06 - Operaciones comerciales de asientos
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            OperacionAsientoInvalidaException $exception,
            Request $request
        ) {
            return response()->json([
                'mensaje' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });


        /*
        |--------------------------------------------------------------------------
        | RF-07 - Operaciones de reservas
        |--------------------------------------------------------------------------
        */

        $exceptions->render(function (
            OperacionReservaInvalidaException $exception,
            Request $request
        ) {
            return response()->json([
                'mensaje' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    })

    ->prefersJsonResponses()
    ->create();
