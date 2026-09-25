<?php

namespace App\Data\Pagos;

/**
 * Representa la información mínima que nuestro
 * sistema necesita después de crear una Order
 * en el proveedor de pagos.
 *
 * De esta forma el resto de la aplicación
 * no depende del JSON específico de Mercado Pago.
 */
final readonly class ResultadoOrdenPago
{
    public function __construct(

        /**
         * Identificador de la Order
         * generado por Mercado Pago.
         */
        public string $orderId,


        /**
         * URL de Checkout Pro donde se
         * redirigirá al comprador.
         */
        public string $checkoutUrl,


        /**
         * Estado original informado
         * por Mercado Pago.
         */
        public string $estadoProveedor,


        /**
         * Información adicional del estado.
         */
        public ?string $detalleEstado = null

    ) {
    }
}
