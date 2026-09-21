<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\VerificarPermiso;
use App\Http\Middleware\VerificarRol;
use App\Exceptions\OperacionUsuarioNoPermitidaException;
use Symfony\Component\HttpFoundation\Response;
use App\Exceptions\ConfiguracionAsientosInvalidaException;
use App\Exceptions\RecorridoRutaInvalidoException;

return Application::configure(basePath: dirname(__DIR__))
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
    | Permiten proteger las rutas utilizando nombres expresivos
    | en lugar de referenciar directamente las clases middleware.
    |
    */

        $middleware->alias([
            'permiso' => VerificarPermiso::class,
            'rol' => VerificarRol::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

            /*
            |--------------------------------------------------------------------------
            | Excepciones de negocio
            |--------------------------------------------------------------------------
            |
            | Las excepciones del dominio se transforman aquí en respuestas HTTP,
            | evitando acoplar las Actions a la capa de presentación.
            |
            */

            $exceptions->render(function (
                OperacionUsuarioNoPermitidaException $excepcion,
                Request $request
            ) {
                return response()->json([
                    'mensaje' => $excepcion->getMessage(),
                ], Response::HTTP_FORBIDDEN);
            });


            $exceptions->render(function (
                ConfiguracionAsientosInvalidaException $excepcion,
                Request $request
             ) {
                return response()->json([
                    'mensaje' => $excepcion->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            });

            $exceptions->render(
                function (
                    RecorridoRutaInvalidoException $e
                ) {
                    return response()->json([
                        'mensaje' => $e->getMessage(),
                    ], 422);
                }
            );
    })
    ->prefersJsonResponses()
    ->create();
