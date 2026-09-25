<?php

namespace App\Integrations\MercadoPago;

use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Webhook\WebhookSignatureValidator;
use RuntimeException;

/**
 * Encapsula la validación criptográfica
 * del Webhook de Mercado Pago.
 *
 * El resto de nuestra aplicación no necesita
 * conocer cómo Mercado Pago construye x-signature.
 */
class ValidadorFirmaWebhookMercadoPago
{
    public function esValida(
        ?string $xSignature,
        ?string $xRequestId,
        ?string $dataId
    ): bool {

        $secret =
            config(
                'services.mercadopago.webhook_secret'
            );


        /*
         * Si nosotros no configuramos el secret,
         * es un error de configuración del servidor,
         * no una firma inválida del cliente.
         */
        if (
            ! is_string($secret)
            || trim($secret) === ''
        ) {
            throw new RuntimeException(
                'MERCADOPAGO_WEBHOOK_SECRET no está configurado.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Datos necesarios para validar la firma
        |--------------------------------------------------------------------------
        |
        | Si alguno falta, directamente consideramos
        | inválida la notificación.
        |
        */

        if (
            ! is_string($xSignature)
            || trim($xSignature) === ''

            || ! is_string($xRequestId)
            || trim($xRequestId) === ''

            || ! is_string($dataId)
            || trim($dataId) === ''
        ) {
            return false;
        }

        try {

            WebhookSignatureValidator::validate(

                $xSignature,

                $xRequestId,

                $dataId,

                $secret
            );


            return true;

        } catch (
            InvalidWebhookSignatureException
        ) {

            return false;
        }
    }
}
