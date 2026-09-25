<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pagos\ProcesarWebhookMercadoPago;
use App\Http\Controllers\Controller;
use App\Integrations\MercadoPago\ValidadorFirmaWebhookMercadoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Exceptions\PasarelaPagoException;
use Throwable;

class WebhookMercadoPagoController extends Controller
{
    public function __construct(

        private readonly
        ValidadorFirmaWebhookMercadoPago $validadorFirma,

        private readonly
        ProcesarWebhookMercadoPago $procesarWebhook

    ) {
    }


    public function handle(
        Request $request
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | 1. Obtener data.id de forma compatible con PHP
        |--------------------------------------------------------------------------
        |
        | Mercado Pago envía:
        |
        | ?data.id=ORD...&type=order
        |
        | IMPORTANTE:
        |
        | PHP transforma normalmente:
        |
        | data.id
        |
        | en:
        |
        | data_id
        |
        | Por eso data_id es la primera opción.
        |
        */

        $dataIdQuery =
            $request->query(
                'data_id'
            )

            ?? $request->query(
                'data.id'
            );


        /*
         * El body solamente sirve como fallback
         * para identificar la Order.
         *
         * NO lo utilizamos como reemplazo del query param
         * para validar una firma.
         */

        $orderId =
            $dataIdQuery

            ?? $request->input(
                'data.id'
            );


        if (
            ! is_string($orderId)
            || trim($orderId) === ''
        ) {

            return response()->json(
                [
                    'mensaje' =>
                        'La notificación no contiene una Order válida.',
                ],
                Response::HTTP_BAD_REQUEST
            );
        }


        $orderId =
            trim(
                $orderId
            );


        /*
        |--------------------------------------------------------------------------
        | 2. Headers criptográficos
        |--------------------------------------------------------------------------
        */

        $xSignature =
            $request->header(
                'x-signature'
            );


        $xRequestId =
            $request->header(
                'x-request-id'
            );






        Log::info(
            'Webhook Mercado Pago recibido.',
            [
                'data_id' =>
                    $orderId,

                'type' =>
                    $request->query('type')
                    ?? $request->input('type'),

                'action' =>
                    $request->input('action'),

                'live_mode' =>
                    $request->input('live_mode'),

                'request_id' =>
                    $xRequestId,

                'firma_presente' =>
                    is_string($xSignature)
                    && trim($xSignature) !== '',
            ]
        );



        /*
        |--------------------------------------------------------------------------
        | 3. Validar firma
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        |
        | Para la firma utilizamos exclusivamente
        | el data.id proveniente del QUERY STRING.
        |
        | No usamos body.data.id para reconstruir
        | una firma válida.
        |
        */

        $firmaValida =
            $this->validadorFirma
                ->esValida(

                    $xSignature,

                    $xRequestId,

                    is_string(
                        $dataIdQuery
                    )
                        ? trim(
                            $dataIdQuery
                        )
                        : null
                );




        if (! $firmaValida) {

            return response()->json(
                [
                    'mensaje' =>
                        'Notificación no autorizada.',
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }




        /*
        |--------------------------------------------------------------------------
        | 4. Tipo de evento
        |--------------------------------------------------------------------------
        */

        $tipo =
            $request->query(
                'type'
            )

            ?? $request->input(
                'type'
            );


        /*
         * Solamente procesamos Orders.
         *
         * Otros tópicos se reconocen pero
         * no provocan errores/reintentos.
         */

        if (
            ! is_string($tipo)
            || $tipo !== 'order'
        ) {

            return response()->json([
                'recibido' =>
                    true,

                'ignorado' =>
                    true,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Acción
        |--------------------------------------------------------------------------
        */

        $accion =
            $request->input(
                'action'
            );


        $accion =
            is_string($accion)
                ? $accion
                : null;


        /*
        |--------------------------------------------------------------------------
        | 6. Identificador idempotente del evento
        |--------------------------------------------------------------------------
        |
        | Las notificaciones de Orders no siempre
        | incluyen un "id" superior.
        |
        | Si viene, lo utilizamos.
        |
        | Si no viene, generamos una huella estable
        | basada en los datos del evento.
        |
        */

        $eventoIdProveedor =
            $request->input(
                'id'
            );


        if (
            is_scalar(
                $eventoIdProveedor
            )

            &&

            trim(
                (string)
                $eventoIdProveedor
            ) !== ''
        ) {

            $eventoId =
                trim(
                    (string)
                    $eventoIdProveedor
                );

        } else {

            /*
             * Estos valores NO se utilizan
             * para aprobar financieramente.
             *
             * Solamente forman una huella
             * idempotente del evento.
             */

            $estado =
                (string)
                $request->input(
                    'data.status',
                    ''
                );


            $detalleEstado =
                (string)
                $request->input(
                    'data.status_detail',
                    ''
                );


            $paymentId =
                (string)
                $request->input(
                    'data.transactions.payments.0.id',
                    ''
                );


            $version =
                (string)
                $request->input(
                    'data.version',
                    ''
                );


            $huella =
                implode(
                    '|',
                    [
                        'mercado_pago',
                        'order',
                        $accion ?? '',
                        $orderId,
                        $estado,
                        $detalleEstado,
                        $paymentId,
                        $version,
                    ]
                );


            $eventoId =
                'MP-'
                .hash(
                    'sha256',
                    $huella
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Procesar Webhook
        |--------------------------------------------------------------------------
        |
        | ProcesarWebhookMercadoPago NO confía
        | en el estado recibido en este body.
        |
        | Volverá a consultar:
        |
        | GET /v1/orders/{orderId}
        |
        | directamente a Mercado Pago.
        |
        */

        /*
        |--------------------------------------------------------------------------
        | 7. Procesar Webhook
        |--------------------------------------------------------------------------
        */

        try {

            Log::info(
                'Webhook Mercado Pago recibido para procesamiento.',
                [
                    'order_id' =>
                        $orderId,

                    'evento_id' =>
                        $eventoId,

                    'accion' =>
                        $accion,

                    'tipo' =>
                        $tipo,

                    'request_id' =>
                        $xRequestId,
                ]
            );


            $this->procesarWebhook
                ->ejecutar(

                    eventoId:
                        $eventoId,

                    tipo:
                        $tipo,

                    accion:
                        $accion,

                    orderId:
                        $orderId,

                    requestId:
                        is_string(
                            $xRequestId
                        )
                            ? $xRequestId
                            : null
                );


            Log::info(
                'Webhook Mercado Pago procesado correctamente.',
                [
                    'order_id' =>
                        $orderId,

                    'evento_id' =>
                        $eventoId,
                ]
            );

        } catch (
            PasarelaPagoException $exception
        ) {

            /*
            |--------------------------------------------------------------------------
            | Error conocido de integración
            |--------------------------------------------------------------------------
            |
            | NO mostramos secretos.
            |
            | Sí dejamos suficiente información para
            | encontrar exactamente por qué MP recibió 502.
            |
            */

            Log::error(
                'Error procesando Webhook de Mercado Pago.',
                [
                    'order_id' =>
                        $orderId,

                    'evento_id' =>
                        $eventoId,

                    'mensaje' =>
                        $exception->getMessage(),

                    'codigo_proveedor' =>
                        $exception->codigoProveedor,

                    'status_proveedor' =>
                        $exception->statusProveedor,

                    'reintentar_misma_clave' =>
                        $exception->reintentarMismaClave,
                ]
            );


            /*
            * Lanzamos nuevamente la excepción.
            *
            * Queremos que Mercado Pago reciba un error
            * y vuelva a intentar la notificación.
            */
            throw $exception;

        } catch (
            Throwable $exception
        ) {

            /*
            |--------------------------------------------------------------------------
            | Error inesperado
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Error inesperado procesando Webhook de Mercado Pago.',
                [
                    'order_id' =>
                        $orderId,

                    'evento_id' =>
                        $eventoId,

                    'tipo_excepcion' =>
                        $exception::class,

                    'mensaje' =>
                        $exception->getMessage(),
                ]
            );


            throw $exception;
        }


        /*
        |--------------------------------------------------------------------------
        | 8. Confirmar recepción
        |--------------------------------------------------------------------------
        */

        return response()->json(
            [
                'recibido' =>
                    true,
            ],
            Response::HTTP_OK
        );

    }
}
