<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Representa un problema de comunicación
 * o integración con un proveedor de pagos.
 */
class PasarelaPagoException extends RuntimeException
{
    public function __construct(
        string $message,

        public readonly ?string $codigoProveedor = null,

        public readonly ?int $statusProveedor = null,

        /*
         * true:
         *
         * No sabemos con certeza si Mercado Pago
         * alcanzó a procesar la solicitud.
         *
         * En ese caso debemos reintentar utilizando
         * LA MISMA clave de idempotencia.
         */
        public readonly bool $reintentarMismaClave = false
    ) {
        parent::__construct(
            $message
        );
    }
}
