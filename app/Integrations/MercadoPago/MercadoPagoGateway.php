<?php

namespace App\Integrations\MercadoPago;

use App\Contracts\Pagos\PasarelaPago;
use App\Data\Pagos\ResultadoOrdenPago;
use App\Enums\CanalPago;
use App\Exceptions\PasarelaPagoException;
use App\Data\Pagos\ResultadoConsultaOrdenPago;
use App\Models\Pago;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;

class MercadoPagoGateway implements PasarelaPago
{
    /**
     * Crea una Order de Checkout Pro
     * utilizando Orders API.
     */
    public function crearOrden(
        Pago $pago
    ): ResultadoOrdenPago {

        /*
        |--------------------------------------------------------------------------
        | 1. Configuración
        |--------------------------------------------------------------------------
        */

        $accessToken =
            config(
                'services.mercadopago.access_token'
            );


        if (empty($accessToken)) {
            throw new PasarelaPagoException(
                'Mercado Pago no se encuentra configurado correctamente.'
            );
        }


        $baseUrl =
            rtrim(
                config(
                    'services.mercadopago.base_url'
                ),
                '/'
            );


        /*
        |--------------------------------------------------------------------------
        | 2. Cargar información necesaria
        |--------------------------------------------------------------------------
        */

        $pago->loadMissing([
            'reserva.cliente',
            'reserva.pasajeros',
        ]);


        /*
        |--------------------------------------------------------------------------
        | 3. Construir payload
        |--------------------------------------------------------------------------
        */

        $payload =
            $this->construirPayload(
                $pago
            );


        /*
        |--------------------------------------------------------------------------
        | 4. Llamar a Mercado Pago
        |--------------------------------------------------------------------------
        |
        | No utilizamos retry automático todavía.
        |
        | Si ocurre un timeout, el frontend/backend
        | puede repetir la operación utilizando
        | exactamente la misma idempotency key.
        |
        */

        try {

            $response =
                Http::withToken(
                    $accessToken
                )

                    ->acceptJson()

                    ->asJson()

                    ->withHeaders([
                        'X-Idempotency-Key' =>
                            $pago->idempotency_key,
                    ])

                    ->connectTimeout(5)

                    ->timeout(15)

                    ->post(
                        "{$baseUrl}/v1/orders",
                        $payload
                    );

        } catch (ConnectionException $exception) {

            /*
             * No sabemos si Mercado Pago recibió
             * o procesó la solicitud antes
             * de perderse la conexión.
             *
             * Por eso NO debe generarse
             * inmediatamente otra clave.
             */
            throw new PasarelaPagoException(
                'No fue posible confirmar la comunicación con Mercado Pago. Reintenta la operación con la misma clave de idempotencia.',
                null,
                null,
                true
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Error del proveedor
        |--------------------------------------------------------------------------
        */

        // if (! $response->successful()) {

        //     $codigo =
        //         $response->json(
        //             'error'
        //         )
        //         ?? $response->json(
        //             'code'
        //         );


        //     /*
        //      * Un 5xx puede representar un resultado
        //      * incierto.
        //      *
        //      * La misma clave de idempotencia debe
        //      * reutilizarse.
        //      */
        //     $resultadoIncierto =
        //         $response->serverError();


        //     throw new PasarelaPagoException(
        //         'Mercado Pago no pudo crear la orden de pago.',
        //         is_string($codigo)
        //             ? $codigo
        //             : null,
        //         $response->status(),
        //         $resultadoIncierto
        //     );
        // }


        if (! $response->successful()) {

            /*
            |--------------------------------------------------------------------------
            | Registrar error real de Mercado Pago
            |--------------------------------------------------------------------------
            |
            | IMPORTANTE:
            |
            | No registramos Authorization ni Access Token.
            | Solamente la respuesta enviada por el proveedor.
            |
            */

            Log::warning(
                'Mercado Pago rechazó la creación de la Order.',
                [
                    'http_status' =>
                        $response->status(),

                    /*
                    * Guardamos el JSON completo porque Mercado Pago
                    * suele indicar el campo concreto que falló dentro
                    * de details / errors / message.
                    */
                    'respuesta' =>
                        $response->json(),
                ]
            );


            /*
            * Mercado Pago puede devolver el código
            * en diferentes posiciones dependiendo
            * del tipo de error.
            */
            $codigo =
                $response->json('code')
                ?? $response->json('error')
                ?? $response->json('errors.0.code');


            /*
            * Solamente los 5xx representan
            * un resultado externo incierto.
            */
            $resultadoIncierto =
                $response->serverError();


            throw new PasarelaPagoException(

                'Mercado Pago no pudo crear la orden de pago.',

                is_string($codigo)
                    ? $codigo
                    : null,

                $response->status(),

                $resultadoIncierto
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Datos mínimos necesarios
        |--------------------------------------------------------------------------
        */

        $orderId =
            $response->json(
                'id'
            );


        $checkoutUrl =
            $response->json(
                'checkout_url'
            );


        if (
            ! is_string($orderId)
            || $orderId === ''
            || ! is_string($checkoutUrl)
            || $checkoutUrl === ''
        ) {
            throw new PasarelaPagoException(
                'Mercado Pago devolvió una respuesta incompleta al crear la orden.'
            );
        }


        return new ResultadoOrdenPago(

            orderId:
                $orderId,

            checkoutUrl:
                $checkoutUrl,

            estadoProveedor:
                (string) (
                    $response->json(
                        'status'
                    )
                    ?? 'created'
                ),

            detalleEstado:
                $response->json(
                    'status_detail'
                )
        );
    }


    /**
     * Construye la Order de Checkout Pro.
     *
     * IMPORTANTE:
     *
     * Durante la validación real con Mercado Pago
     * utilizamos únicamente las propiedades necesarias
     * y que ya fueron comprobadas contra la API real.
     *
     * Más adelante podremos incorporar información
     * adicional del viaje de forma incremental.
     */
    private function construirPayload(
        Pago $pago
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Cargar datos necesarios
        |--------------------------------------------------------------------------
        */

        $pago->loadMissing([
            'reserva.cliente',
            'reserva.pasajeros',
        ]);


        $reserva =
            $pago->reserva;


        /*
        |--------------------------------------------------------------------------
        | URLs según canal
        |--------------------------------------------------------------------------
        */

        $urls =
            $this->obtenerUrlsRetorno(
                $pago->canal
            );


        /*
        |--------------------------------------------------------------------------
        | Correo del comprador
        |--------------------------------------------------------------------------
        |
        | Para nuestras pruebas sandbox:
        |
        | TESTUSER....@testuser.com
        |
        */

        $correoPagador =
            $reserva->correo_contacto
            ?: $reserva
                ->cliente
                ?->correo;


        if (
            ! is_string($correoPagador)
            || trim($correoPagador) === ''
        ) {
            throw new PasarelaPagoException(
                'La reserva no tiene un correo de contacto válido para iniciar el pago.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Items
        |--------------------------------------------------------------------------
        |
        | Un pasajero = un item.
        |
        | Mercado Pago calculará:
        |
        | unit_price × quantity
        |
        */

        $items = [];


        foreach (
            $reserva->pasajeros
            as $pasajero
        ) {

            $items[] = [

                /*
                * Lo mantenemos simple durante
                * la integración real.
                */
                'title' =>
                    'Pasaje '
                    .$reserva->codigo,


                'quantity' =>
                    1,


                'unit_price' =>
                    (string)
                    $pasajero->precio,
            ];
        }


        if (empty($items)) {
            throw new PasarelaPagoException(
                'La reserva no tiene pasajeros que puedan ser enviados a Mercado Pago.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Payload mínimo de Checkout Pro
        |--------------------------------------------------------------------------
        |
        | Este formato replica el escenario que
        | ya comprobamos manualmente desde Tinker.
        |
        */

        return [

            'type' =>
                'online',


            'processing_mode' =>
                'manual',


            /*
            * Nunca lo recibe desde frontend.
            *
            * Es el snapshot generado por nuestro backend.
            */
            'total_amount' =>
                (string)
                $pago->monto,


            /*
            * Permite reconciliar posteriormente
            * Mercado Pago con nuestro Pago local.
            */
            'external_reference' =>
                $pago->external_reference,


            /*
            |--------------------------------------------------------------------------
            | Comprador
            |--------------------------------------------------------------------------
            */

            'payer' => [

                'email' =>
                    trim(
                        $correoPagador
                    ),
            ],


            /*
            |--------------------------------------------------------------------------
            | Pasajes
            |--------------------------------------------------------------------------
            */

            'items' =>
                $items,


            /*
            |--------------------------------------------------------------------------
            | Retornos
            |--------------------------------------------------------------------------
            */

            'config' => [

                'online' => [

                    'success_url' =>
                        $urls[
                            'success_url'
                        ],


                    'failure_url' =>
                        $urls[
                            'failure_url'
                        ],


                    'pending_url' =>
                        $urls[
                            'pending_url'
                        ],


                    /*
                    * Ya comprobamos este valor
                    * con la Order mínima.
                    */
                    'auto_return' =>
                        'approved',
                ],
            ],
        ];
    }


    /**
     * Devuelve URLs específicas
     * para WEB o MOVIL.
     */
    private function obtenerUrlsRetorno(
        CanalPago $canal
    ): array {

        $configuracion =
            $canal === CanalPago::MOVIL
                ? config(
                    'services.mercadopago.mobile'
                )
                : config(
                    'services.mercadopago.web'
                );


        foreach (
            [
                'success_url',
                'failure_url',
                'pending_url',
            ]
            as $campo
        ) {

            if (
                empty(
                    $configuracion[
                        $campo
                    ] ?? null
                )
            ) {
                throw new PasarelaPagoException(
                    'Las URLs de retorno de Mercado Pago no están configuradas.'
                );
            }
        }


        return $configuracion;
    }



    /**
     * Consulta directamente en Mercado Pago
     * el estado oficial de una Order.
     *
     * Este método se utiliza principalmente
     * después de recibir un Webhook.
     *
     * El Webhook únicamente informa que ocurrió
     * un evento; el estado financiero real se
     * vuelve a consultar al proveedor.
     */
    public function consultarOrden(
        string $orderId
    ): ResultadoConsultaOrdenPago {

        /*
        |--------------------------------------------------------------------------
        | Configuración
        |--------------------------------------------------------------------------
        */

        $accessToken =
            config(
                'services.mercadopago.access_token'
            );


        if (empty($accessToken)) {
            throw new PasarelaPagoException(
                'Mercado Pago no se encuentra configurado correctamente.'
            );
        }


        $baseUrl =
            rtrim(
                config(
                    'services.mercadopago.base_url'
                ),
                '/'
            );


        /*
        |--------------------------------------------------------------------------
        | Consultar Order
        |--------------------------------------------------------------------------
        */

        try {

            $response =
                Http::withToken(
                    $accessToken
                )
                    ->acceptJson()

                    ->connectTimeout(5)

                    ->timeout(15)

                    ->get(
                        "{$baseUrl}/v1/orders/{$orderId}"
                    );

        } catch (ConnectionException $exception) {

            throw new PasarelaPagoException(
                'No fue posible consultar el estado de la orden en Mercado Pago.',
                null,
                null,
                true
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Error del proveedor
        |--------------------------------------------------------------------------
        */

        if (! $response->successful()) {

            $codigo =
                $response->json(
                    'error'
                )
                ?? $response->json(
                    'code'
                );


            throw new PasarelaPagoException(
                'Mercado Pago no pudo devolver el estado de la orden.',
                is_string($codigo)
                    ? $codigo
                    : null,
                $response->status(),
                $response->serverError()
            );
        }





        /*
        |--------------------------------------------------------------------------
        | Datos obligatorios
        |--------------------------------------------------------------------------
        */

        $id =
            $response->json(
                'id'
            );


        /*
        |--------------------------------------------------------------------------
        | Integridad de la Order consultada
        |--------------------------------------------------------------------------
        |
        | Nunca aceptamos una respuesta correspondiente
        | a otra Order.
        |
        */

        if (
            ! is_string($id)
            || $id === ''
            || ! hash_equals(
                $orderId,
                $id
            )
        ) {
            throw new PasarelaPagoException(
                'Mercado Pago devolvió una Order diferente a la solicitada.'
            );
        }


        $estado =
            $response->json(
                'status'
            );


        if (
            ! is_string($id)
            || $id === ''
            || ! is_string($estado)
            || $estado === ''
        ) {
            throw new PasarelaPagoException(
                'Mercado Pago devolvió una orden incompleta.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Resultado desacoplado de Mercado Pago
        |--------------------------------------------------------------------------
        */

        return new ResultadoConsultaOrdenPago(

            orderId:
                $id,

            estado:
                $estado,

            detalleEstado:
                $response->json(
                    'status_detail'
                ),

            externalReference:
                $response->json(
                    'external_reference'
                ),

            montoTotal:
                (string) (
                    $response->json(
                        'total_amount'
                    )
                    ?? '0.00'
                ),

            montoPagado:
                (string) (
                    $response->json(
                        'total_paid_amount'
                    )
                    ?? '0.00'
                ),

            moneda:
                $response->json(
                    'currency'
                ),

            paymentId:
                $response->json(
                    'transactions.payments.0.id'
                )
        );
    }
}
