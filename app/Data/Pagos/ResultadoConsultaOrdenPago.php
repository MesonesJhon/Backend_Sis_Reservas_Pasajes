<?php

namespace App\Data\Pagos;

/**
 * Resultado normalizado de consultar
 * una Order en el proveedor de pagos.
 *
 * Este objeto solamente transporta datos.
 * No realiza peticiones HTTP.
 */
final readonly class ResultadoConsultaOrdenPago
{
    public function __construct(

        public string $orderId,

        public string $estado,

        public ?string $detalleEstado,

        public ?string $externalReference,

        public string $montoTotal,

        public string $montoPagado,

        public ?string $moneda,

        public ?string $paymentId

    ) {
    }
}
