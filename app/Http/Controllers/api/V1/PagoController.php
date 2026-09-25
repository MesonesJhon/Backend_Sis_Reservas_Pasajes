<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pagos\IniciarPago;
use App\Enums\CanalPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pagos\IniciarPagoRequest;
use App\Http\Resources\Api\V1\PagoResource;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Pagos\AutorizacionPago;

use App\Models\Pago;
use Illuminate\Http\Request;

/**
 * Controlador encargado de iniciar
 * intentos de pago de una reserva.
 *
 * IMPORTANTE:
 *
 * Este controlador NO confirma pagos.
 *
 * La confirmación financiera real se realiza mediante:
 *
 * Mercado Pago
 *      ↓
 * Webhook
 *      ↓
 * GET /v1/orders/{id}
 *      ↓
 * sincronización backend
 */
class PagoController extends Controller
{
    public function __construct(
        private readonly IniciarPago $iniciarPago,
        private readonly AutorizacionPago $autorizacionPago,
    ) {
    }

    /**
     * Inicia un intento de pago.
     *
     * El usuario debe estar autenticado
     * y tener permiso pagos.crear.
     */
    public function store(
        IniciarPagoRequest $request,
        Reserva $reserva
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Canal validado
        |--------------------------------------------------------------------------
        |
        | IniciarPagoRequest ya validó que solamente
        | pueda ser WEB o MOVIL.
        |
        */

        $canal =
            CanalPago::from(
                $request->validated(
                    'canal'
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Iniciar pago
        |--------------------------------------------------------------------------
        |
        | Nunca recibimos monto desde frontend.
        |
        | IniciarPago utilizará el monto almacenado
        | en la Reserva/Pago.
        |
        */

        $resultado =
            $this->iniciarPago
                ->ejecutar(
                    usuario:
                        $request->user(),

                    reserva:
                        $reserva,

                    canal:
                        $canal,

                    idempotencyKey:
                        $request->validated(
                            'idempotency_key'
                        )
                );


        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        |
        | 201:
        | Se creó un nuevo intento.
        |
        | 200:
        | Se reutilizó el resultado de una
        | petición idempotente anterior.
        |
        */

        return (
            new PagoResource(
                $resultado->pago
            )
        )
            ->response()
            ->setStatusCode(
                $resultado->creado
                    ? Response::HTTP_CREATED
                    : Response::HTTP_OK
            );
    }


    /**
     * Consulta el estado actual de un intento de pago.
     *
     * Este endpoint NO consulta directamente Mercado Pago.
     *
     * Devuelve el estado que nuestro backend ya conoce
     * mediante Webhook o reconciliación.
     */

    public function show(
        Request $request,
        Pago $pago
    ): PagoResource|JsonResponse {

        if (
            ! $this
                ->autorizacionPago
                ->puedeVer(
                    $request->user(),
                    $pago
                )
        ) {

            return response()->json(
                [
                    'mensaje' =>
                        'No tienes autorización para consultar este pago.',
                ],
                Response::HTTP_FORBIDDEN
            );
        }


        $pago->loadMissing(
            'reserva'
        );


        return new PagoResource(
            $pago
        );
    }
}
