<?php

use App\Http\Controllers\Api\V1\PagoController;
use App\Http\Controllers\Api\V1\WebhookMercadoPagoController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Rutas públicas de Mercado Pago
|--------------------------------------------------------------------------
|
| Estas rutas NO utilizan auth:sanctum.
|
| Mercado Pago y el navegador externo deben poder
| acceder sin tener un token de nuestro sistema.
|
*/


/*
|--------------------------------------------------------------------------
| Webhook
|--------------------------------------------------------------------------
*/

Route::post(
    '/webhooks/mercado-pago',
    [
        WebhookMercadoPagoController::class,
        'handle',
    ]
);


/*
|--------------------------------------------------------------------------
| Retorno exitoso
|--------------------------------------------------------------------------
|
| IMPORTANTE:
|
| Esta URL NO confirma la reserva.
| Solamente sirve para devolver al usuario
| a nuestra aplicación después del Checkout.
|
*/

Route::get(
    '/pagos/retorno/success',
    function () {

        return response()->json([
            'mensaje' =>
                'Mercado Pago regresó por la URL de éxito.',

            'estado' =>
                'VERIFICANDO_PAGO',

            'importante' =>
                'La confirmación real se realiza mediante Webhook y verificación server-to-server.',
        ]);
    }
);


/*
|--------------------------------------------------------------------------
| Retorno fallido
|--------------------------------------------------------------------------
*/

Route::get(
    '/pagos/retorno/failure',
    function () {

        return response()->json([
            'mensaje' =>
                'Mercado Pago regresó por la URL de pago no completado.',

            'estado' =>
                'PAGO_NO_COMPLETADO',
        ]);
    }
);


/*
|--------------------------------------------------------------------------
| Retorno pendiente
|--------------------------------------------------------------------------
*/

Route::get(
    '/pagos/retorno/pending',
    function () {

        return response()->json([
            'mensaje' =>
                'Mercado Pago regresó con un pago pendiente.',

            'estado' =>
                'PAGO_PENDIENTE',
        ]);
    }
);


/*
|--------------------------------------------------------------------------
| Rutas privadas del módulo de pagos
|--------------------------------------------------------------------------
|
| Solamente estas rutas requieren autenticación Sanctum.
|
*/

Route::middleware(
    'auth:sanctum'
)->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Iniciar pago
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/reservas/{reserva}/pagos',
        [
            PagoController::class,
            'store',
        ]
    )
        ->middleware(
            'permiso:pagos.crear'
        );



    /*
    |--------------------------------------------------------------------------
    | Consultar estado de pago
    |--------------------------------------------------------------------------
    |
    | Utilizado principalmente por Vue o la aplicación móvil
    | después de regresar desde Mercado Pago.
    |
    | No consulta directamente al proveedor.
    | Devuelve el último estado sincronizado por nuestro backend.
    |
    */

    Route::get(
        '/pagos/{pago}',
        [
            PagoController::class,
            'show',
        ]
    )
        ->middleware(
            'permiso:pagos.ver'
        );
});
